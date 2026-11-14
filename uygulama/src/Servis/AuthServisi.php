<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use Yemekhane\Depo\KullaniciDeposu;
use Yemekhane\Guvenlik\OturumKullanicisi;
use Yemekhane\Guvenlik\OturumServisi;
use Yemekhane\Hata\IsKuraliHatasi;

final class AuthServisi
{
    /**
     * Kullanıcı bulunamadığında da password_verify bu özetle çalıştırılır; böylece
     * "e-posta kayıtlı mı?" sorusunun cevabı yanıt süresinden anlaşılamaz.
     */
    private const SAHTE_OZET = '$2y$10$yVqakULtKjt81ZB/Uy5/buTZJ84SbDMdZBDn8ahGkdLFLMmHyvMN6';

    public function __construct(
        private readonly KullaniciDeposu $kullaniciDeposu,
        private readonly OturumServisi $oturumServisi,
    ) {
    }

    /** @return array{token: string, kullanici: OturumKullanicisi} */
    public function girisYap(string $eposta, string $sifre): array
    {
        $kayit = $this->kullaniciDeposu->epostaIleBul($eposta);
        $sifreDogru = password_verify($sifre, $kayit['sifre_hash'] ?? self::SAHTE_OZET);

        if ($kayit === null || !$sifreDogru) {
            error_log('[OTURUM] başarısız giriş denemesi');
            throw new IsKuraliHatasi('E-posta veya şifre hatalı.', 401);
        }
        if ((int) $kayit['aktif'] !== 1) {
            throw new IsKuraliHatasi('Hesabınız pasif durumda.', 403);
        }

        $kullanici = new OturumKullanicisi((int) $kayit['id'], $kayit['ad'], $kayit['soyad'], $kayit['rol']);
        $token = $this->oturumServisi->olustur($kullanici);
        error_log(sprintf('[OTURUM] giriş: kullanici_id=%d rol=%s', $kullanici->id, $kullanici->rol));

        return ['token' => $token, 'kullanici' => $kullanici];
    }

    public function cikisYap(?string $token): void
    {
        if ($token !== null) {
            $this->oturumServisi->sil($token);
        }
    }
}
