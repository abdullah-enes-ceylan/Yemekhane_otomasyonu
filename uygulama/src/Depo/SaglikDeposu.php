<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use PDO;
use Yemekhane\Ayar\Veritabani;

final class SaglikDeposu
{
    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /** @return array{sunucu: string, salt_okunur: bool} */
    public function primaryBilgisi(): array
    {
        return self::sunucuBilgisi($this->veritabani->primaryDb());
    }

    /** @return array{sunucu: string, salt_okunur: bool} */
    public function replicaBilgisi(): array
    {
        return self::sunucuBilgisi($this->veritabani->replicaDb());
    }

    /** @return array{sunucu: string, salt_okunur: bool} */
    private static function sunucuBilgisi(PDO $db): array
    {
        $satir = $db->query('SELECT @@hostname AS sunucu, @@read_only AS salt_okunur')->fetch();

        return ['sunucu' => (string) $satir['sunucu'], 'salt_okunur' => (int) $satir['salt_okunur'] === 1];
    }
}
