<?php

declare(strict_types=1);

namespace Yemekhane\Cekirdek;

use Yemekhane\Hata\IsKuraliHatasi;

/**
 * Rota tablosu: "GET /api/menuler/{id}" gibi bir kalıbı ilgili Controller metoduna bağlar.
 */
final class Yonlendirici
{
    /** @var list<array{yontem: string, kalip: string, isleyici: callable(Istek): Yanit}> */
    private array $rotalar = [];

    /** @param callable(Istek): Yanit $isleyici */
    public function get(string $kalip, callable $isleyici): void
    {
        $this->ekle('GET', $kalip, $isleyici);
    }

    /** @param callable(Istek): Yanit $isleyici */
    public function post(string $kalip, callable $isleyici): void
    {
        $this->ekle('POST', $kalip, $isleyici);
    }

    /** @param callable(Istek): Yanit $isleyici */
    public function put(string $kalip, callable $isleyici): void
    {
        $this->ekle('PUT', $kalip, $isleyici);
    }

    /** @param callable(Istek): Yanit $isleyici */
    public function delete(string $kalip, callable $isleyici): void
    {
        $this->ekle('DELETE', $kalip, $isleyici);
    }

    /** @param callable(Istek): Yanit $isleyici */
    private function ekle(string $yontem, string $kalip, callable $isleyici): void
    {
        $this->rotalar[] = ['yontem' => $yontem, 'kalip' => self::duzenliIfadeyeCevir($kalip), 'isleyici' => $isleyici];
    }

    public function yonlendir(Istek $istek): Yanit
    {
        $yolVar = false;

        foreach ($this->rotalar as $rota) {
            if (preg_match($rota['kalip'], $istek->yol, $eslesme) !== 1) {
                continue;
            }
            $yolVar = true;
            if ($rota['yontem'] !== $istek->yontem) {
                continue;
            }

            $parametreler = array_filter($eslesme, 'is_string', ARRAY_FILTER_USE_KEY);

            return ($rota['isleyici'])($istek->yolParametreleriyle($parametreler));
        }

        if ($yolVar) {
            throw new IsKuraliHatasi('Bu adres için bu istek yöntemi desteklenmiyor.', 405);
        }
        throw new IsKuraliHatasi('İstenen adres bulunamadı.', 404);
    }

    /** "/api/menuler/{id}" → "#^/api/menuler/(?P<id>[^/]+)$#" */
    private static function duzenliIfadeyeCevir(string $kalip): string
    {
        return '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $kalip) . '$#';
    }
}
