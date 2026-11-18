<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use DateTimeImmutable;
use DateTimeZone;
use RedisException;
use Yemekhane\Ayar\RedisBaglantisi;

/**
 * Menü önbelleği (Aşama 3).
 *
 *   Anahtar: menu:{yemekhane_id}:{tarih}:{ogun_id}      ör. menu:1:2026-11-20:2
 *   Değer:   menünün değişmeyen içeriği (yemekler, kalori, alerjen, iki fiyat) – JSON
 *   Süre:    anahtardaki tarihin gün sonu (Europe/Istanbul 23:59:59), SET ... EXAT
 *   Silinir: yönetici o menüyü oluşturduğunda / düzenlediğinde (YonetimServisi)
 *
 * Her okuma uygulama günlüğüne HIT ya da MISS olarak yazılır (docker compose logs uygulama).
 * Redis'e ulaşılamazsa önbellek atlanır, menü veritabanından gelir: önbellek hızlandırıcıdır, zorunlu değil.
 */
final class MenuOnbellegi
{
    private const SAAT_DILIMI = 'Europe/Istanbul';

    public function __construct(private readonly RedisBaglantisi $redisBaglantisi)
    {
    }

    public static function anahtar(int $yemekhaneId, string $tarih, int $ogunId): string
    {
        return "menu:{$yemekhaneId}:{$tarih}:{$ogunId}";
    }

    /** @return array<string, mixed>|null önbellekte yoksa null (MISS) */
    public function oku(int $yemekhaneId, string $tarih, int $ogunId): ?array
    {
        $anahtar = self::anahtar($yemekhaneId, $tarih, $ogunId);
        try {
            $deger = $this->redisBaglantisi->redis()->get($anahtar);
        } catch (RedisException $hata) {
            error_log("[ONBELLEK] HATA {$anahtar}: Redis'e ulaşılamadı, veritabanından okunacak ({$hata->getMessage()})");

            return null;
        }

        if (!is_string($deger)) {
            error_log("[ONBELLEK] MISS {$anahtar} (veritabanından okunacak)");

            return null;
        }

        error_log("[ONBELLEK] HIT {$anahtar}");

        return json_decode($deger, true, 16, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $icerik */
    public function yaz(int $yemekhaneId, string $tarih, int $ogunId, array $icerik): void
    {
        $anahtar = self::anahtar($yemekhaneId, $tarih, $ogunId);
        $gunSonu = (new DateTimeImmutable($tarih . ' 23:59:59', new DateTimeZone(self::SAAT_DILIMI)))->getTimestamp();

        // Tarihi geçmiş menü önbelleğe alınmaz (süresi zaten dolmuş olurdu)
        if ($gunSonu <= time()) {
            return;
        }

        try {
            // Değer ve bitiş anı tek komutta: SET anahtar değer EXAT gün_sonu
            $this->redisBaglantisi->redis()->set(
                $anahtar,
                json_encode($icerik, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ['EXAT' => $gunSonu],
            );
        } catch (RedisException $hata) {
            error_log("[ONBELLEK] HATA {$anahtar}: yazılamadı ({$hata->getMessage()})");
        }
    }

    public function sil(int $yemekhaneId, string $tarih, int $ogunId): void
    {
        $anahtar = self::anahtar($yemekhaneId, $tarih, $ogunId);
        try {
            $silinen = $this->redisBaglantisi->redis()->del($anahtar);
            error_log("[ONBELLEK] SIL {$anahtar} (" . ($silinen > 0 ? 'silindi' : 'zaten yoktu') . ')');
        } catch (RedisException $hata) {
            // Silinemeyen kayıt en geç gün sonunda düşer; yine de günlüğe yazılır
            error_log("[ONBELLEK] HATA {$anahtar}: silinemedi ({$hata->getMessage()})");
        }
    }
}
