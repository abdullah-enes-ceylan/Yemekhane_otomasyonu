<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use Yemekhane\Depo\DegerlendirmeDeposu;
use Yemekhane\Depo\ReplikasyonDeposu;
use Yemekhane\Guvenlik\OturumKullanicisi;
use Yemekhane\Hata\IsKuraliHatasi;

final class DegerlendirmeServisi
{
    private const YORUM_EN_FAZLA = 500;

    public function __construct(
        private readonly DegerlendirmeDeposu $degerlendirmeDeposu,
        private readonly ReplikasyonDeposu $replikasyonDeposu,
    ) {
    }

    public static function yorumSiniri(): int
    {
        return self::YORUM_EN_FAZLA;
    }

    /** @return array{menuler: list<array<string, mixed>>, yorumSiniri: int} */
    public function listele(OturumKullanicisi $kullanici): array
    {
        $menuler = array_map(static fn (array $s): array => [
            'menuId' => (int) $s['menu_id'],
            'tarih' => $s['tarih'],
            'ogun' => $s['ogun'],
            'yemekhane' => $s['yemekhane'],
            'yemekler' => $s['yemekler'],
            'kullanmaZamani' => $s['kullanma_zamani'],
            // Kişi başına menü başına tek oy: puan verilmişse form yerine verilen puan gösterilir
            'degerlendirilebilir' => $s['puan'] === null,
            'degerlendirme' => $s['puan'] === null ? null : [
                'puan' => (int) $s['puan'],
                'yorum' => $s['yorum'],
                'zaman' => $s['degerlendirme_zamani'],
            ],
        ], $this->degerlendirmeDeposu->kullanilmisMenuler($kullanici->id));

        return ['menuler' => $menuler, 'yorumSiniri' => self::YORUM_EN_FAZLA];
    }

    /** @return array{degerlendirmeId: int} */
    public function degerlendir(OturumKullanicisi $kullanici, int $menuId, int $puan, ?string $yorum): array
    {
        if (!$this->degerlendirmeDeposu->menuyuKullandiMi($kullanici->id, $menuId)) {
            throw new IsKuraliHatasi('Yalnızca kullandığınız (turnikeden geçtiğiniz) menüleri değerlendirebilirsiniz.', 422);
        }

        $kimlik = $this->degerlendirmeDeposu->ekle($kullanici->id, $menuId, $puan, $yorum);

        // Sayfa hemen ardından listeyi replikadan yeniden ister
        if (!$this->replikasyonDeposu->replikaninYetismesiniBekle()) {
            error_log('[UYARI] Replika değerlendirme sonrasında zamanında yetişmedi; liste bir an eski görünebilir.');
        }

        return ['degerlendirmeId' => $kimlik];
    }
}
