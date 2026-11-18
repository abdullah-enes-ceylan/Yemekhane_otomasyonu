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
use Yemekhane\Controller\AdminController;
use Yemekhane\Controller\AuthController;
use Yemekhane\Controller\BakiyeController;
use Yemekhane\Controller\DegerlendirmeController;
use Yemekhane\Controller\MenuController;
use Yemekhane\Controller\RezervasyonController;
use Yemekhane\Controller\SaglikController;
use Yemekhane\Depo\BakiyeDeposu;
use Yemekhane\Depo\DegerlendirmeDeposu;
use Yemekhane\Depo\KullaniciDeposu;
use Yemekhane\Depo\MenuDeposu;
use Yemekhane\Depo\MenuYonetimDeposu;
use Yemekhane\Depo\ReplikasyonDeposu;
use Yemekhane\Depo\RezervasyonDeposu;
use Yemekhane\Depo\SaglikDeposu;
use Yemekhane\Guvenlik\OturumFiltresi;
use Yemekhane\Guvenlik\OturumServisi;
use Yemekhane\Hata\HataYakalayici;
use Yemekhane\Servis\AuthServisi;
use Yemekhane\Servis\BakiyeServisi;
use Yemekhane\Servis\DegerlendirmeServisi;
use Yemekhane\Servis\MenuOnbellegi;
use Yemekhane\Servis\MenuServisi;
use Yemekhane\Servis\RezervasyonServisi;
use Yemekhane\Servis\SaglikServisi;
use Yemekhane\Servis\YonetimServisi;

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
    $kullaniciDeposu = new KullaniciDeposu($veritabani);
    $menuDeposu = new MenuDeposu($veritabani);
    $rezervasyonDeposu = new RezervasyonDeposu($veritabani);
    $replikasyonDeposu = new ReplikasyonDeposu($veritabani);
    $menuOnbellegi = new MenuOnbellegi($redisBaglantisi);

    $authController = new AuthController(new AuthServisi($kullaniciDeposu, $oturumServisi));
    $menuController = new MenuController(new MenuServisi($menuDeposu, $rezervasyonDeposu, $menuOnbellegi));
    $rezervasyonController = new RezervasyonController(new RezervasyonServisi($rezervasyonDeposu, $replikasyonDeposu));
    $bakiyeController = new BakiyeController(new BakiyeServisi(new BakiyeDeposu($veritabani), $replikasyonDeposu));
    $degerlendirmeController = new DegerlendirmeController(
        new DegerlendirmeServisi(new DegerlendirmeDeposu($veritabani), $replikasyonDeposu),
    );
    $adminController = new AdminController(new YonetimServisi(
        new MenuYonetimDeposu($veritabani),
        $menuDeposu,
        $kullaniciDeposu,
        $rezervasyonDeposu,
        $replikasyonDeposu,
    ));

    $yonlendirici = new Yonlendirici();
    $yonlendirici->get('/api/saglik', $saglikController->durum(...));
    $yonlendirici->post('/api/auth/giris', $authController->giris(...));
    $yonlendirici->post('/api/auth/cikis', $authController->cikis(...));
    $yonlendirici->get('/api/auth/ben', $authController->ben(...));
    $yonlendirici->get('/api/menu/secenekler', $menuController->secenekler(...));
    $yonlendirici->get('/api/menu/gunluk', $menuController->gunluk(...));
    $yonlendirici->get('/api/menu/haftalik', $menuController->haftalik(...));
    $yonlendirici->get('/api/rezervasyonlar', $rezervasyonController->listele(...));
    $yonlendirici->post('/api/rezervasyonlar', $rezervasyonController->yap(...));
    $yonlendirici->post('/api/rezervasyonlar/{id}/iptal', $rezervasyonController->iptal(...));
    $yonlendirici->get('/api/bakiye', $bakiyeController->ozet(...));
    $yonlendirici->post('/api/bakiye/yukle', $bakiyeController->yukle(...));
    $yonlendirici->get('/api/degerlendirmeler', $degerlendirmeController->listele(...));
    $yonlendirici->post('/api/degerlendirmeler', $degerlendirmeController->ekle(...));
    // Yönetici: OturumFiltresi /api/admin/ önekli her yolda rolü denetler (403)
    $yonlendirici->get('/api/admin/menuler', $adminController->menuRaporu(...));
    $yonlendirici->post('/api/admin/menuler', $adminController->menuOlustur(...));
    $yonlendirici->get('/api/admin/menuler/{id}', $adminController->menu(...));
    $yonlendirici->put('/api/admin/menuler/{id}', $adminController->menuGuncelle(...));
    $yonlendirici->get('/api/admin/yemekler', $adminController->yemekler(...));
    $yonlendirici->post('/api/admin/turnike', $adminController->turnike(...));

    $istek = (new OturumFiltresi($oturumServisi))->uygula(Istek::globallerdenOlustur());
    $yanit = $yonlendirici->yonlendir($istek);
} catch (Throwable $hata) {
    $yanit = HataYakalayici::yanitaCevir($hata);
}

$yanit->gonder();
