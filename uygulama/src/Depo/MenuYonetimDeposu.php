<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use PDO;
use PDOException;
use Throwable;
use Yemekhane\Ayar\Veritabani;
use Yemekhane\Hata\IsKuraliHatasi;

/**
 * Yönetici paneli: menü raporları (replika) ve menü oluşturma / düzenleme (primary).
 * Menü yazmalarının stored procedure'ü yok (para hareketi değil); bu yüzden menuler ve
 * menu_yemekleri birlikte, uygulamanın açtığı TEK transaction içinde yazılır.
 */
final class MenuYonetimDeposu
{
    /** MySQL: UNIQUE anahtar ihlali (uq_menu: yemekhane + tarih + öğün başına tek menü) */
    private const TEKRAR_EDEN_KAYIT = 1062;

    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /**
     * Rapor: menü bazında doluluk (v_menu_doluluk) ve ortalama puan (v_menu_puan). Raporlar → replika.
     *
     * @return list<array<string, mixed>>
     */
    public function menuRaporu(int $yemekhaneId, string $baslangic, string $bitis): array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            'SELECT d.menu_id, d.tarih, d.ogun_id, d.ogun, d.kapasite, d.rezerve, d.bos_yer, d.doluluk_yuzde,
                    p.oy_sayisi, p.ortalama_puan, m.ogrenci_fiyati, m.personel_fiyati,
                    (SELECT GROUP_CONCAT(md.yemek ORDER BY md.kategori_sira, md.yemek SEPARATOR \', \')
                       FROM v_menu_detay md WHERE md.menu_id = d.menu_id) AS yemekler,
                    d.tarih >= CURDATE() AS duzenlenebilir
               FROM v_menu_doluluk d
               JOIN v_menu_puan p ON p.menu_id = d.menu_id
               JOIN menuler m     ON m.id      = d.menu_id
              WHERE d.yemekhane_id = ? AND d.tarih BETWEEN ? AND ?
              ORDER BY d.tarih, d.ogun_id',
        );
        $sorgu->execute([$yemekhaneId, $baslangic, $bitis]);

