<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use PDO;
use Yemekhane\Ayar\Veritabani;

final class BakiyeDeposu
{
    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /**
     * Güncel bakiye PRIMARY'den (Kararlar #14): kullanıcı rezervasyondan hemen sonra bu sayfaya
     * geçerse replika bir an geride olabilir; para bilgisi her zaman taze gösterilir.
     */
    public function guncelBakiye(int $kullaniciId): string
    {
        $sorgu = $this->veritabani->primaryDb()->prepare('SELECT bakiye FROM kullanicilar WHERE id = ?');
        $sorgu->execute([$kullaniciId]);

        return (string) $sorgu->fetchColumn();
    }

    /**
     * Hareket geçmişi: listeleme olduğu için REPLİKADAN, en yeniden eskiye.
     *
     * @return list<array<string, mixed>>
     */
    public function hareketler(int $kullaniciId, int $enFazla): array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            'SELECT id, tur, tutar, aciklama, rezervasyon_id, olusturma
               FROM bakiye_hareketleri
              WHERE kullanici_id = ?
              ORDER BY olusturma DESC, id DESC
              LIMIT ?',
        );
        $sorgu->bindValue(1, $kullaniciId, PDO::PARAM_INT);
        $sorgu->bindValue(2, $enFazla, PDO::PARAM_INT);
        $sorgu->execute();

        return $sorgu->fetchAll();
    }

    /**
     * Para hareketi: sp_bakiye_yukle, primary'de. 10–1000 TL kuralı yordamda (45000 mesajı).
     *
     * @return array{hareket_id: int|string, bakiye: string}
     */
    public function bakiyeYukle(int $kullaniciId, string $tutar): array
    {
        $sorgu = $this->veritabani->primaryDb()->prepare('CALL sp_bakiye_yukle(?, ?)');

        return YordamSonucu::tekSatir($sorgu, [$kullaniciId, $tutar]);
    }
}
