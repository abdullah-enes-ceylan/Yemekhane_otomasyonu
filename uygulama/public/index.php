<?php

declare(strict_types=1);

/*
 * Tek giriş noktası (front controller). Apache, /api/ ile başlayan her isteği buraya gönderir.
 * Akış: Istek → Yonlendirici → Controller → Servis → Depo → Yanit (JSON)
 */

use Yemekhane\Ayar\RedisBaglantisi;
use Yemekhane\Ayar\Veritabani;
use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yonlendirici;
use Yemekhane\Controller\SaglikController;
use Yemekhane\Depo\SaglikDeposu;
use Yemekhane\Hata\HataYakalayici;
use Yemekhane\Servis\SaglikServisi;

require __DIR__ . '/../vendor/autoload.php';

// PHP uyarıları (ör. tanımsız dizi anahtarı) sessizce geçilmesin; hataya çevrilip yakalanır
set_error_handler(static function (int $seviye, string $mesaj, string $dosya, int $satir): bool {
    if ((error_reporting() & $seviye) === 0) {
        return false;
    }
    throw new ErrorException($mesaj, 0, $seviye, $dosya, $satir);
});

try {
    $veritabani = Veritabani::ortamdanOlustur();
    $redisBaglantisi = RedisBaglantisi::ortamdanOlustur();

    $saglikController = new SaglikController(new SaglikServisi(new SaglikDeposu($veritabani), $redisBaglantisi));

    $yonlendirici = new Yonlendirici();
    $yonlendirici->get('/api/saglik', $saglikController->durum(...));

    $yanit = $yonlendirici->yonlendir(Istek::globallerdenOlustur());
} catch (Throwable $hata) {
    $yanit = HataYakalayici::yanitaCevir($hata);
}

$yanit->gonder();
