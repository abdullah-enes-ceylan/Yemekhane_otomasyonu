<?php

declare(strict_types=1);

/*
 * Tek giriş noktası (front controller). Apache, /api/ ile başlayan her isteği buraya gönderir.
 * Akış: Istek → OturumFiltresi → Yonlendirici → Controller → Servis → Depo → Yanit (JSON)
 */

use Yemekhane\Ayar\RedisBaglantisi;
use Yemekhane\Ayar\Veritabani;
use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yonlendirici;
use Yemekhane\Controller\AuthController;
use Yemekhane\Controller\MenuController;
use Yemekhane\Controller\SaglikController;
use Yemekhane\Depo\KullaniciDeposu;
use Yemekhane\Depo\MenuDeposu;
use Yemekhane\Depo\RezervasyonDeposu;
use Yemekhane\Depo\SaglikDeposu;
use Yemekhane\Guvenlik\OturumFiltresi;
use Yemekhane\Guvenlik\OturumServisi;
use Yemekhane\Hata\HataYakalayici;
use Yemekhane\Servis\AuthServisi;
use Yemekhane\Servis\MenuServisi;
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

    $oturumServisi = new OturumServisi($redisBaglantisi);

    $saglikController = new SaglikController(new SaglikServisi(new SaglikDeposu($veritabani), $redisBaglantisi));
    $authController = new AuthController(new AuthServisi(new KullaniciDeposu($veritabani), $oturumServisi));
    $rezervasyonDeposu = new RezervasyonDeposu($veritabani);
    $menuController = new MenuController(new MenuServisi(new MenuDeposu($veritabani), $rezervasyonDeposu));

    $yonlendirici = new Yonlendirici();
    $yonlendirici->get('/api/saglik', $saglikController->durum(...));
    $yonlendirici->post('/api/auth/giris', $authController->giris(...));
    $yonlendirici->post('/api/auth/cikis', $authController->cikis(...));
    $yonlendirici->get('/api/auth/ben', $authController->ben(...));
    $yonlendirici->get('/api/menu/secenekler', $menuController->secenekler(...));
    $yonlendirici->get('/api/menu/gunluk', $menuController->gunluk(...));
    $yonlendirici->get('/api/menu/haftalik', $menuController->haftalik(...));

    $istek = (new OturumFiltresi($oturumServisi))->uygula(Istek::globallerdenOlustur());
    $yanit = $yonlendirici->yonlendir($istek);
} catch (Throwable $hata) {
    $yanit = HataYakalayici::yanitaCevir($hata);
}

$yanit->gonder();
