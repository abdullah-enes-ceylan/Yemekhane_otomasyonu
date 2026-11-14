<?php

declare(strict_types=1);

namespace Yemekhane\Hata;

use JsonException;
use Throwable;
use Yemekhane\Cekirdek\Yanit;

/**
 * Uygulamadaki tüm hatalar burada JSON yanıta çevrilir. Beklenmeyen hataların
 * ayrıntısı yalnızca günlüğe yazılır; kullanıcıya genel bir mesaj gider.
 */
final class HataYakalayici
{
    public static function yanitaCevir(Throwable $hata): Yanit
    {
        if ($hata instanceof IsKuraliHatasi) {
            return Yanit::json(['mesaj' => $hata->getMessage()], $hata->httpKodu);
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
}
