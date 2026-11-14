<?php

declare(strict_types=1);

namespace Yemekhane\Hata;

use JsonException;
use PDOException;
use Throwable;
use Yemekhane\Cekirdek\Yanit;

/**
 * Uygulamadaki tüm hatalar burada JSON yanıta çevrilir. Beklenmeyen hataların
 * ayrıntısı yalnızca günlüğe yazılır; kullanıcıya genel bir mesaj gider.
 */
final class HataYakalayici
{
    /** Stored procedure'lerin iş kuralı ihlalinde döndürdüğü SQLSTATE (MySQL hata no 1644) */
    private const YORDAM_IS_KURALI_SQLSTATE = '45000';

    public static function yanitaCevir(Throwable $hata): Yanit
    {
        if ($hata instanceof IsKuraliHatasi) {
            return Yanit::json(['mesaj' => $hata->getMessage()], $hata->httpKodu);
        }

        $yordamMesaji = self::yordamMesaji($hata);
        if ($yordamMesaji !== null) {
            return Yanit::json(['mesaj' => $yordamMesaji], 422);
        }

        if ($hata instanceof JsonException) {
            return Yanit::json(['mesaj' => 'İstek gövdesi geçerli bir JSON değil.'], 400);
        }

        error_log(sprintf(
            '[HATA] %s: %s (%s:%d)',
            $hata::class,
            $hata->getMessage(),
            $hata->getFile(),
            $hata->getLine(),
        ));

        return Yanit::json(['mesaj' => 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.'], 500);
    }

    /**
     * SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bakiye yetersiz.' → "Bakiye yetersiz."
     * Diğer veritabanı hataları (ör. CHECK ihlali 3819) için null: kullanıcıya ham gösterilmez.
     */
    private static function yordamMesaji(Throwable $hata): ?string
    {
        if (!$hata instanceof PDOException || $hata->getCode() !== self::YORDAM_IS_KURALI_SQLSTATE) {
            return null;
        }

        $mesaj = $hata->errorInfo[2] ?? null;

        return is_string($mesaj) && $mesaj !== '' ? $mesaj : null;
    }
}
