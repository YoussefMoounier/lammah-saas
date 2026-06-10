<?php

namespace App\Support\Security;

class PrivacyHasher
{
    public static function email(?string $value): ?string
    {
        return self::hash($value === null ? null : strtolower(trim($value)));
    }

    public static function phone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', trim($value));

        return self::hash($normalized === '' ? null : $normalized);
    }

    public static function ip(?string $value): ?string
    {
        return self::hash($value === null ? null : trim(strtolower($value)));
    }

    public static function url(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::hash(rtrim(strtolower(trim($value)), '/'));
    }

    public static function value(?string $value): ?string
    {
        return self::hash($value === null ? null : trim($value));
    }

    private static function hash(?string $normalized): ?string
    {
        if ($normalized === null || $normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
