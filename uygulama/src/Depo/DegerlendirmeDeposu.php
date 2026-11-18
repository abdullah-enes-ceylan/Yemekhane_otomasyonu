<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use PDOException;
use Yemekhane\Ayar\Veritabani;
use Yemekhane\Hata\IsKuraliHatasi;

final class DegerlendirmeDeposu
{
    /** MySQL: UNIQUE anahtar ihlali (uq_degerlendirme: kişi başına menü başına tek oy) */
    private const TEKRAR_EDEN_KAYIT = 1062;

    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /**
     * Değerlendirme ekranı: kullanıcının KULLANDIĞI menüler ve varsa verdiği puan. Listeleme → replika.
     *
     * @return list<array<string, mixed>>
     */
    public function kullanilmisMenuler(int $kullaniciId): array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            "SELECT r.menu_id, m.tarih, o.ad AS ogun, yh.ad AS yemekhane, r.kullanma_zamani,
                    (SELECT GROUP_CONCAT(md.yemek ORDER BY md.kategori_sira, md.yemek SEPARATOR ', ')
                       FROM v_menu_detay md WHERE md.menu_id = r.menu_id) AS yemekler,
                    d.puan, d.yorum, d.olusturma AS degerlendirme_zamani
               FROM rezervasyonlar r
               JOIN menuler m       ON m.id  = r.menu_id
               JOIN ogunler o       ON o.id  = m.ogun_id
               JOIN yemekhaneler yh ON yh.id = m.yemekhane_id
               LEFT JOIN degerlendirmeler d ON d.menu_id = r.menu_id AND d.kullanici_id = r.kullanici_id
              WHERE r.kullanici_id = ? AND r.durum = 'kullanildi'
              ORDER BY m.tarih DESC, o.sira DESC",
        );
        $sorgu->execute([$kullaniciId]);

        return $sorgu->fetchAll();
    }

    /**
     * "Yalnızca kullanılmış menü değerlendirilir" kuralının kontrolü (şemada yok, uygulamanın).
     * Yazmadan hemen önceki kontrol olduğu için PRIMARY'den: turnike geçişi henüz replikaya
     * ulaşmamış olsa bile doğru sonuç verir.
     */
    public function menuyuKullandiMi(int $kullaniciId, int $menuId): bool
    {
        $sorgu = $this->veritabani->primaryDb()->prepare(
            "SELECT 1 FROM rezervasyonlar WHERE kullanici_id = ? AND menu_id = ? AND durum = 'kullanildi'",
        );
        $sorgu->execute([$kullaniciId, $menuId]);

        return $sorgu->fetchColumn() !== false;
    }

    /** Yazma → primary. Para hareketi olmadığı için yordamı yok; düz INSERT (hazırlanmış ifade). */
    public function ekle(int $kullaniciId, int $menuId, int $puan, ?string $yorum): int
    {
        $baglanti = $this->veritabani->primaryDb();
        $sorgu = $baglanti->prepare(
            'INSERT INTO degerlendirmeler (kullanici_id, menu_id, puan, yorum) VALUES (?, ?, ?, ?)',
        );

        try {
            $sorgu->execute([$kullaniciId, $menuId, $puan, $yorum]);
        } catch (PDOException $hata) {
            if (($hata->errorInfo[1] ?? null) === self::TEKRAR_EDEN_KAYIT) {
                throw new IsKuraliHatasi('Bu menüyü zaten değerlendirdiniz.', 409);
            }
            throw $hata;
        }

        return (int) $baglanti->lastInsertId();
    }
}
