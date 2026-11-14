<?php

declare(strict_types=1);

namespace Yemekhane\Guvenlik;

use Redis;
use Yemekhane\Ayar\RedisBaglantisi;

/**
 * Oturumlar Redis'te tutulur:  oturum:{token} → {"id":..,"ad":..,"soyad":..,"rol":..}
 * Süre 30 dakikadır ve her istekte yeniden 30 dakikaya çekilir (kayan süre).
 */
final class OturumServisi
{
    public const CEREZ_ADI = 'yemekhane_oturum';
    private const SURE_SANIYE = 1800;

    public function __construct(private readonly RedisBaglantisi $redisBaglantisi)
    {
    }

    public function olustur(OturumKullanicisi $kullanici): string
    {
        $token = bin2hex(random_bytes(32));
        $this->redis()->set(
            self::anahtar($token),
            json_encode($kullanici->diziye(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ['EX' => self::SURE_SANIYE],
        );

        return $token;
    }

    /** Oturum varsa kullanıcıyı döner ve süresini yeniden 30 dakikaya çeker. */
    public function bulVeYenile(string $token): ?OturumKullanicisi
    {
        if (!self::gecerliBicim($token)) {
            return null;
        }

        // GETEX: okuma ve süre yenileme tek komutta (Redis 6.2+)
        $deger = $this->redis()->getEx(self::anahtar($token), ['EX' => self::SURE_SANIYE]);
        if (!is_string($deger)) {
            return null;
        }

        return OturumKullanicisi::diziden(json_decode($deger, true, 4, JSON_THROW_ON_ERROR));
    }

    public function sil(string $token): void
    {
        if (self::gecerliBicim($token)) {
            $this->redis()->del(self::anahtar($token));
        }
    }

    private function redis(): Redis
    {
        return $this->redisBaglantisi->redis();
    }

    private static function anahtar(string $token): string
    {
        return 'oturum:' . $token;
    }

    /** Cookie'den gelen değer yalnızca bizim ürettiğimiz biçimde olabilir (64 hex karakter). */
    private static function gecerliBicim(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }
}
