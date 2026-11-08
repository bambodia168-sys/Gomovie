<?php

declare(strict_types=1);

/**
 * Standalone, no-CLI, five-step customer installer.
 * Only the public/ directory may be served by the web server.
 * It boots Laravel once, at the final confirmed migration step.
 */
require_once dirname(__DIR__, 2).'/support/installer/InstallerEngine.php';

use Gomovie\Installer\InstallerEngine;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
$envFile = $root.'/.env';
$installedFile = $root.'/storage/app/private/.gomovie-installed';
$setupDir = $root.'/storage/framework/installer';
$ownerKeyFile = $setupDir.'/owner-key';
$error = '';
$notice = '';
$steps = ['Requirements & access', 'Application', 'Database', 'Administrator & install', 'Complete'];

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setupAbort(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, private');
    exit($message);
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','POST'], true)) {
    header('Allow: GET, POST');
    setupAbort(405, 'Method not allowed.');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$secure = in_array((string) ($_SERVER['HTTPS'] ?? ''), ['on','1'], true)
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
$detectedUrl = InstallerEngine::detectedUrl($_SERVER);
$detectedHost = strtolower((string) parse_url($detectedUrl, PHP_URL_HOST));
$isLocal = in_array($detectedHost, ['localhost','127.0.0.1'], true);
if (!$secure && !$isLocal) {
    setupAbort(403, 'Secure HTTPS is required to run the installer. Configure the webserver and TLS first.');
}
if ($detectedUrl === '') {
    setupAbort(400, 'The installation host is invalid.');
}
if (!is_dir($setupDir) && !@mkdir($setupDir, 0700, true) && !is_dir($setupDir)) {
    setupAbort(503, 'Private installer storage is unavailable.');
}
@chmod($setupDir, 0700);
$sessionDir = $setupDir.'/sessions';
if (!is_dir($sessionDir) && !@mkdir($sessionDir, 0700) && !is_dir($sessionDir)) {
    setupAbort(503, 'Private installer sessions are unavailable.');
}
@chmod($sessionDir, 0700);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '1800');
session_save_path($sessionDir);
session_name('GOMOVIE_INSTALL');
session_set_cookie_params([
    'lifetime'=>0,'path'=>'/installer/','secure'=>$secure,
    'httponly'=>true,'samesite'=>'Strict',
]);
if (!session_start()) {
    setupAbort(503, 'Could not open a private installer session.');
}

if (is_file($installedFile)) {
    if (!($_SESSION['completed'] ?? false)) {
        setupAbort(410, 'Gomovie has already been installed. Remove public/installer from the deployed release.');
    }
    $_SESSION['step'] = 5;
} elseif (file_exists($envFile) || is_link($envFile)) {
    setupAbort(409, 'An application environment already exists. Setup cannot overwrite it; use the recovery guide.');
}

