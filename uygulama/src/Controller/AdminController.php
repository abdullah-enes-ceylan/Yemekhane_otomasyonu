<?php

declare(strict_types=1);

namespace Yemekhane\Controller;

use Yemekhane\Cekirdek\Girdi;
use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yanit;
use Yemekhane\Hata\IsKuraliHatasi;
use Yemekhane\Servis\YonetimServisi;

/**
 * Yönetici paneli uç noktaları. Hepsi /api/admin/ ile başlar; OturumFiltresi yönetici olmayanı 403 ile durdurur.
 */
final class AdminController
{
    public function __construct(private readonly YonetimServisi $yonetimServisi)
    {
    }

    /** GET /api/admin/menuler?yemekhane_id=1&baslangic=2026-11-18 – menü bazında doluluk ve ortalama puan */
    public function menuRaporu(Istek $istek): Yanit
    {
        $yemekhaneId = Girdi::pozitifTamSayi($istek->sorgu('yemekhane_id'), 'Yemekhane');
        $baslangic = $istek->sorgu('baslangic') === null
            ? Girdi::bugun()
            : Girdi::tarih($istek->sorgu('baslangic'), 'Başlangıç tarihi');

        return Yanit::json($this->yonetimServisi->menuRaporu($yemekhaneId, $baslangic));
    }

    /** GET /api/admin/menuler/{id} – düzenleme formu için */
    public function menu(Istek $istek): Yanit
    {
        $menuId = Girdi::pozitifTamSayi($istek->yolParametresi('id'), 'Menü');

        return Yanit::json(['menu' => $this->yonetimServisi->menu($menuId)]);
    }

    /** GET /api/admin/yemekler – menü formundaki seçilebilir yemekler */
    public function yemekler(Istek $istek): Yanit
    {
        return Yanit::json($this->yonetimServisi->yemekKategorileri());
    }

    /** POST /api/admin/menuler { yemekhane_id, tarih, ogun_id, ogrenci_fiyati, personel_fiyati, yemek_idleri[] } */
    public function menuOlustur(Istek $istek): Yanit
    {
        return Yanit::json($this->yonetimServisi->menuOlustur(
            Girdi::pozitifTamSayi($istek->govde('yemekhane_id'), 'Yemekhane'),
            Girdi::tarih($istek->govde('tarih'), 'Tarih'),
            Girdi::pozitifTamSayi($istek->govde('ogun_id'), 'Öğün'),
            Girdi::tutar($istek->govde('ogrenci_fiyati'), 'Öğrenci fiyatı'),
            Girdi::tutar($istek->govde('personel_fiyati'), 'Personel fiyatı'),
            Girdi::kimlikListesi($istek->govde('yemek_idleri'), 'Yemekler'),
        ), 201);
    }

    /** PUT /api/admin/menuler/{id} { ogrenci_fiyati, personel_fiyati, yemek_idleri[] } */
    public function menuGuncelle(Istek $istek): Yanit
    {
        return Yanit::json($this->yonetimServisi->menuGuncelle(
            Girdi::pozitifTamSayi($istek->yolParametresi('id'), 'Menü'),
            Girdi::tutar($istek->govde('ogrenci_fiyati'), 'Öğrenci fiyatı'),
            Girdi::tutar($istek->govde('personel_fiyati'), 'Personel fiyatı'),
            Girdi::kimlikListesi($istek->govde('yemek_idleri'), 'Yemekler'),
        ));
    }

    /** POST /api/admin/turnike { kullanici: e-posta ya da öğrenci no, yemekhane_id, zaman?: "2026-11-19T12:15" } */
    public function turnike(Istek $istek): Yanit
    {
        $kullanici = $istek->govde('kullanici');
        if (!is_string($kullanici) || trim($kullanici) === '') {
            throw new IsKuraliHatasi('Kullanıcının e-postası ya da öğrenci numarası zorunludur.', 400);
        }
        $zaman = $istek->govde('zaman');
        $zaman = $zaman === null || $zaman === '' ? null : Girdi::tarihSaat($zaman, 'Zaman');

        return Yanit::json($this->yonetimServisi->turnikeGecisi(
            trim($kullanici),
            Girdi::pozitifTamSayi($istek->govde('yemekhane_id'), 'Yemekhane'),
            $zaman,
        ));
    }
}
