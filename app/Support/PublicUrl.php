<?php

namespace App\Support;

class PublicUrl
{
    public static function storage(string $path): string
    {
        return self::fromBase('media/'.self::encodePath($path));
    }

    public static function asset(string $path): string
    {
        return self::fromBase(ltrim($path, '/'));
    }

    private static function fromBase(string $path): string
    {
        $base = app()->bound('request')
            ? rtrim((string) request()->getBaseUrl(), '/')
            : '';

        return $base.'/'.ltrim($path, '/');
    }

    private static function encodePath(string $path): string
    {
        return collect(explode('/', str_replace('\\', '/', $path)))
            ->filter(fn (string $segment) => $segment !== '')
            ->map(fn (string $segment) => rawurlencode($segment))
            ->implode('/');
    }
}