if (!isset($_SESSION['created_at']) || time() - (int) $_SESSION['created_at'] > 1800) {
    $_SESSION = ['created_at'=>time()];
    session_regenerate_id(true);
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$_SESSION['step'] ??= 1;
$step = max(1, min(5, (int) $_SESSION['step']));

if (!is_file($installedFile) && !is_file($ownerKeyFile)) {
    // The setup key is generated server-side and must be read in File Manager.
    // No fixed/default owner secret is included in source or HTTP responses.
    try {
        InstallerEngine::createPrivateFile($ownerKeyFile, bin2hex(random_bytes(32))."\n");
    } catch (Throwable) {
        if (!is_file($ownerKeyFile)) {
            setupAbort(503, 'Unable to create a private setup key.');
        }
    }
}
$requirements = InstallerEngine::requirements($root);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf'] ?? '');
    if (strlen($postedToken) !== 64 || !hash_equals($_SESSION['csrf'], $postedToken)) {
        setupAbort(419, 'The installer session expired. Refresh and try again.');
    }
    if (is_file($installedFile)) {
        setupAbort(410, 'This installation is locked.');
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        switch ($action) {
            case 'access':
                if ($step !== 1) throw new RuntimeException('Invalid installation step.');
                if (!isset($_SESSION['attempts'])) $_SESSION['attempts'] = 0;
                if ($_SESSION['attempts'] >= 5) {
                    throw new RuntimeException('Too many attempts in this installer session.');
                }
                $_SESSION['attempts']++;
                $expected = is_file($ownerKeyFile) ? trim((string) file_get_contents($ownerKeyFile)) : '';
                $provided = (string) ($_POST['installer_key'] ?? '');
                if ($expected === '' || !hash_equals($expected, $provided)) {
                    throw new RuntimeException('Installer Key is incorrect.');
                }
                if (in_array(false, $requirements, true)) {
                    throw new RuntimeException('All system requirements must pass first.');
                }
                session_regenerate_id(true);
                $_SESSION['unlocked'] = true;
                $_SESSION['step'] = $step = 2;
                break;

            case 'application':
                if ($step !== 2 || !($_SESSION['unlocked'] ?? false)) {
                    throw new RuntimeException('Complete the access step first.');
                }
                $url = InstallerEngine::validateUrl((string) ($_POST['app_url'] ?? ''),$detectedUrl);
                InstallerEngine::validatePurchaseCode(trim((string) ($_POST['purchase_code'] ?? '')));
                $_SESSION['app_url'] = $url;
                // Purchase Code is never stored or treated as a verified license.
                $_SESSION['step'] = $step = 3;
                break;

            case 'database':
                if ($step !== 3 || !($_SESSION['unlocked'] ?? false) || empty($_SESSION['app_url'])) {
                    throw new RuntimeException('Complete application setup first.');
                }
                $db = InstallerEngine::normalizeDatabase($_POST);
                try {
                    $pdo = InstallerEngine::connect($db);
                    $empty = InstallerEngine::databaseEmpty($pdo,$db['driver']);
                } catch (Throwable) {
                    throw new RuntimeException('Database connection failed. Verify the host, privileges and credentials.');
                }
                if (!$empty) {
                    throw new RuntimeException('Database is not empty. Use a newly created dedicated database.');
                }
                $_SESSION['db'] = $db;
                $_SESSION['step'] = $step = 4;
                break;

            case 'install':
                if ($step !== 4 || !($_SESSION['unlocked'] ?? false)
                    || !is_array($_SESSION['db'] ?? null) || empty($_SESSION['app_url'])) {
                    throw new RuntimeException('Complete the preceding setup steps.');
                }
                if (($_POST['confirm_install'] ?? '') !== 'yes') {
                    throw new RuntimeException('Confirm that this is a new isolated installation.');
                }
                $admin = InstallerEngine::validateAdministrator($_POST);
                if (in_array(false, InstallerEngine::requirements($root), true)) {
                    throw new RuntimeException('Installation requirements changed. Recheck permissions.');
                }
                $op = @fopen($setupDir.'/operation.lock','c');
                if ($op === false || !flock($op, LOCK_EX | LOCK_NB)) {
                    throw new RuntimeException('Another installation operation is active.');
                }
                @chmod($setupDir.'/operation.lock',0600);
                try {
                    if (file_exists($envFile) || is_file($installedFile)) {
                        throw new RuntimeException('Setup has already initialized.');
                    }
                    try {
                        $pdo = InstallerEngine::connect($_SESSION['db']);
                        if (!InstallerEngine::databaseEmpty($pdo, $_SESSION['db']['driver'])) {
                            throw new RuntimeException('Database already contains tables.');
                        }
                    } catch (Throwable) {
                        throw new RuntimeException('Fresh database validation failed. No existing data may be overwritten.');
                    }
                    // Crossing this boundary is irreversible without a controlled
                    // recovery. Exclusive file mode prevents overwriting .env.
                    InstallerEngine::createPrivateFile($envFile,
                        InstallerEngine::environment($_SESSION['app_url'],$_SESSION['db']));

                    require_once $root.'/vendor/autoload.php';
                    $app = require $root.'/bootstrap/app.php';
                    /** @var ConsoleKernel $kernel */
                    $kernel = $app->make(ConsoleKernel::class);
                    $kernel->bootstrap();
                    if ($kernel->call('migrate', ['--force'=>true,'--no-interaction'=>true]) !== 0) {
                        throw new RuntimeException('Laravel migration failed.');
                    }
                    DB::transaction(function () use ($admin): void {
                        if (DB::table('users')->exists()) {
                            throw new RuntimeException('Initial admin already exists.');
                        }
                        $user = \App\Models\User::query()->create([
                            'name'=>$admin['name'],'email'=>$admin['email'],
                            'password'=>$admin['password'],
                        ]);
                        // Ownership of the private Installer Key authenticates
                        // the first operator. Customer email still needs SMTP QA.
                        $user->forceFill([
                            'role'=>'super_admin', 'is_active'=>true,
                            'email_verified_at'=>now(),
                        ])->save();
                        \App\Models\UserProfile::query()->create([
                            'user_id'=>$user->id,'name'=>'Main','slug'=>'main','is_kids'=>false,
                        ]);
                        DB::table('admin_audit_events')->insert([
                            'actor_id'=>$user->id,
                            'action'=>'server.initial_owner_installed',
                            'resource_type'=>'user','resource_id'=>$user->id,
                            'details'=>json_encode(['source'=>'private-installer-key']),
                            'created_at'=>now(),'updated_at'=>now(),
                        ]);
                    });

                    InstallerEngine::createPrivateFile($installedFile,
                        "gomovie-installed-v1\n".gmdate(DATE_ATOM)."\n");
                    @unlink($ownerKeyFile);
                    $_SESSION = ['created_at'=>time(),'completed'=>true,'step'=>5,
                        'csrf'=>bin2hex(random_bytes(32))];
                    session_regenerate_id(true);
                    header('Location: /installer/',true,303);
                    exit;
                } finally {
                    flock($op,LOCK_UN);
                    fclose($op);
                }

            default:
                throw new RuntimeException('Unrecognized installer action.');
        }
    } catch (Throwable $exception) {
        // Do not leak PDO exception messages, SQL, private config or passwords.
        $error = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException
            ? $exception->getMessage() : 'Installer operation failed. Review private host logs.';
        if (file_exists($envFile) || is_file($installedFile)) {
            $error = 'Installation stopped after configuration began. Do not retry on the same database. Contact the server operator for recovery.';
        }
        error_log('Gomovie installer failed at step '.$step.' ('.get_class($exception).').');
    }
}

