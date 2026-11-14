<?php

declare(strict_types=1);

namespace Yemekhane\Cekirdek;

use Yemekhane\Hata\IsKuraliHatasi;

/**
 * Gelen HTTP isteği. PHP'nin global değişkenleri ($_SERVER, $_GET, $_COOKIE) yalnızca
 * burada, bir kez okunur; diğer katmanlar bu nesneyle çalışır.
 */
final class Istek
{
    /** @var array<string, string> */
    private array $yolParametreleri = [];

    /**
     * @param array<string, mixed>  $sorgu
     * @param array<string, mixed>  $govde
     * @param array<string, string> $cerezler
     */
    private function __construct(
        public readonly string $yontem,
        public readonly string $yol,
        private readonly array $sorgu,
        private readonly array $govde,
        private readonly array $cerezler,
    ) {
    }

    public static function globallerdenOlustur(): self
    {
        $yol = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            is_string($yol) && $yol !== '' ? $yol : '/',
            $_GET,
            self::jsonGovdeyiOku(),
            $_COOKIE,
        );
    }

    /** @return array<string, mixed> */
    private static function jsonGovdeyiOku(): array
    {
        $ham = file_get_contents('php://input');
        if ($ham === false || trim($ham) === '') {
            return [];
        }

        if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            throw new IsKuraliHatasi('İstek gövdesi JSON olarak gönderilmelidir.', 415);
        }

        $veri = json_decode($ham, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($veri)) {
            throw new IsKuraliHatasi('İstek gövdesi bir JSON nesnesi olmalıdır.', 400);
        }

        return $veri;
    }

    /** @param array<string, string> $parametreler */
    public function yolParametreleriyle(array $parametreler): self
    {
        $kopya = clone $this;
        $kopya->yolParametreleri = $parametreler;

        return $kopya;
    }

    public function yolParametresi(string $ad): ?string
    {
        return $this->yolParametreleri[$ad] ?? null;
    }

    public function sorgu(string $ad): ?string
    {
        $deger = $this->sorgu[$ad] ?? null;

        return is_string($deger) ? $deger : null;
    }

    public function govde(string $ad): mixed
    {
        return $this->govde[$ad] ?? null;
    }

    public function cerez(string $ad): ?string
    {
        return $this->cerezler[$ad] ?? null;
    }
}
