<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use DateTimeImmutable;
use Yemekhane\Cekirdek\Girdi;
use Yemekhane\Depo\KullaniciDeposu;
use Yemekhane\Depo\MenuDeposu;
use Yemekhane\Depo\MenuYonetimDeposu;
use Yemekhane\Depo\ReplikasyonDeposu;
use Yemekhane\Depo\RezervasyonDeposu;
use Yemekhane\Hata\IsKuraliHatasi;

/**
 * Yönetici paneli: menü raporu (doluluk + ortalama puan), menü oluşturma/düzenleme, turnike.
 * Yetki kontrolü burada değil, OturumFiltresi'nde: /api/admin/ ile başlayan her yol yönetici ister.
 */
final class YonetimServisi
{
    private const HAFTA_GUN_SAYISI = 7;

    public function __construct(
        private readonly MenuYonetimDeposu $menuYonetimDeposu,
        private readonly MenuDeposu $menuDeposu,
        private readonly KullaniciDeposu $kullaniciDeposu,
        private readonly RezervasyonDeposu $rezervasyonDeposu,
        private readonly ReplikasyonDeposu $replikasyonDeposu,
    ) {
    }

    /** @return array<string, mixed> */
    public function menuRaporu(int $yemekhaneId, DateTimeImmutable $baslangic): array
    {
        $bitis = $baslangic->modify('+' . (self::HAFTA_GUN_SAYISI - 1) . ' days');
        $satirlar = $this->menuYonetimDeposu->menuRaporu($yemekhaneId, $baslangic->format('Y-m-d'), $bitis->format('Y-m-d'));

        return [
            'baslangic' => $baslangic->format('Y-m-d'),
            'bitis' => $bitis->format('Y-m-d'),
            'oncekiBaslangic' => $baslangic->modify('-' . self::HAFTA_GUN_SAYISI . ' days')->format('Y-m-d'),
            'sonrakiBaslangic' => $baslangic->modify('+' . self::HAFTA_GUN_SAYISI . ' days')->format('Y-m-d'),
            'menuler' => array_map(static fn (array $s): array => [
                'id' => (int) $s['menu_id'],
                'tarih' => $s['tarih'],
                'ogun' => ['id' => (int) $s['ogun_id'], 'ad' => $s['ogun']],
                'yemekler' => $s['yemekler'],
                'ogrenciFiyati' => $s['ogrenci_fiyati'],
                'personelFiyati' => $s['personel_fiyati'],
                'kapasite' => (int) $s['kapasite'],
                'rezerve' => (int) $s['rezerve'],
                'bosYer' => (int) $s['bos_yer'],
                'dolulukYuzde' => $s['doluluk_yuzde'],
                'oySayisi' => (int) $s['oy_sayisi'],
                'ortalamaPuan' => $s['ortalama_puan'],
                'duzenlenebilir' => (int) $s['duzenlenebilir'] === 1,
            ], $satirlar),
        ];
    }

    /** @return array<string, mixed> */
    public function menu(int $menuId): array
    {
        $menu = $this->menuYonetimDeposu->menu($menuId) ?? throw new IsKuraliHatasi('Menü bulunamadı.', 404);

        return [
            'id' => (int) $menu['id'],
            'yemekhaneId' => (int) $menu['yemekhane_id'],
            'tarih' => $menu['tarih'],
            'ogunId' => (int) $menu['ogun_id'],
            'ogrenciFiyati' => $menu['ogrenci_fiyati'],
            'personelFiyati' => $menu['personel_fiyati'],
            'yemekIdleri' => $menu['yemek_idleri'],
            'duzenlenebilir' => (int) $menu['duzenlenebilir'] === 1,
        ];
    }

    /** Menü formu için yemekler, kategoriye göre gruplu (sıra veritabanından) */
    public function yemekKategorileri(): array
    {
        $kategoriler = [];
        foreach ($this->menuYonetimDeposu->yemekler() as $yemek) {
            $kategoriler[$yemek['kategori']]['ad'] = $yemek['kategori'];
            $kategoriler[$yemek['kategori']]['yemekler'][] = [
                'id' => (int) $yemek['id'],
                'ad' => $yemek['ad'],
                'kalori' => (int) $yemek['kalori'],
                'vejetaryen' => (int) $yemek['vejetaryen'] === 1,
            ];
        }

        return ['kategoriler' => array_values($kategoriler)];
    }

