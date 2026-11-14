<?php

declare(strict_types=1);

namespace Yemekhane\Guvenlik;

/**
 * Oturumu açık kullanıcı. Redis'teki oturum:{token} kaydının içeriği budur;
 * şifre özeti gibi hassas bilgi içermez.
 */
final class OturumKullanicisi
{
    public const ROL_OGRENCI = 'ogrenci';
    public const ROL_PERSONEL = 'personel';
    public const ROL_YONETICI = 'yonetici';

    public function __construct(
        public readonly int $id,
        public readonly string $ad,
        public readonly string $soyad,
        public readonly string $rol,
    ) {
    }

    public function yoneticiMi(): bool
    {
        return $this->rol === self::ROL_YONETICI;
    }

    /** @return array{id: int, ad: string, soyad: string, rol: string} */
    public function diziye(): array
    {
        return ['id' => $this->id, 'ad' => $this->ad, 'soyad' => $this->soyad, 'rol' => $this->rol];
    }

    /** @param array<string, mixed> $veri */
    public static function diziden(array $veri): self
    {
        return new self((int) $veri['id'], (string) $veri['ad'], (string) $veri['soyad'], (string) $veri['rol']);
    }
}
