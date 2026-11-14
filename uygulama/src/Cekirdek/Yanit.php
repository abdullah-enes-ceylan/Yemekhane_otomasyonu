<?php

declare(strict_types=1);

namespace Yemekhane\Cekirdek;

/**
 * API'nin döndürdüğü yanıt. Web API yolu seçildiği için gövde her zaman JSON'dır.
 */
final class Yanit
{
    /** @var array<string, string> */
    private array $basliklar = [];

    /** @var list<array{ad: string, deger: string, bitis: int}> */
    private array $cerezler = [];

    /** @param array<mixed>|null $veri */
    private function __construct(
        public readonly int $durumKodu,
        public readonly ?array $veri,
    ) {
    }

    /** @param array<mixed> $veri */
    public static function json(array $veri, int $durumKodu = 200): self
    {
        return new self($durumKodu, $veri);
    }

    public static function bos(int $durumKodu = 204): self
    {
        return new self($durumKodu, null);
    }

    public function baslikEkle(string $ad, string $deger): self
    {
        $this->basliklar[$ad] = $deger;

        return $this;
    }

    /** Tarayıcı kapanınca silinen oturum cookie'si; asıl süre sunucu tarafında (Redis TTL). */
    public function cerezYaz(string $ad, string $deger): self
    {
        $this->cerezler[] = ['ad' => $ad, 'deger' => $deger, 'bitis' => 0];

        return $this;
    }

    public function cerezSil(string $ad): self
    {
        $this->cerezler[] = ['ad' => $ad, 'deger' => '', 'bitis' => 1];

        return $this;
    }

    public function gonder(): void
    {
        http_response_code($this->durumKodu);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($this->basliklar as $ad => $deger) {
            header($ad . ': ' . $deger);
        }
        foreach ($this->cerezler as $cerez) {
            // HttpOnly: JavaScript okuyamaz (XSS). SameSite=Strict: başka siteden gelen istekte gönderilmez (CSRF).
            // Secure yok: sistem http://localhost üzerinde çalışıyor; üretimde HTTPS ile birlikte açılmalı.
            setcookie($cerez['ad'], $cerez['deger'], [
                'expires' => $cerez['bitis'],
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        if ($this->veri !== null) {
            echo json_encode($this->veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
    }
}
