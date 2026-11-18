<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use DateTimeImmutable;
use Yemekhane\Depo\MenuDeposu;
use Yemekhane\Depo\RezervasyonDeposu;
use Yemekhane\Guvenlik\OturumKullanicisi;

/**
 * Bir menü iki parçadan oluşur:
 *  1. İçerik (yemekler, kaloriler, alerjenler, iki fiyat): yönetici değiştirmedikçe aynıdır → önbelleğe alınabilir.
 *  2. Anlık ve kişiye özel alanlar (rolüne göre fiyat, boş yer, son saat, kendi rezervasyonu): her istekte hesaplanır.
 */
final class MenuServisi
{
    private const HAFTA_GUN_SAYISI = 7;

    public function __construct(
        private readonly MenuDeposu $menuDeposu,
        private readonly RezervasyonDeposu $rezervasyonDeposu,
        private readonly MenuOnbellegi $menuOnbellegi,
    ) {
    }

    /** @return array{yemekhaneler: list<array<string, mixed>>, ogunler: list<array<string, mixed>>} */
    public function secenekler(): array
    {
        return [
            'yemekhaneler' => array_map(static fn (array $y): array => [
                'id' => (int) $y['id'],
                'ad' => $y['ad'],
                'konum' => $y['konum'],
                'kapasite' => (int) $y['kapasite'],
            ], $this->menuDeposu->yemekhaneler()),
            'ogunler' => array_map(static fn (array $o): array => [
                'id' => (int) $o['id'],
                'ad' => $o['ad'],
                'sonSaat' => substr($o['son_saat'], 0, 5),
            ], $this->menuDeposu->ogunler()),
        ];
    }

    /** @return array<string, mixed>|null */
    public function gunlukMenu(OturumKullanicisi $kullanici, int $yemekhaneId, DateTimeImmutable $tarih, int $ogunId): ?array
    {
        $gun = $tarih->format('Y-m-d');
        $icerik = $this->menuIcerigi($yemekhaneId, $gun, $ogunId);
        if ($icerik === null) {
            return null;
        }

        $doluluk = null;
        foreach ($this->menuDeposu->dolulukDurumlari($yemekhaneId, $gun, $gun) as $satir) {
            if ((int) $satir['menu_id'] === $icerik['id']) {
                $doluluk = $satir;
            }
        }
        $rezervasyonlar = $this->rezervasyonDeposu->kullanicininMenuDurumlari($kullanici->id, [$icerik['id']]);

        return $this->kullaniciIcinTamamla($icerik, $doluluk, $rezervasyonlar[$icerik['id']] ?? null, $kullanici);
    }

    /** @return array<string, mixed> */
    public function haftalikMenu(OturumKullanicisi $kullanici, int $yemekhaneId, DateTimeImmutable $baslangic): array
    {
        $bitis = $baslangic->modify('+' . (self::HAFTA_GUN_SAYISI - 1) . ' days');
        $doluluklar = $this->menuDeposu->dolulukDurumlari($yemekhaneId, $baslangic->format('Y-m-d'), $bitis->format('Y-m-d'));
        $rezervasyonlar = $this->rezervasyonDeposu->kullanicininMenuDurumlari(
            $kullanici->id,
            array_map(static fn (array $d): int => (int) $d['menu_id'], $doluluklar),
        );

        $gunler = [];
        for ($i = 0; $i < self::HAFTA_GUN_SAYISI; $i++) {
            $gun = $baslangic->modify("+{$i} days")->format('Y-m-d');
            $gunler[$gun] = ['tarih' => $gun, 'menuler' => []];
        }

        foreach ($doluluklar as $doluluk) {
            $icerik = $this->menuIcerigi($yemekhaneId, $doluluk['tarih'], (int) $doluluk['ogun_id']);
            if ($icerik === null) {
                continue;
            }
            $gunler[$doluluk['tarih']]['menuler'][] = $this->kullaniciIcinTamamla(
                $icerik,
                $doluluk,
                $rezervasyonlar[$icerik['id']] ?? null,
                $kullanici,
            );
        }

        return [
            'baslangic' => $baslangic->format('Y-m-d'),
            'bitis' => $bitis->format('Y-m-d'),
            'oncekiBaslangic' => $baslangic->modify('-' . self::HAFTA_GUN_SAYISI . ' days')->format('Y-m-d'),
            'sonrakiBaslangic' => $baslangic->modify('+' . self::HAFTA_GUN_SAYISI . ' days')->format('Y-m-d'),
            'gunler' => array_values($gunler),
        ];
    }

