<?php

declare(strict_types=1);

namespace Yemekhane\Ayar;

use RuntimeException;

/**
 * docker-compose.yml'deki ortam değişkenlerini okur (DB_YAZMA_HOST, REDIS_HOST ...).
 */
final class Ortam
{
    public static function zorunlu(string $ad): string
    {
        $deger = getenv($ad);
        if ($deger === false || $deger === '') {
            throw new RuntimeException("Ortam değişkeni tanımlı değil: {$ad}");
        }

        return $deger;
    }

    public static function zorunluSayi(string $ad): int
    {
        $deger = self::zorunlu($ad);
        if (!ctype_digit($deger)) {
            throw new RuntimeException("Ortam değişkeni sayı olmalı: {$ad}");
        }

        return (int) $deger;
    }
}
