<?php

declare(strict_types=1);

namespace Yemekhane\Controller;

use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yanit;
use Yemekhane\Servis\SaglikServisi;

final class SaglikController
{
    public function __construct(private readonly SaglikServisi $saglikServisi)
    {
    }

    /** GET /api/saglik */
    public function durum(Istek $istek): Yanit
    {
        return Yanit::json($this->saglikServisi->durum());
    }
}
