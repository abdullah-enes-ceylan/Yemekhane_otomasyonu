<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use Yemekhane\Ayar\Veritabani;

/**
 * "Kendi yazdığını oku" (read-your-writes) için replikasyon beklemesi.
 *
 * Sorun (ölçüldü, rapor/asama-2/03_replikasyon_gecikmesi.txt): replikasyon asenkron olduğu için
 * primary'ye yazıp hemen ardından replikadan okuyan istek birkaç milisaniye eski veriyi görür.
 * Kullanıcı iptal ettiği rezervasyonu listede hâlâ "aktif" görüyordu.
 *
 * Çözüm: para hareketi yapan yazmadan sonra primary'nin uyguladığı GTID kümesini alıp replikada
 * bu kümenin uygulanmasını en çok BEKLEME_SANIYE kadar bekleriz. Yanıt döndüğünde arayüzün yapacağı
 * liste isteği (replikadan) yazmayı görür. Okumalar yine replikada kalır.
 */
final class ReplikasyonDeposu
{
    private const BEKLEME_SANIYE = 1;

    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /** true: replika yetişti; false: süre doldu (yazma geçerli, yalnızca liste bir an eski görünebilir) */
    public function replikaninYetismesiniBekle(): bool
    {
        // Primary'de o ana kadar uygulanmış tüm işlemlerin GTID kümesi (az önceki yazma dahil)
        $gtidKumesi = $this->veritabani->primaryDb()
            ->query('SELECT @@GLOBAL.gtid_executed')
            ->fetchColumn();

        // Replika bu kümeyi uygulayana kadar bekler; 0 = uygulandı, 1 = süre doldu
        $sorgu = $this->veritabani->replicaDb()->prepare('SELECT WAIT_FOR_EXECUTED_GTID_SET(?, ?)');
        $sorgu->execute([$gtidKumesi, self::BEKLEME_SANIYE]);

        return (int) $sorgu->fetchColumn() === 0;
    }
}
