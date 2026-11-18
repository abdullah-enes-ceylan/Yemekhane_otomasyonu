<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use Yemekhane\Depo\BakiyeDeposu;
use Yemekhane\Depo\ReplikasyonDeposu;
use Yemekhane\Guvenlik\OturumKullanicisi;

final class BakiyeServisi
{
    /** Bakiye ekranında gösterilen en fazla hareket sayısı */
    private const HAREKET_SINIRI = 50;

    private const TUR_ADLARI = [
        'yukleme' => 'Bakiye yükleme',
        'rezervasyon' => 'Rezervasyon',
        'iade' => 'İade',
    ];

    public function __construct(
        private readonly BakiyeDeposu $bakiyeDeposu,
        private readonly ReplikasyonDeposu $replikasyonDeposu,
    ) {
    }

    /** @return array{bakiye: string, hareketler: list<array<string, mixed>>, hareketSiniri: int} */
    public function ozet(OturumKullanicisi $kullanici): array
    {
        $hareketler = array_map(static fn (array $h): array => [
            'id' => (int) $h['id'],
            'tur' => $h['tur'],
            'turAdi' => self::TUR_ADLARI[$h['tur']] ?? $h['tur'],
            'tutar' => $h['tutar'],
            'aciklama' => $h['aciklama'],
            'rezervasyonId' => $h['rezervasyon_id'] === null ? null : (int) $h['rezervasyon_id'],
            'zaman' => $h['olusturma'],
        ], $this->bakiyeDeposu->hareketler($kullanici->id, self::HAREKET_SINIRI));

        return [
            'bakiye' => $this->bakiyeDeposu->guncelBakiye($kullanici->id),
            'hareketler' => $hareketler,
            'hareketSiniri' => self::HAREKET_SINIRI,
        ];
    }

    /** @return array{hareketId: int, bakiye: string} */
    public function yukle(OturumKullanicisi $kullanici, string $tutar): array
    {
        $sonuc = $this->bakiyeDeposu->bakiyeYukle($kullanici->id, $tutar);

        // Sayfa hemen ardından hareket listesini replikadan yeniden ister
        if (!$this->replikasyonDeposu->replikaninYetismesiniBekle()) {
            error_log('[UYARI] Replika bakiye yükleme sonrasında zamanında yetişmedi; liste bir an eski görünebilir.');
        }

        return ['hareketId' => (int) $sonuc['hareket_id'], 'bakiye' => $sonuc['bakiye']];
    }
}