    /**
     * Menünün değişmeyen içeriği; menu:{yemekhane_id}:{tarih}:{ogun_id} anahtarıyla önbelleğe giren
     * kısım budur ve kişiye özel hiçbir şey içermez. Önce Redis'e bakılır (HIT); yoksa (MISS)
     * replikadan okunup önbelleğe yazılır. "Menü yok" sonucu önbelleğe alınmaz.
     *
     * @return array<string, mixed>|null
     */
    public function menuIcerigi(int $yemekhaneId, string $tarih, int $ogunId): ?array
    {
        $onbellekte = $this->menuOnbellegi->oku($yemekhaneId, $tarih, $ogunId);
        if ($onbellekte !== null) {
            return $onbellekte;
        }

        $icerik = $this->veritabanindanMenuIcerigi($yemekhaneId, $tarih, $ogunId);
        if ($icerik !== null) {
            $this->menuOnbellegi->yaz($yemekhaneId, $tarih, $ogunId, $icerik);
        }

        return $icerik;
    }

    /** @return array<string, mixed>|null */
    private function veritabanindanMenuIcerigi(int $yemekhaneId, string $tarih, int $ogunId): ?array
    {
        $satirlar = $this->menuDeposu->menuSatirlari($yemekhaneId, $tarih, $ogunId);
        if ($satirlar === []) {
            return null;
        }

        $ilk = $satirlar[0];
        $yemekler = array_map(static fn (array $s): array => [
            'id' => (int) $s['yemek_id'],
            'ad' => $s['yemek'],
            'kategori' => $s['kategori'],
            'kalori' => (int) $s['kalori'],
            'vejetaryen' => (int) $s['vejetaryen'] === 1,
            'alerjenler' => $s['alerjenler'] === null ? [] : explode(', ', $s['alerjenler']),
            'alerjenIdleri' => $s['alerjen_idler'] === null ? [] : array_map('intval', explode(',', $s['alerjen_idler'])),
        ], $satirlar);

        return [
            'id' => (int) $ilk['menu_id'],
            'tarih' => $ilk['tarih'],
            'yemekhane' => ['id' => (int) $ilk['yemekhane_id'], 'ad' => $ilk['yemekhane']],
            'ogun' => ['id' => (int) $ilk['ogun_id'], 'ad' => $ilk['ogun']],
            'ogrenciFiyati' => $ilk['ogrenci_fiyati'],
            'personelFiyati' => $ilk['personel_fiyati'],
            'yemekler' => $yemekler,
            'toplamKalori' => array_sum(array_column($yemekler, 'kalori')),
        ];
    }

    /**
     * @param array<string, mixed>                 $icerik
     * @param array<string, mixed>|null            $doluluk
     * @param array{id: int, durum: string}|null   $rezervasyon
     * @return array<string, mixed>
     */
    private function kullaniciIcinTamamla(array $icerik, ?array $doluluk, ?array $rezervasyon, OturumKullanicisi $kullanici): array
    {
        // sp_rezervasyon_yap ile aynı kural: öğrenci öğrenci fiyatı, personel ve yönetici personel fiyatı öder
        $fiyat = $kullanici->rol === OturumKullanicisi::ROL_OGRENCI ? $icerik['ogrenciFiyati'] : $icerik['personelFiyati'];
        $bosYer = (int) ($doluluk['bos_yer'] ?? 0);
        $sureDevamEdiyor = (int) ($doluluk['sure_devam_ediyor'] ?? 0) === 1;
        $rezervasyonuVar = $rezervasyon !== null && in_array($rezervasyon['durum'], ['aktif', 'kullanildi'], true);

        $kapaliNedeni = match (true) {
            $rezervasyonuVar => 'Bu öğün için rezervasyonunuz var.',
            !$sureDevamEdiyor => 'Rezervasyon süresi doldu.',
            $bosYer <= 0 => 'Kapasite dolu.',
            default => null,
        };

        unset($icerik['ogrenciFiyati'], $icerik['personelFiyati']);

        return $icerik + [
            'fiyat' => $fiyat,
            'kapasite' => (int) ($doluluk['kapasite'] ?? 0),
            'bosYer' => $bosYer,
            'rezervasyonSon' => $doluluk['rezervasyon_son'] ?? null,
            'rezervasyonaAcik' => $kapaliNedeni === null,
            'kapaliNedeni' => $kapaliNedeni,
            'rezervasyonum' => $rezervasyon,
        ];
    }
}
