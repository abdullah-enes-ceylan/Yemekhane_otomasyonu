<?php

declare(strict_types=1);

namespace Yemekhane\Controller;

use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yanit;
use Yemekhane\Guvenlik\OturumServisi;
use Yemekhane\Hata\IsKuraliHatasi;
use Yemekhane\Servis\AuthServisi;

final class AuthController
{
    public function __construct(private readonly AuthServisi $authServisi)
    {
    }

    /** POST /api/auth/giris  {"eposta": "...", "sifre": "..."} */
    public function giris(Istek $istek): Yanit
    {
        $eposta = $istek->govde('eposta');
        $sifre = $istek->govde('sifre');
        if (!is_string($eposta) || trim($eposta) === '' || !is_string($sifre) || $sifre === '') {
            throw new IsKuraliHatasi('E-posta ve şifre zorunludur.', 400);
        }
        if (mb_strlen($eposta) > 120 || strlen($sifre) > 72) {
            throw new IsKuraliHatasi('E-posta veya şifre hatalı.', 401);
        }

        $sonuc = $this->authServisi->girisYap(trim($eposta), $sifre);

        return Yanit::json(['kullanici' => $sonuc['kullanici']->diziye()])
            ->cerezYaz(OturumServisi::CEREZ_ADI, $sonuc['token']);
    }

    /** POST /api/auth/cikis */
    public function cikis(Istek $istek): Yanit
    {
        $this->authServisi->cikisYap($istek->cerez(OturumServisi::CEREZ_ADI));

        return Yanit::json(['mesaj' => 'Çıkış yapıldı.'])->cerezSil(OturumServisi::CEREZ_ADI);
    }

    /** GET /api/auth/ben – sayfalar açılırken oturumu buradan doğrular */
    public function ben(Istek $istek): Yanit
    {
        return Yanit::json(['kullanici' => $istek->kullanici()->diziye()]);
    }
}
