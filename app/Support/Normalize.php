<?php

namespace App\Support;

use Illuminate\Support\Str;

class Normalize
{
    /** Llave comparable: sin tildes, minúsculas, sin signos, "a. m." == "am". */
    public static function key(?string $v): string
    {
        $v = mb_strtolower(Str::ascii(trim((string) $v)));
        $v = trim(preg_replace('/[^a-z0-9]+/', ' ', $v));

        return preg_replace('/\b([ap]) m\b/', '$1m', $v);
    }

    public static function email(?string $v): string
    {
        return mb_strtolower(trim((string) $v));
    }

    public static function digits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    /** Celular solo con dígitos; quita el prefijo +57. */
    public static function phone(?string $v): ?string
    {
        $d = self::digits($v);
        if (strlen($d) === 12 && str_starts_with($d, '57')) {
            $d = substr($d, 2);
        }

        return $d === '' ? null : $d;
    }
}
