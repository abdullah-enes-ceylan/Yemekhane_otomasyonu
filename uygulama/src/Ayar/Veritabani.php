<?php

declare(strict_types=1);

namespace Yemekhane\Ayar;

use PDO;

/**
 * Okuma/yazma ayrımı: iki ayrı bağlantı, iki açık isim.
 *   primaryDb() → mysql-primary (yazma ve "hemen sonra" okumaları)
 *   replicaDb() → mysql-replica (listeleme ve raporlar, salt okunur)
 * Bağlantı, o sunucuya ilk sorgu gönderildiğinde açılır.
 */
final class Veritabani
{
    private ?PDO $primaryDb = null;
    private ?PDO $replicaDb = null;

    public function __construct(
        private readonly string $yazmaSunucusu,
        private readonly string $okumaSunucusu,
        private readonly int $port,
        private readonly string $veritabaniAdi,
        private readonly string $kullanici,
        private readonly string $sifre,
    ) {
    }

    public static function ortamdanOlustur(): self
    {
        return new self(
            Ortam::zorunlu('DB_YAZMA_HOST'),
            Ortam::zorunlu('DB_OKUMA_HOST'),
            Ortam::zorunluSayi('DB_PORT'),
            Ortam::zorunlu('DB_ADI'),
            Ortam::zorunlu('DB_KULLANICI'),
            Ortam::zorunlu('DB_SIFRE'),
        );
    }

    public function primaryDb(): PDO
    {
        return $this->primaryDb ??= $this->baglan($this->yazmaSunucusu);
    }

    public function replicaDb(): PDO
    {
        return $this->replicaDb ??= $this->baglan($this->okumaSunucusu);
    }

    private function baglan(string $sunucu): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $sunucu, $this->port, $this->veritabaniAdi);

        return new PDO($dsn, $this->kullanici, $this->sifre, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            // Son saat karşılaştırmaları NOW() ile veritabanında yapılır; oturum saati İstanbul olmalı
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'",
        ]);
    }
}
