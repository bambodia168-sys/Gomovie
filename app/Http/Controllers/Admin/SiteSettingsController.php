<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteSettings;
use App\Support\StudioNavigation;
use App\Support\VideoUploadLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use Throwable;

final class SiteSettingsController extends Controller
{
    private const MEDIA = [
        'transcoding_enabled',
        'high_res_transcoding_enabled',
        'automatic_publication_enabled',
        'public_hls_enabled',
        'media_staging_enabled',
    ];

    private const INTEGRATIONS = [
        'tmdb' => ['tmdb_enabled', 'tmdb_token'],
        'storage' => ['r2_enabled', 'r2_endpoint', 'r2_bucket', 'r2_key', 'r2_secret'],
        'edge' => ['edge_enabled', 'media_edge_url', 'media_signing_key', 'edge_shared_key'],
        'monitoring' => ['queue_probes_enabled'],
    ];

    public function edit(Request $request): View
    {
        abort_unless(StudioNavigation::administrator($request->user()), 403);
        $values = [];
        foreach (array_diff(array_merge(self::MEDIA, ...array_values(self::INTEGRATIONS)), SiteSettings::SECRETS) as $key) {
            $values[$key] = SiteSettings::get($key);
        }

        return view('admin.site-settings', [
            'values' => $values,
            'uploadLimit' => VideoUploadLimit::megabytes(),
            'effectiveUploadMb' => VideoUploadLimit::effectiveBytes() / 1048576,
            'credentials' => array_map(fn ($key) => filled(SiteSettings::get($key)), array_combine(
                SiteSettings::SECRETS, SiteSettings::SECRETS,
            )),
        ]);
    }

    public function update(Request $request, string $section, SiteSettings $settings): RedirectResponse
    {
        abort_unless(StudioNavigation::administrator($request->user()), 403);
        $changes = match ($section) {
            'media' => $this->mediaChanges($request),
            'uploads' => ['video_upload_max_mb' => (int) $request->validate([
                'video_upload_max_mb' => ['required', 'integer', 'min:1', 'max:'.VideoUploadLimit::MAX_MB],
            ])['video_upload_max_mb']],
            default => $this->integrationChanges($request, $section),
        };

        DB::transaction(function () use ($request, $section, $settings, $changes): void {
            $settings->save($changes);
            DB::table('admin_audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'site.settings.updated',
                'resource_type' => 'site_settings',
                'details' => json_encode(['section' => $section, 'fields' => array_keys($changes)], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.site-settings.edit')
            ->withFragment($section)->with('status', 'Site settings saved.');
    }

    public function testFfmpeg(Request $request): RedirectResponse
    {
        abort_unless(StudioNavigation::administrator($request->user()), 403);
        $results = [];
        foreach (['ffmpeg' => 'FFmpeg', 'ffprobe' => 'FFprobe'] as $key => $label) {
            $path = (string) config('gomovie.'.$key.'_binary');
            $version = null;
            if (str_starts_with($path, '/') && is_file($path) && is_executable($path)) {
                try {
                    $process = new Process([$path, '-version'], timeout: 5);
                    $process->mustRun();
                    if (preg_match('/^'.preg_quote($key, '/').' version (\S+)/i', $process->getOutput(), $match)) {
                        $version = $match[1];
                    }
                } catch (Throwable) {
                    // Do not expose process commands, filesystem paths or stderr in the dashboard.
                }
            }
            $results[] = ['label' => $label, 'version' => $version];
        }

        return redirect()->route('admin.site-settings.edit')
            ->withFragment('media')->with('ffmpeg_test', $results);
    }

    private function mediaChanges(Request $request): array
    {
        $changes = $request->validate(array_fill_keys(self::MEDIA, ['required', 'boolean']));
        return array_map(fn ($value) => (bool) $value, $changes);
    }

    private function integrationChanges(Request $request, string $section): array
    {
        $rules = [
            'queue_probes_enabled' => ['required', 'boolean'],
            'tmdb_enabled' => ['required', 'boolean'],
            'tmdb_token' => ['nullable', 'string', 'min:20', 'max:2048'],
            'r2_enabled' => ['required', 'boolean'],
            'r2_endpoint' => ['nullable', 'string', 'regex:'.SiteSettings::R2_ENDPOINT],
            'r2_bucket' => ['nullable', 'string', 'regex:/\A[a-z0-9][a-z0-9-]{1,61}[a-z0-9]\z/D'],
            'r2_key' => ['nullable', 'string', 'max:255'],
            'r2_secret' => ['nullable', 'string', 'max:255'],
            'edge_enabled' => ['required', 'boolean'],
            'media_edge_url' => ['nullable', 'string', 'url:https', 'max:1024'],
            'media_signing_key' => ['nullable', 'string', 'min:32', 'max:255'],
            'edge_shared_key' => ['nullable', 'string', 'min:32', 'max:255'],
        ];
        $changes = $request->validate(array_intersect_key($rules, array_flip(self::INTEGRATIONS[$section])));

        foreach (SiteSettings::SECRETS as $key) {
            if (blank($changes[$key] ?? null)) {
                unset($changes[$key]);
            }
        }

        $requirements = [
            'tmdb_enabled' => ['tmdb_token' => 'Enter a TMDB read access token.'],
            'r2_enabled' => [
                'r2_endpoint' => 'Enter the Cloudflare R2 S3 endpoint.',
                'r2_bucket' => 'Enter the private R2 bucket name.',
                'r2_key' => 'Enter the R2 access key ID.',
                'r2_secret' => 'Enter the R2 secret access key.',
            ],
            'edge_enabled' => [
                'media_edge_url' => 'Enter an HTTPS URL for the deployed playback edge.',
                'media_signing_key' => 'Enter the playback signing key.',
                'edge_shared_key' => 'Enter the shared edge authorization key.',
            ],
        ];
        $missing = [];
        foreach ($requirements as $flag => $fields) {
            if (!(bool) ($changes[$flag] ?? false)) {
                continue;
            }
            foreach ($fields as $key => $message) {
                $value = array_key_exists($key, $changes) ? $changes[$key] : SiteSettings::get($key);
                if (blank($value)) {
                    $missing[$key] = $message;
                }
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        foreach (['queue_probes_enabled', ...array_keys($requirements)] as $key) {
            if (array_key_exists($key, $changes)) {
                $changes[$key] = (bool) $changes[$key];
            }
        }

        return $changes;
    }
}
