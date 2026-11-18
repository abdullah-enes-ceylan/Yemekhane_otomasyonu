<?php

declare(strict_types=1);

namespace Yemekhane\Controller;

use Yemekhane\Cekirdek\Girdi;
use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yanit;
use Yemekhane\Servis\DegerlendirmeServisi;

final class DegerlendirmeController
{
    public function __construct(private readonly DegerlendirmeServisi $degerlendirmeServisi)
    {
    }

    /** GET /api/degerlendirmeler – kullanılmış menüler ve verilmiş puanlar */
    public function listele(Istek $istek): Yanit
    {
        return Yanit::json($this->degerlendirmeServisi->listele($istek->kullanici()));
    }

    /** POST /api/degerlendirmeler  { "menu_id": 31, "puan": 4, "yorum": "..." } */
    public function ekle(Istek $istek): Yanit
    {
        $menuId = Girdi::pozitifTamSayi($istek->govde('menu_id'), 'Menü');
        $puan = Girdi::puan($istek->govde('puan'));
        $yorum = Girdi::istegeBagliMetin($istek->govde('yorum'), 'Yorum', DegerlendirmeServisi::yorumSiniri());

        return Yanit::json(
            $this->degerlendirmeServisi->degerlendir($istek->kullanici(), $menuId, $puan, $yorum),
            201,
        );
    }
}
