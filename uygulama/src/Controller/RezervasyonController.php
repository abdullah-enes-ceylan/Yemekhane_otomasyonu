<?php

declare(strict_types=1);

namespace Yemekhane\Controller;

use Yemekhane\Cekirdek\Girdi;
use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yanit;
use Yemekhane\Servis\RezervasyonServisi;

final class RezervasyonController
{
    public function __construct(private readonly RezervasyonServisi $rezervasyonServisi)
    {
    }

    /** GET /api/rezervasyonlar – oturumdaki kullanıcının rezervasyonları, duruma göre gruplu */
    public function listele(Istek $istek): Yanit
    {
        return Yanit::json($this->rezervasyonServisi->listele($istek->kullanici()));
    }

    /** POST /api/rezervasyonlar  { "menu_id": 12 } */
    public function yap(Istek $istek): Yanit
    {
        $menuId = Girdi::pozitifTamSayi($istek->govde('menu_id'), 'Menü');

        return Yanit::json($this->rezervasyonServisi->yap($istek->kullanici(), $menuId), 201);
    }

    /**
     * POST /api/rezervasyonlar/{id}/iptal
     * DELETE yerine POST: kayıt silinmez, durumu 'iptal' olur ve ücret iade edilir.
     */
    public function iptal(Istek $istek): Yanit
    {
        $rezervasyonId = Girdi::pozitifTamSayi($istek->yolParametresi('id'), 'Rezervasyon');

        return Yanit::json($this->rezervasyonServisi->iptalEt($istek->kullanici(), $rezervasyonId));
    }
}
