<?php

declare(strict_types=1);

namespace Yemekhane\Hata;

use RuntimeException;

/**
 * Kullanıcıya olduğu gibi gösterilebilecek, beklenen hata (ör. "İstenen adres bulunamadı.").
 */
final class IsKuraliHatasi extends RuntimeException
{
    public function __construct(string $mesaj, public readonly int $httpKodu = 400)
    {
        parent::__construct($mesaj);
    }
}
