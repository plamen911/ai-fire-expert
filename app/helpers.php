<?php

declare(strict_types=1);

if (! function_exists('php_ini_to_bytes')) {
    /**
     * Parse a PHP ini shorthand value (e.g. "128M") to bytes.
     */
    function php_ini_to_bytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $bytes = (int) $value;

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }
}

if (! function_exists('max_upload_size_kb')) {
    /**
     * Return the maximum upload size in kilobytes based on PHP ini settings.
     */
    function max_upload_size_kb(): int
    {
        $uploadMax = php_ini_to_bytes(ini_get('upload_max_filesize'));
        $postMax = php_ini_to_bytes(ini_get('post_max_size'));

        return (int) floor(min($uploadMax, $postMax) / 1024);
    }
}

if (! function_exists('format_bytes')) {
    /**
     * Format bytes to a human-readable string (KB, MB, GB).
     */
    function format_bytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1).'GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).'MB';
        }

        return round($bytes / 1024, 1).'KB';
    }
}
