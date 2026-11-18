<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use Yemekhane\Depo\ReplikasyonDeposu;
use Yemekhane\Depo\RezervasyonDeposu;
use Yemekhane\Guvenlik\OturumKullanicisi;

/**
 * Rezervasyon akışı. Süre, kapasite, bakiye gibi kurallar sp_rezervasyon_yap / sp_rezervasyon_iptal
 * içinde; burada tekrar edilmez. İhlalde yordamın Türkçe mesajı (45000) olduğu gibi arayüze gider.
 * Kullanıcı kimliği her zaman oturumdan gelir, istek gövdesinden asla.
 */
final class RezervasyonServisi
{
    /** Rezervasyonlarım ekranındaki grupların sırası ve başlıkları */
    private const GRUPLAR = [
        'aktif' => 'Aktif',
        'kullanildi' => 'Kullanıldı',
        'iptal' => 'İptal edildi',
    ];

    public function __construct(
        private readonly RezervasyonDeposu $rezervasyonDeposu,
        private readonly ReplikasyonDeposu $replikasyonDeposu,
    ) {
    }

    /** @return array{rezervasyonId: int, ucret: string, bakiye: string} */
    public function yap(OturumKullanicisi $kullanici, int $menuId): array
    {
        $sonuc = $this->rezervasyonDeposu->rezervasyonYap($kullanici->id, $menuId);
        $this->replikayiBekle('rezervasyon');

        return [
            'rezervasyonId' => (int) $sonuc['rezervasyon_id'],
            'ucret' => $sonuc['ucret'],
            'bakiye' => $sonuc['bakiye'],
        ];
    }

    /** @return array{rezervasyonId: int, iade: string, bakiye: string} */
    public function iptalEt(OturumKullanicisi $kullanici, int $rezervasyonId): array
    {
        $sonuc = $this->rezervasyonDeposu->rezervasyonIptal($kullanici->id, $rezervasyonId);
        $this->replikayiBekle('iptal');

        return [
            'rezervasyonId' => (int) $sonuc['rezervasyon_id'],
            'iade' => $sonuc['iade'],
            'bakiye' => $sonuc['bakiye'],
        ];
    }

    /**
     * Rezervasyonlarım: aktif / kullanıldı / iptal grupları. Gruplama ve "iptal edilebilir mi"
     * API'de yapılır; arayüz yalnızca çizer.
     *
     * @return array{gruplar: list<array{durum: string, baslik: string, rezervasyonlar: list<array<string, mixed>>}>}
     */
    public function listele(OturumKullanicisi $kullanici): array
    {
        $gruplar = [];
        foreach (self::GRUPLAR as $durum => $baslik) {
            $gruplar[$durum] = ['durum' => $durum, 'baslik' => $baslik, 'rezervasyonlar' => []];
        }

        foreach ($this->rezervasyonDeposu->kullanicininRezervasyonlari($kullanici->id) as $satir) {
            $gruplar[$satir['durum']]['rezervasyonlar'][] = [
                'id' => (int) $satir['id'],
                'menuId' => (int) $satir['menu_id'],
                'tarih' => $satir['tarih'],
                'ogun' => $satir['ogun'],
                'yemekhane' => $satir['yemekhane'],
                'ucret' => $satir['ucret'],
                'durum' => $satir['durum'],
                'rezervasyonSon' => $satir['rezervasyon_son'],
                'olusturma' => $satir['olusturma'],
                'kullanmaZamani' => $satir['kullanma_zamani'],
                'iptalZamani' => $satir['iptal_zamani'],
                'iptalEdilebilir' => (int) $satir['iptal_edilebilir'] === 1,
            ];
        }

        return ['gruplar' => array_values($gruplar)];
    }

    /**
     * Yazma primary'de tamamlandı; arayüz hemen ardından listeyi replikadan isteyecek.
     * Replika yetişmezse yazma yine geçerlidir, yalnızca uyarı günlüğe düşer.
     */
    private function replikayiBekle(string $islem): void
    {
        if (!$this->replikasyonDeposu->replikaninYetismesiniBekle()) {
            error_log("[UYARI] Replika {$islem} sonrasında zamanında yetişmedi; liste bir an eski görünebilir.");
        }
    }
}
