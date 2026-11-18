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

    /** Değerlendirme puanı: 1–5 arası tamsayı */
    public static function puan(mixed $deger): int
    {
        if (is_string($deger) && preg_match('/^[1-5]$/', $deger) === 1) {
            return (int) $deger;
        }
        if (is_int($deger) && $deger >= 1 && $deger <= 5) {
            return $deger;
        }

        throw new IsKuraliHatasi('Puan 1 ile 5 arasında bir tamsayı olmalıdır.', 400);
    }

    /** İsteğe bağlı serbest metin: boşsa null, uzunluk karakter (bayt değil) olarak sınırlanır */
    public static function istegeBagliMetin(mixed $deger, string $alanAdi, int $enFazlaKarakter): ?string
    {
        if ($deger === null) {
            return null;
        }
        if (!is_string($deger)) {
            throw new IsKuraliHatasi("{$alanAdi} metin olmalıdır.", 400);
        }
        $metin = trim($deger);
        if ($metin === '') {
            return null;
        }
        if (mb_strlen($metin) > $enFazlaKarakter) {
            throw new IsKuraliHatasi("{$alanAdi} en çok {$enFazlaKarakter} karakter olabilir.", 400);
        }

        return $metin;
    }

    /**
     * Pozitif tamsayılardan oluşan, tekrarsız ve boş olmayan liste (ör. menüdeki yemekler).
     *
     * @return list<int>
     */
    public static function kimlikListesi(mixed $deger, string $alanAdi): array
    {
        if (!is_array($deger) || $deger === [] || !array_is_list($deger)) {
            throw new IsKuraliHatasi("{$alanAdi} en az bir seçim içermelidir.", 400);
        }

        $kimlikler = array_map(static fn (mixed $k): int => self::pozitifTamSayi($k, $alanAdi), $deger);

        return array_values(array_unique($kimlikler));
    }

    /** "2026-11-19T12:15" (datetime-local) ya da "2026-11-19 12:15" → "2026-11-19 12:15:00" */
    public static function tarihSaat(mixed $deger, string $alanAdi): string
    {
        if (is_string($deger)) {
            $metin = str_replace('T', ' ', $deger);
            $zaman = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $metin);
            if ($zaman !== false && $zaman->format('Y-m-d H:i') === $metin) {
                return $zaman->format('Y-m-d H:i:s');
            }
        }

        throw new IsKuraliHatasi("{$alanAdi} YYYY-AA-GG SS:DD biçiminde olmalıdır.", 400);
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