        return $sorgu->fetchAll();
    }

    /**
     * Düzenleme formu için tek menü ve yemekleri (replika).
     *
     * @return array<string, mixed>|null
     */
    public function menu(int $menuId): ?array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            'SELECT id, yemekhane_id, tarih, ogun_id, ogrenci_fiyati, personel_fiyati,
                    tarih >= CURDATE() AS duzenlenebilir
               FROM menuler WHERE id = ?',
        );
        $sorgu->execute([$menuId]);
        $menu = $sorgu->fetch();
        if ($menu === false) {
            return null;
        }

        $yemekler = $this->veritabani->replicaDb()->prepare(
            'SELECT yemek_id FROM menu_yemekleri WHERE menu_id = ? ORDER BY yemek_id',
        );
        $yemekler->execute([$menuId]);
        $menu['yemek_idleri'] = array_map('intval', $yemekler->fetchAll(PDO::FETCH_COLUMN));

        return $menu;
    }

    /**
     * Menü formundaki seçilebilir yemekler, kategori sırasıyla (replika).
     *
     * @return list<array<string, mixed>>
     */
    public function yemekler(): array
    {
        return $this->veritabani->replicaDb()
            ->query(
                'SELECT y.id, y.ad, y.kalori, y.vejetaryen, k.ad AS kategori
                   FROM yemekler y
                   JOIN yemek_kategorileri k ON k.id = y.kategori_id
                  ORDER BY k.sira, y.ad',
            )
            ->fetchAll();
    }

    /**
     * Verilen yemek kimliklerinden kaç tanesi gerçekten var (geçersiz kimliği FK hatasından önce yakalamak için).
     *
     * @param list<int> $yemekIdleri
     */
    public function varOlanYemekSayisi(array $yemekIdleri): int
    {
        $yerTutucular = implode(', ', array_fill(0, count($yemekIdleri), '?'));
        $sorgu = $this->veritabani->replicaDb()->prepare(
            "SELECT COUNT(*) FROM yemekler WHERE id IN ({$yerTutucular})",
        );
        $sorgu->execute($yemekIdleri);

        return (int) $sorgu->fetchColumn();
    }

    /**
     * Yeni menü: menuler + menu_yemekleri tek transaction'da, primary'de.
     *
     * @param list<int> $yemekIdleri
     */
    public function menuOlustur(
        int $yemekhaneId,
        string $tarih,
        int $ogunId,
        string $ogrenciFiyati,
        string $personelFiyati,
        array $yemekIdleri,
    ): int {
        $baglanti = $this->veritabani->primaryDb();
        $baglanti->beginTransaction();
        try {
            $menu = $baglanti->prepare(
                'INSERT INTO menuler (yemekhane_id, tarih, ogun_id, ogrenci_fiyati, personel_fiyati)
                 VALUES (?, ?, ?, ?, ?)',
            );
            $menu->execute([$yemekhaneId, $tarih, $ogunId, $ogrenciFiyati, $personelFiyati]);
            $menuId = (int) $baglanti->lastInsertId();

            $this->yemekleriYaz($baglanti, $menuId, $yemekIdleri);
            $baglanti->commit();
        } catch (Throwable $hata) {
            $baglanti->rollBack();
            if ($hata instanceof PDOException && ($hata->errorInfo[1] ?? null) === self::TEKRAR_EDEN_KAYIT) {
                throw new IsKuraliHatasi('Bu yemekhane, tarih ve öğün için zaten bir menü var; onu düzenleyin.', 409);
            }
            throw $hata;
        }

        return $menuId;
    }

    /**
     * Menü düzenleme: fiyatlar ve yemek listesi. Yemekhane, tarih ve öğün değişmez (menünün kimliği
     * ve önbellek anahtarı bunlardır). Satır FOR UPDATE ile kilitlenir; geçmiş tarihli menü düzenlenmez.
     *
     * @param list<int> $yemekIdleri
     * @return array{yemekhane_id: int, tarih: string, ogun_id: int} düzenlenen menünün anahtarı
     */
    public function menuGuncelle(int $menuId, string $ogrenciFiyati, string $personelFiyati, array $yemekIdleri): array
    {
        $baglanti = $this->veritabani->primaryDb();
        $baglanti->beginTransaction();
        try {
            $kilit = $baglanti->prepare(
                'SELECT yemekhane_id, tarih, ogun_id, tarih >= CURDATE() AS duzenlenebilir
                   FROM menuler WHERE id = ? FOR UPDATE',
            );
            $kilit->execute([$menuId]);
            $menu = $kilit->fetch();
            if ($menu === false) {
                throw new IsKuraliHatasi('Menü bulunamadı.', 404);
            }
            if ((int) $menu['duzenlenebilir'] !== 1) {
                throw new IsKuraliHatasi('Geçmiş tarihli menüler düzenlenemez.', 422);
            }

            $fiyat = $baglanti->prepare('UPDATE menuler SET ogrenci_fiyati = ?, personel_fiyati = ? WHERE id = ?');
            $fiyat->execute([$ogrenciFiyati, $personelFiyati, $menuId]);

            $sil = $baglanti->prepare('DELETE FROM menu_yemekleri WHERE menu_id = ?');
            $sil->execute([$menuId]);
            $this->yemekleriYaz($baglanti, $menuId, $yemekIdleri);

            $baglanti->commit();
        } catch (Throwable $hata) {
            $baglanti->rollBack();
            throw $hata;
        }

        return [
            'yemekhane_id' => (int) $menu['yemekhane_id'],
            'tarih' => $menu['tarih'],
            'ogun_id' => (int) $menu['ogun_id'],
        ];
    }

    /** @param list<int> $yemekIdleri */
    private function yemekleriYaz(PDO $baglanti, int $menuId, array $yemekIdleri): void
    {
        $ekle = $baglanti->prepare('INSERT INTO menu_yemekleri (menu_id, yemek_id) VALUES (?, ?)');
        foreach ($yemekIdleri as $yemekId) {
            $ekle->execute([$menuId, $yemekId]);
        }
    }
}
