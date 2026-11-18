<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use Yemekhane\Ayar\Veritabani;

final class RezervasyonDeposu
{
    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /**
     * Kullanıcının verilen menülerdeki rezervasyon durumları: [menu_id => ['id' => .., 'durum' => ..]]
     *
     * @param list<int> $menuIdleri
     * @return array<int, array{id: int, durum: string}>
     */
    public function kullanicininMenuDurumlari(int $kullaniciId, array $menuIdleri): array
    {
        if ($menuIdleri === []) {
            return [];
        }

        $yerTutucular = implode(', ', array_fill(0, count($menuIdleri), '?'));
        $sorgu = $this->veritabani->replicaDb()->prepare(
            "SELECT menu_id, id, durum
               FROM rezervasyonlar
              WHERE kullanici_id = ? AND menu_id IN ({$yerTutucular})",
        );
        $sorgu->execute([$kullaniciId, ...$menuIdleri]);

        $durumlar = [];
        foreach ($sorgu->fetchAll() as $satir) {
            $durumlar[(int) $satir['menu_id']] = ['id' => (int) $satir['id'], 'durum' => $satir['durum']];
        }

        return $durumlar;
    }

    /**
     * Rezervasyonlarım ekranı: listeleme olduğu için replikadan.
     * iptal_edilebilir, sp_rezervasyon_iptal'in kontrolüyle birebir aynı koşuldur
     * (durum 'aktif' ve NOW() < TIMESTAMP(tarih, son_saat)); saat karşılaştırması veritabanında yapılır.
     *
     * @return list<array<string, mixed>>
     */
    public function kullanicininRezervasyonlari(int $kullaniciId): array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            "SELECT r.id, r.menu_id, r.durum, r.ucret, r.olusturma, r.kullanma_zamani, r.iptal_zamani,
                    m.tarih, o.ad AS ogun, yh.ad AS yemekhane,
                    TIMESTAMP(m.tarih, o.son_saat) AS rezervasyon_son,
                    (r.durum = 'aktif' AND NOW() < TIMESTAMP(m.tarih, o.son_saat)) AS iptal_edilebilir
               FROM rezervasyonlar r
               JOIN menuler m       ON m.id  = r.menu_id
               JOIN ogunler o       ON o.id  = m.ogun_id
               JOIN yemekhaneler yh ON yh.id = m.yemekhane_id
              WHERE r.kullanici_id = ?
              ORDER BY m.tarih DESC, o.sira DESC",
        );
        $sorgu->execute([$kullaniciId]);

        return $sorgu->fetchAll();
    }

    /**
     * Para hareketi: sp_rezervasyon_yap, primary'de. Dönen bakiye primary'deki taze değerdir;
     * rezervasyondan hemen sonra ekranda bu gösterilir (replika bir an geride olabilir).
     *
     * @return array{rezervasyon_id: int|string, ucret: string, bakiye: string}
     */
    public function rezervasyonYap(int $kullaniciId, int $menuId): array
    {
        $sorgu = $this->veritabani->primaryDb()->prepare('CALL sp_rezervasyon_yap(?, ?)');

        return YordamSonucu::tekSatir($sorgu, [$kullaniciId, $menuId]);
    }

    /**
     * Para hareketi: sp_rezervasyon_iptal, primary'de. Başkasının rezervasyonunu yordam "bulunamadı" sayar.
     *
     * @return array{rezervasyon_id: int|string, iade: string, bakiye: string}
     */
    public function rezervasyonIptal(int $kullaniciId, int $rezervasyonId): array
    {
        $sorgu = $this->veritabani->primaryDb()->prepare('CALL sp_rezervasyon_iptal(?, ?)');

        return YordamSonucu::tekSatir($sorgu, [$kullaniciId, $rezervasyonId]);
    }

    /**
     * Turnike geçişi: sp_turnike_gecis, primary'de. Menüyü yordam kendisi bulur (tarih + öğünün
     * servis aralığı). $zaman null ise yordam NOW() kullanır; test için belirli bir an verilebilir.
     *
     * @return array{rezervasyon_id: int|string, menu_id: int|string, ogun: string}
     */
    public function turnikeGecisi(int $kullaniciId, int $yemekhaneId, ?string $zaman): array
    {
        $sorgu = $this->veritabani->primaryDb()->prepare('CALL sp_turnike_gecis(?, ?, ?)');

        return YordamSonucu::tekSatir($sorgu, [$kullaniciId, $yemekhaneId, $zaman]);
    }
}
