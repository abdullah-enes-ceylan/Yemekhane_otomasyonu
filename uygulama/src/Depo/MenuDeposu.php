<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use Yemekhane\Ayar\Veritabani;

/**
 * Menü okumaları. Hepsi listeleme olduğu için replikadan yapılır.
 */
final class MenuDeposu
{
    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /** @return list<array<string, mixed>> */
    public function yemekhaneler(): array
    {
        return $this->veritabani->replicaDb()
            ->query('SELECT id, ad, konum, kapasite FROM yemekhaneler ORDER BY id')
            ->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function ogunler(): array
    {
        return $this->veritabani->replicaDb()
            ->query('SELECT id, ad, sira, son_saat FROM ogunler ORDER BY sira')
            ->fetchAll();
    }

    /**
     * Bir menünün yemekleri, kategori sırasıyla (v_menu_detay: her yemek bir satır).
     * Menü yoksa boş liste.
     *
     * @return list<array<string, mixed>>
     */
    public function menuSatirlari(int $yemekhaneId, string $tarih, int $ogunId): array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            'SELECT menu_id, tarih, yemekhane_id, yemekhane, ogun_id, ogun,
                    ogrenci_fiyati, personel_fiyati,
                    yemek_id, yemek, kategori, kalori, vejetaryen, alerjenler, alerjen_idler
               FROM v_menu_detay
              WHERE yemekhane_id = ? AND tarih = ? AND ogun_id = ?
              ORDER BY kategori_sira, yemek',
        );
        $sorgu->execute([$yemekhaneId, $tarih, $ogunId]);

        return $sorgu->fetchAll();
    }

    /**
     * Tarih aralığındaki menülerin anlık doluluğu. Son saat karşılaştırması
     * veritabanının saatiyle (NOW()) yapılır.
     *
     * @return list<array<string, mixed>>
     */
    public function dolulukDurumlari(int $yemekhaneId, string $baslangic, string $bitis): array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            'SELECT menu_id, tarih, ogun_id, kapasite, bos_yer, rezervasyon_son,
                    rezervasyon_son > NOW() AS sure_devam_ediyor
               FROM v_menu_doluluk
              WHERE yemekhane_id = ? AND tarih BETWEEN ? AND ?
              ORDER BY tarih, ogun_id',
        );
        $sorgu->execute([$yemekhaneId, $baslangic, $bitis]);

        return $sorgu->fetchAll();
    }
}
