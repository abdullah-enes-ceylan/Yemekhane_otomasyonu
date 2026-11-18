<?php

declare(strict_types=1);

namespace Yemekhane\Cekirdek;

use DateTimeImmutable;
use Yemekhane\Hata\IsKuraliHatasi;

/**
 * İstekten gelen ham değerleri doğrulayıp türüne çevirir. Geçersizse 400 ve
 * hangi alanın hatalı olduğunu söyleyen Türkçe mesaj.
 */
final class Girdi
{
    public static function pozitifTamSayi(mixed $deger, string $alanAdi): int
    {
        if (is_int($deger) && $deger > 0) {
            return $deger;
        }
        if (is_string($deger) && preg_match('/^[1-9][0-9]{0,9}$/', $deger) === 1) {
            return (int) $deger;
        }

        throw new IsKuraliHatasi("{$alanAdi} geçerli bir sayı olmalıdır.", 400);
    }

    /**
     * Para tutarı: "50", "50.5", "50.50" (en çok 2 ondalık). float'a çevrilmez, string olarak
     * DECIMAL parametreye kayıpsız gider. Yalnızca BİÇİM kontrol edilir; 10–1000 TL kuralı yordamdadır.
     */
    public static function tutar(mixed $deger, string $alanAdi): string
    {
        if (is_int($deger) || is_float($deger)) {
            $deger = (string) $deger;
        }
        if (is_string($deger) && preg_match('/^[0-9]{1,7}(\.[0-9]{1,2})?$/', $deger) === 1) {
            return $deger;
        }

        throw new IsKuraliHatasi("{$alanAdi} en çok iki ondalıklı bir sayı olmalıdır (ör. 50 ya da 50.25).", 400);
    }

    /** "2026-11-15" biçiminde, takvimde gerçekten var olan bir tarih */
    public static function tarih(mixed $deger, string $alanAdi): DateTimeImmutable
    {
        if (is_string($deger)) {
            $tarih = DateTimeImmutable::createFromFormat('!Y-m-d', $deger);
            if ($tarih !== false && $tarih->format('Y-m-d') === $deger) {
                return $tarih;
            }
        }

        throw new IsKuraliHatasi("{$alanAdi} YYYY-AA-GG biçiminde geçerli bir tarih olmalıdır.", 400);
    }

    public static function bugun(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}
