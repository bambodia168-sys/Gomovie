<?php

namespace App\Services\Recovery;

use JsonException;

/**
 * Verifies a self-contained OFFLINE backup bundle without executing SQL,
 * extracting archives, contacting storage providers or changing a live host.
 * A matching checksum is not proof of an off-site copy or a successful restore.
 */
final class RecoveryBundleVerifier
{
    public function verify(string $directory): array
    {
        $checks = [
            'bundle_directory' => false,
            'manifest_format' => false,
            'required_artifact_roles' => false,
            'artifact_checksums' => false,
            'encrypted_configuration_artifact' => false,
        ];
        $checked = 0;
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            return $this->result($checks, $checked);
        }
        $checks['bundle_directory'] = true;

        $manifestPath = $root.DIRECTORY_SEPARATOR.'manifest.json';
        if (is_link($manifestPath) || !is_file($manifestPath) || filesize($manifestPath) > 65536) {
            return $this->result($checks, $checked);
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->result($checks, $checked);
        }

        if (!is_array($manifest)
            || ($manifest['format'] ?? null) !== 'gomovie-recovery-v1'
            || !is_string($manifest['revision'] ?? null)
            || !preg_match('/\A[a-f0-9]{40}\z/D', $manifest['revision'])
            || !is_array($manifest['artifacts'] ?? null)
            || !array_is_list($manifest['artifacts'])
            || count($manifest['artifacts']) !== 3) {
            return $this->result($checks, $checked);
        }
        $checks['manifest_format'] = true;

        $roles = ['database', 'private_media', 'configuration'];
        $seenRoles = [];
        $seenNames = [];
        $valid = true;
        $configurationEncrypted = false;

        foreach ($manifest['artifacts'] as $artifact) {
            if (!is_array($artifact)) {
                $valid = false;
                continue;
            }
            $role = $artifact['role'] ?? null;
            $name = $artifact['filename'] ?? null;
            $sha = $artifact['sha256'] ?? null;
            $bytes = $artifact['size_bytes'] ?? null;

            if (!is_string($role) || !in_array($role, $roles, true)
                || isset($seenRoles[$role])
                || !is_string($name)
                || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}\z/D', $name)
                || isset($seenNames[$name])
                || !is_string($sha) || !preg_match('/\A[a-f0-9]{64}\z/D', $sha)
                || !is_int($bytes) || $bytes <= 0) {
                $valid = false;
                continue;
            }

            $seenRoles[$role] = true;
            $seenNames[$name] = true;
            if ($role === 'configuration') {
                $configurationEncrypted = (bool) preg_match('/\.(age|gpg)\z/iD', $name);
            }
            $path = $root.DIRECTORY_SEPARATOR.$name;
            if (is_link($path) || !is_file($path)
                || realpath(dirname($path)) !== $root
                || filesize($path) !== $bytes
                || !hash_equals($sha, (string) hash_file('sha256', $path))) {
                $valid = false;
                continue;
            }
            $checked++;
        }

        $checks['required_artifact_roles'] = count($seenRoles) === 3;
        $checks['encrypted_configuration_artifact'] = $configurationEncrypted;
        $checks['artifact_checksums'] = $valid && $checked === 3;

        return $this->result($checks, $checked);
    }

    private function result(array $checks, int $checked): array
    {
        return [
            'integrity_verified' => !in_array(false, $checks, true),
            'checks' => $checks,
            'artifacts_verified' => $checked,
            'offsite_copy_verified' => false,
            'restoration_verified' => false,
        ];
    }
}
