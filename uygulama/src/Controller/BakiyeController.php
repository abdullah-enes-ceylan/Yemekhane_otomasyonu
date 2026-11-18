<?php

declare(strict_types=1);

namespace Yemekhane\Controller;

use Yemekhane\Cekirdek\Girdi;
use Yemekhane\Cekirdek\Istek;
use Yemekhane\Cekirdek\Yanit;
use Yemekhane\Servis\BakiyeServisi;

final class BakiyeController
{
    public function __construct(private readonly BakiyeServisi $bakiyeServisi)
    {
    }

    /** GET /api/bakiye – güncel bakiye (primary) ve son hareketler (replika) */
    public function ozet(Istek $istek): Yanit
    {
        return Yanit::json($this->bakiyeServisi->ozet($istek->kullanici()));
    }

    /** POST /api/bakiye/yukle  { "tutar": "50.00" } */
    public function yukle(Istek $istek): Yanit
    {
        $tutar = Girdi::tutar($istek->govde('tutar'), 'Tutar');

        return Yanit::json($this->bakiyeServisi->yukle($istek->kullanici(), $tutar), 201);
    }
}
