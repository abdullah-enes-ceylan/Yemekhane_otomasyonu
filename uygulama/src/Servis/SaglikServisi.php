<?php

declare(strict_types=1);

namespace Yemekhane\Servis;

use Yemekhane\Ayar\RedisBaglantisi;
use Yemekhane\Depo\SaglikDeposu;

/**
 * Uygulamanın bağımlı olduğu üç servisi (primary, replica, Redis) yoklar.
 * Herhangi birine ulaşılamazsa hata fırlar ve container "unhealthy" olur.
 */
final class SaglikServisi
{
    public function __construct(
        private readonly SaglikDeposu $saglikDeposu,
        private readonly RedisBaglantisi $redisBaglantisi,
    ) {
    }

    /** @return array<string, mixed> */
    public function durum(): array
    {
        return [
            'durum' => 'calisiyor',
            'primary' => $this->saglikDeposu->primaryBilgisi(),
            'replica' => $this->saglikDeposu->replicaBilgisi(),
            'redis' => $this->redisBaglantisi->redis()->ping() ? 'calisiyor' : 'yanit-yok',
        ];
    }
}
