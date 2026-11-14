<?php

declare(strict_types=1);

namespace Yemekhane\Ayar;

use Redis;

/**
 * Menü önbelleği ve oturumlar için tek Redis bağlantısı; ilk kullanımda açılır.
 */
final class RedisBaglantisi
{
    private ?Redis $redis = null;

    public function __construct(
        private readonly string $sunucu,
        private readonly int $port,
    ) {
    }

    public static function ortamdanOlustur(): self
    {
        return new self(Ortam::zorunlu('REDIS_HOST'), Ortam::zorunluSayi('REDIS_PORT'));
    }

    public function redis(): Redis
    {
        if ($this->redis === null) {
            $redis = new Redis();
            $redis->connect($this->sunucu, $this->port, 2.0);
            $this->redis = $redis;
        }

        return $this->redis;
    }
}