    /**
     * @param list<int> $yemekIdleri
     * @return array{menuId: int}
     */
    public function menuOlustur(
        int $yemekhaneId,
        DateTimeImmutable $tarih,
        int $ogunId,
        string $ogrenciFiyati,
        string $personelFiyati,
        array $yemekIdleri,
    ): array {
        if ($tarih < Girdi::bugun()) {
            throw new IsKuraliHatasi('Geçmiş bir tarih için menü oluşturulamaz.', 422);
        }
        $this->yemekhaneVeOgunVarMi($yemekhaneId, $ogunId);
        $this->yemeklerVarMi($yemekIdleri);

        $menuId = $this->menuYonetimDeposu->menuOlustur(
            $yemekhaneId,
            $tarih->format('Y-m-d'),
            $ogunId,
            $ogrenciFiyati,
            $personelFiyati,
            $yemekIdleri,
        );
        $this->replikayiBekle('menü oluşturma');

        return ['menuId' => $menuId];
    }

    /**
     * @param list<int> $yemekIdleri
     * @return array{menuId: int}
     */
    public function menuGuncelle(int $menuId, string $ogrenciFiyati, string $personelFiyati, array $yemekIdleri): array
    {
        $this->yemeklerVarMi($yemekIdleri);
        $this->menuYonetimDeposu->menuGuncelle($menuId, $ogrenciFiyati, $personelFiyati, $yemekIdleri);
        $this->replikayiBekle('menü düzenleme');

        return ['menuId' => $menuId];
    }

    /** @return array<string, mixed> */
    public function turnikeGecisi(string $kullaniciKimligi, int $yemekhaneId, ?string $zaman): array
    {
        $kullanici = $this->kullaniciDeposu->epostaYaDaOgrenciNoIleBul($kullaniciKimligi)
            ?? throw new IsKuraliHatasi('Bu e-posta ya da öğrenci numarasıyla kullanıcı bulunamadı.', 404);

        $sonuc = $this->rezervasyonDeposu->turnikeGecisi((int) $kullanici['id'], $yemekhaneId, $zaman);
        $this->replikayiBekle('turnike geçişi');

        return [
            'kullanici' => $kullanici['ad'] . ' ' . $kullanici['soyad'],
            'rezervasyonId' => (int) $sonuc['rezervasyon_id'],
            'menuId' => (int) $sonuc['menu_id'],
            'ogun' => $sonuc['ogun'],
        ];
    }

    private function yemekhaneVeOgunVarMi(int $yemekhaneId, int $ogunId): void
    {
        $yemekhaneIdleri = array_map(static fn (array $y): int => (int) $y['id'], $this->menuDeposu->yemekhaneler());
        $ogunIdleri = array_map(static fn (array $o): int => (int) $o['id'], $this->menuDeposu->ogunler());
        if (!in_array($yemekhaneId, $yemekhaneIdleri, true) || !in_array($ogunId, $ogunIdleri, true)) {
            throw new IsKuraliHatasi('Yemekhane ya da öğün bulunamadı.', 400);
        }
    }

    /** @param list<int> $yemekIdleri */
    private function yemeklerVarMi(array $yemekIdleri): void
    {
        if ($this->menuYonetimDeposu->varOlanYemekSayisi($yemekIdleri) !== count($yemekIdleri)) {
            throw new IsKuraliHatasi('Seçilen yemeklerden biri bulunamadı.', 400);
        }
    }

    private function replikayiBekle(string $islem): void
    {
        if (!$this->replikasyonDeposu->replikaninYetismesiniBekle()) {
            error_log("[UYARI] Replika {$islem} sonrasında zamanında yetişmedi; liste bir an eski görünebilir.");
        }
    }
}
