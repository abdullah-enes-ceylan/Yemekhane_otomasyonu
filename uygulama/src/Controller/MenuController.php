<?php

declare(strict_types=1);

namespace Yemekhane\Controller;

use Yemekhane\Cekirdek\Girdi;
use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yanit;
use Yemekhane\Servis\MenuServisi;

final class MenuController
{
    public function __construct(private readonly MenuServisi $menuServisi)
    {
    }

    /** GET /api/menu/secenekler – yemekhane ve öğün listeleri (seçim kutuları için) */
    public function secenekler(Istek $istek): Yanit
    {
        return Yanit::json($this->menuServisi->secenekler());
    }

    /** GET /api/menu/gunluk?yemekhane_id=1&ogun_id=2&tarih=2026-11-15 (tarih verilmezse bugün) */
    public function gunluk(Istek $istek): Yanit
    {
        $yemekhaneId = Girdi::pozitifTamSayi($istek->sorgu('yemekhane_id'), 'Yemekhane');
        $ogunId = Girdi::pozitifTamSayi($istek->sorgu('ogun_id'), 'Öğün');
        $tarih = $istek->sorgu('tarih') === null ? Girdi::bugun() : Girdi::tarih($istek->sorgu('tarih'), 'Tarih');

        return Yanit::json([
            'menu' => $this->menuServisi->gunlukMenu($istek->kullanici(), $yemekhaneId, $tarih, $ogunId),
        ]);
    }

    /** GET /api/menu/haftalik?yemekhane_id=1&baslangic=2026-11-15 (başlangıç verilmezse bugün) */
    public function haftalik(Istek $istek): Yanit
    {
        $yemekhaneId = Girdi::pozitifTamSayi($istek->sorgu('yemekhane_id'), 'Yemekhane');
        $baslangic = $istek->sorgu('baslangic') === null
            ? Girdi::bugun()
            : Girdi::tarih($istek->sorgu('baslangic'), 'Başlangıç tarihi');

        return Yanit::json($this->menuServisi->haftalikMenu($istek->kullanici(), $yemekhaneId, $baslangic));
    }
}
