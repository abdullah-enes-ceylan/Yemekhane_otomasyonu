<?php

declare(strict_types=1);

namespace Yemekhane\Guvenlik;

use Yemekhane\Cekirdek\Istek;
use Yemekhane\Hata\IsKuraliHatasi;

/**
 * Her istek Controller'a ulaşmadan önce buradan geçer: cookie'deki token Redis'te
 * aranır, oturum yoksa 401; yönetici yollarında rol yönetici değilse 403.
 */
final class OturumFiltresi
{
    /** Oturum gerektirmeyen yollar */
    private const ACIK_YOLLAR = ['/api/saglik', '/api/auth/giris', '/api/auth/cikis'];
    private const YONETICI_YOL_ONEKI = '/api/admin/';

    public function __construct(private readonly OturumServisi $oturumServisi)
    {
    }

    public function uygula(Istek $istek): Istek
    {
        if (in_array($istek->yol, self::ACIK_YOLLAR, true)) {
            return $istek;
        }

        $token = $istek->cerez(OturumServisi::CEREZ_ADI);
        $kullanici = $token !== null ? $this->oturumServisi->bulVeYenile($token) : null;
        if ($kullanici === null) {
            throw new IsKuraliHatasi('Oturumunuz bulunamadı ya da süresi doldu. Lütfen giriş yapın.', 401);
        }

        if (str_starts_with($istek->yol, self::YONETICI_YOL_ONEKI) && !$kullanici->yoneticiMi()) {
            throw new IsKuraliHatasi('Bu işlem için yönetici yetkisi gerekir.', 403);
        }

        return $istek->kullaniciyla($kullanici);
    }
}