// No GET query parameter can skip authorization or installation steps.
$step = max(1, min(5,(int) ($_SESSION['step'] ?? 1)));
$allReady = !in_array(false, $requirements, true);
$appUrl = (string) ($_SESSION['app_url'] ?? $detectedUrl);
$csrf = (string) $_SESSION['csrf'];
$showDone = $step === 5 && is_file($installedFile) && ($_SESSION['completed'] ?? false);
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark">
<title>Install Gomovie — Step <?= $step ?> of 5</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#fafafa;background:#09090b}
*{box-sizing:border-box}
body{min-height:100vh;margin:0;background:radial-gradient(circle at 20% 0%,#291015 0%,#09090b 44%);padding:40px 18px}
.wrap{max-width:900px;margin:auto}
.top{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:28px}
.brand{font-size:23px;font-weight:900;letter-spacing:-.04em}
.brand span{color:#ef233c}
.eyebrow{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#f5c518}
.progress{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin:20px 0 26px}
.progress div{height:7px;border-radius:20px;background:#27272a}
.progress div.active{background:#e50914}
.panel{border:1px solid #303034;background:#141418;border-radius:24px;box-shadow:0 22px 70px #0007;padding:clamp(22px,5vw,44px)}
h1{font-size:clamp(27px,4vw,39px);margin:6px 0 12px;letter-spacing:-.04em}
h2{font-size:17px;margin:24px 0 14px}
p{font-size:14px;line-height:1.7;color:#adadb7}
small{font-size:12px;line-height:1.6;color:#90909c}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
label{display:block;font-size:13px;color:#dfdfe4;font-weight:600;margin-bottom:16px}
input,select{display:block;width:100%;margin-top:8px;background:#09090d;color:#fff;border:1px solid #393940;border-radius:12px;padding:13px;font:inherit;min-width:0}
input:focus,select:focus{outline:2px solid #e50914;outline-offset:1px}
input[type=checkbox]{display:inline-block;width:auto;margin:0 8px 0 0}
.actions{display:flex;align-items:center;justify-content:flex-end;margin-top:25px;gap:14px}
button,.btn{border:0;background:#e50914;color:white;border-radius:12px;padding:14px 23px;font-weight:750;cursor:pointer;text-decoration:none;font-size:14px}
button:hover,.btn:hover{background:#bb0811}
button:disabled{opacity:.45;cursor:not-allowed}
.note,.error{border:1px solid #6d4a15;background:#251a0c;padding:14px 17px;border-radius:12px;font-size:13px;color:#ffdd8c;line-height:1.5;margin:18px 0}
.error{border-color:#852929;background:#2a1014;color:#ffc2c5}
.requirement{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:12px 0;border-bottom:1px solid #28282d;font-size:13px}
.requirement strong{font-size:11px;white-space:nowrap;color:#69e2a0}
.requirement strong.bad{color:#fca5a5}
.code{font-family:ui-monospace,SFMono-Regular,monospace;word-break:break-word;color:#f5c518}
.divider{height:1px;background:#29292f;margin:24px 0}
footer{margin-top:28px;text-align:center;font-size:12px;color:#777782}
@media(max-width:640px){body{padding:18px 12px}.grid{grid-template-columns:1fr}.top{align-items:start}.panel{border-radius:18px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top"><div class="brand"><span>GO</span>MOVIE</div><div class="eyebrow">Secure installation · <?= $step ?>/5</div></div>
  <div class="progress" aria-label="Installation progress">
    <?php for ($i=1;$i<=5;$i++): ?><div class="<?= $i <= $step ? 'active' : '' ?>" title="<?= esc($steps[$i-1]) ?>"></div><?php endfor; ?>
  </div>
  <main class="panel">
    <div class="eyebrow">Step <?= $step ?> — <?= esc($steps[$step-1]) ?></div>
    <?php if ($error !== ''): ?><div class="error" role="alert"><?= esc($error) ?></div><?php endif; ?>
    <?php if ($step === 1): ?>
      <h1>Secure application setup</h1>
      <p>Check server readiness and confirm ownership of this hosting account. No database or application data is changed at this step.</p>
      <div class="note">From your hosting File Manager, open <span class="code">storage/framework/installer/owner-key</span>. Copy its secret value below. This key is never exposed on the website.</div>
      <?php foreach ($requirements as $label=>$pass): ?>
        <div class="requirement"><span><?= esc($label) ?></span><strong class="<?= $pass ? '' : 'bad' ?>"><?= $pass ? 'PASS' : 'BLOCKED' ?></strong></div>
      <?php endforeach; ?>
      <form method="post" action="/installer/">
        <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
        <input type="hidden" name="action" value="access">
        <h2>Hosting ownership</h2>
        <label>Installer Key
          <input type="password" name="installer_key" required autocomplete="off" minlength="64" maxlength="64" placeholder="Read the private owner-key file">
        </label>
        <div class="actions"><button type="submit" <?= $allReady ? '' : 'disabled' ?>>Verify &amp; continue</button></div>
      </form>
    <?php elseif ($step === 2): ?>
      <h1>Configure your application</h1>
      <p>The domain is detected from this secure request. Confirm the destination before saving. Purchase Code is optional and is not considered verified by this installer.</p>
      <form method="post" action="/installer/">
        <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
        <input type="hidden" name="action" value="application">
        <label>Application URL
          <input type="url" name="app_url" value="<?= esc($appUrl) ?>" required autocomplete="url" maxlength="253">
        </label>
        <label>Purchase Code (optional)
          <input type="text" name="purchase_code" autocomplete="off" maxlength="120" placeholder="If supplied by your licensing provider">
        </label>
        <p>No licensing service is connected, so entering a Purchase Code does not validate a license. Do not enter passwords or private keys here.</p>
        <div class="actions"><button type="submit">Continue to database</button></div>
      </form>
    <?php elseif ($step === 3): ?>
      <h1>Connect a new database</h1>
      <p>Use an empty, dedicated MySQL 8 or PostgreSQL 16 database. Existing tables are rejected. The password remains only in this private installer session until configuration is saved.</p>
      <form method="post" action="/installer/">
        <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
        <input type="hidden" name="action" value="database">
        <div class="grid">
          <label>Database type<select name="driver" required><option value="mysql">MySQL 8</option><option value="pgsql">PostgreSQL 16</option></select></label>
          <label>Database host<input name="host" value="127.0.0.1" required maxlength="253"></label>
          <label>Port<input name="port" value="3306" required type="number" min="1" max="65535"></label>
          <label>Database name<input name="database" required maxlength="64" autocomplete="off"></label>
          <label>Database username<input name="username" required maxlength="128" autocomplete="off"></label>
          <label>Database password<input name="password" type="password" autocomplete="new-password" maxlength="256"></label>
        </div>
        <div class="actions"><button type="submit">Test connection &amp; continue</button></div>
      </form>
    <?php elseif ($step === 4): ?>
      <h1>Create the first administrator</h1>
      <p>One verified hosting owner becomes the initial super administrator. Final confirmation writes a private configuration file, migrates the empty database, and records an installation lock.</p>
      <div class="note">This installer does not import demo accounts, create subscriptions, activate private streaming or configure live payment providers. Production email delivery still requires separate SMTP acceptance.</div>
      <form method="post" action="/installer/">
        <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
        <input type="hidden" name="action" value="install">
        <div class="grid">
          <label>Administrator name<input name="name" maxlength="100" required></label>
          <label>Administrator email<input name="email" type="email" maxlength="255" required></label>
          <label>Strong password<input name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></label>
          <label>Confirm password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
        </div>
        <label style="font-weight:400;margin-top:12px"><input type="checkbox" name="confirm_install" value="yes" required>I confirm this is a new, empty database and I authorize initial migrations and account creation.</label>
        <div class="actions"><button type="submit">Install Gomovie</button></div>
      </form>
    <?php elseif ($showDone): ?>
      <h1>Gomovie is installed</h1>
      <p>Initial schema migrations, administrator creation and the installation lock were completed. Sensitive setup credentials have been cleared from the installer session.</p>
      <div class="note">Security cleanup required: remove the entire <span class="code">public/installer</span> directory in your hosting File Manager. The permanent installation lock already blocks subsequent setup. Verify HTTPS, SMTP, backups and the live website separately before accepting production.</div>
      <div class="actions"><a class="btn" href="/login">Open sign in</a></div>
    <?php endif; ?>
  </main>
  <footer>Gomovie · Laravel 13 · No command-line access required for customers · Installation is not a streaming/commerce release approval.</footer>
</div>
</body>
</html>
