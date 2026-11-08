# Gomovie — secure five-step customer installer

**Source implementation only.** The standalone installer is implemented at /installer/; an independent aaPanel and cPanel fresh installation has **not** been accepted. Do not install over an existing database. Live streaming, payment, DRM and external SMTP remain disabled.

## Preconditions

Package Laravel source with Composer vendor/ **and** compiled public/build/ files. The deployed document root must be public/ only. PHP 8.4+, PDO MySQL 8 or PostgreSQL 16, OpenSSL, mbstring, fileinfo, secure HTTPS, a new empty database and writable private storage are required. Do not ship a preconfigured .env, source credentials, demo users or a pre-created installation lock. Avoid config:cache before setup, as it would freeze an incorrect APP_KEY/database configuration.

A dedicated File Manager owner key is generated at storage/framework/installer/owner-key. The key is a random 256-bit token readable only outside the document root. The customer opens that file with aaPanel/cPanel File Manager and enters it in Step 1. No default secret is embedded in the source, HTML or GitHub. HTTPS is mandatory on non-local hosts, as are file-based bootstrap sessions, CSRF checks, a strong initial administrator password, isolated DB validation, and an exclusive installation lock.

## Five-step installation

1. **Requirements & access:** checks runtime extensions, private storage permissions, vendor and Vite artifacts, config cache and ability to create .env. The operator enters the private Installer Key from hosting File Manager. Only the verified owner advances.
2. **Application:** auto-detects the current hostname, requires HTTPS on remote hosts and confirms APP_URL. The visible field is labeled **Purchase Code**, optional and **not verified**. No purchase code is stored or sent to a licensing provider.
3. **Database:** validates MySQL/PostgreSQL host, port, database, user and password using PDO. Refuses any database containing existing tables. Database credentials are stored in a private, short-lived PHP installer session and written only at Step 4.
4. **Administrator & install:** requires explicit operator confirmation and a strong administrator name/email/password. Rechecks a completely empty database while holding a server-side operation lock, writes a new .env exclusively with mode 0600, runs Laravel migrations in-process (no CLI/shell or customer terminal), creates the first super-admin and default profile, audits the action, and creates storage/app/private/.gomovie-installed. If migrations or account creation fail, the installer deliberately does NOT delete the .env or retry on a partially migrated DB. Recover with an authorized operator on an isolated host.
5. **Complete:** installation is locked, owner key is deleted, stored installer credentials are cleared, and the operator is directed to remove public/installer/ using File Manager. The root site and login are available; installed users cannot re-run setup.

The browser installer starts before Laravel because Laravel's normal DB-backed session middleware cannot operate on an empty database. The normal root front controller redirects to /installer/ if .env/APP_KEY are missing. Existing .env files are never overwritten, and a persistent lock prevents post-install setup access. The package is compatible in principle with standard Apache/cPanel and Nginx/aaPanel document roots; operators must confirm PHP directory-index routing and TLS termination.

## Isolated QA

The dedicated GitHub Actions jobs use separate empty disposable MySQL 8 and PostgreSQL 16 databases, a temporary copy of the application, Composer dependencies and real compiled Vite assets. It starts a local PHP HTTP server, submits each installer step with CSRF and cookies, verifies the database migrations, first admin, audit event and .env/lock creation, denies fresh-session re-entry, and verifies that homepage and login load. The QA database and files never contain customer data. A PASS is **not** independent production hosting acceptance.

A separate packaging workflow builds a customer-style ZIP containing Composer vendor/, compiled frontend assets, lock manifests, the standalone installer and INSTALL.txt (no Markdown docs). Download the QA bundle and SHA-256 receipt from the [installer ZIP preparation run](https://github.com/bambodia168-sys/Gomovie/actions/runs/42444468713). The ZIP is **not** a production-signoff artifact.

The existing Laravel test suites validate catalog, account and administrator interfaces against SQLite, MySQL and PostgreSQL. These do not substitute for physical browser, mail provider, runtime queue or independent customer installation QA.

## Release acceptance still pending

- Real independent aaPanel and cPanel installation with operator witnesses and full evidence capture
- Operator acceptance of the QA ZIP on real hosts; Composer/npm lock manifests are embedded in each QA ZIP but dependency lockfiles are not yet committed to GitHub main, so repeat-build reproducibility is not certified
- SMTP delivery, password recovery, worker supervision, private media rights/DRM and payments
- Clean rollback and off-site restoration on target databases
- Physical iPhone, Android, desktop and tablet rendering/accessibility review

Do not run migrate:fresh, PHPUnit RefreshDatabase, or any installer operation against production/customer data. See [installation/recovery](INSTALLATION_RECOVERY.md) and [host acceptance](HOST_ACCEPTANCE.md).
