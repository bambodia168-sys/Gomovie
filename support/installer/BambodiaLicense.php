<?php

declare(strict_types=1);

namespace Gomovie\Installer;

use InvalidArgumentException;
use RuntimeException;

final class BambodiaLicense
{
    private const ACTIVATE_URL = 'https://bambodia.com/api/v1/licenses/activate';

    public static function productId(string $root): ?string
    {
        $path = $root.'/support/installer/bambodia-product-id';
        if (!file_exists($path) && !is_link($path)) {
            return null;
        }
        $id = is_file($path) && is_readable($path) ? trim((string) file_get_contents($path)) : '';
        if (!preg_match('/\A[1-9][0-9]{8}\z/D', $id)) {
            throw new RuntimeException('The Bambodia product information is invalid. Download a fresh copy of the archive.');
        }
        return $id;
    }

    public static function activate(string $productId, string $key, string $applicationUrl, ?callable $send = null): void
    {
        $key = trim($key);
        if (!preg_match('/\ABAM-[A-F0-9]{48}\z/D', $key)) {
            throw new InvalidArgumentException('Enter the Bambodia license key from My licenses.');
        }
        $domain = rtrim(strtolower((string) parse_url($applicationUrl, PHP_URL_HOST)), '.');
        if (parse_url($applicationUrl, PHP_URL_SCHEME) !== 'https' || filter_var($domain, FILTER_VALIDATE_IP)
            || !preg_match('/\A(?=.{4,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/D', $domain)) {
            throw new InvalidArgumentException('Use the HTTPS domain where you are installing GoMovie.');
        }

        [$status, $body] = ($send ?? self::request(...))(self::ACTIVATE_URL, [
            'license_key' => $key,
            'product_id' => $productId,
            'domain' => $domain,
        ]);
        if ($status === 429) {
            throw new RuntimeException('Bambodia is limiting activation requests. Wait a minute and retry.');
        }
        if (in_array($status, [403, 409, 422], true)) {
            throw new RuntimeException('Bambodia could not activate this license. Check the key, product, and domain in My licenses.');
        }
        $response = is_string($body) ? json_decode($body, true) : null;
        $returnedProduct = $response['product_id'] ?? null;
        if ($status !== 200 || !is_array($response) || ($response['valid'] ?? null) !== true
            || ($response['status'] ?? null) !== 'activated' || ($response['domain'] ?? null) !== $domain
            || !in_array($returnedProduct, [$productId, (int) $productId], true)) {
            throw new RuntimeException('Bambodia could not confirm activation. Try again before continuing.');
        }
    }

    private static function request(string $url, array $payload): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return [0, null];
        }
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
            ]);
            $body = curl_exec($handle);
            return [(int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body];
        } finally {
            curl_close($handle);
        }
    }
}
