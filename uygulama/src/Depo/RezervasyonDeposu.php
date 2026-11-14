<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use Yemekhane\Ayar\Veritabani;

final class RezervasyonDeposu
{
    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /**
     * Kullanıcının verilen menülerdeki rezervasyon durumları: [menu_id => ['id' => .., 'durum' => ..]]
     *
     * @param list<int> $menuIdleri
     * @return array<int, array{id: int, durum: string}>
     */
    public function kullanicininMenuDurumlari(int $kullaniciId, array $menuIdleri): array
    {
        if ($menuIdleri === []) {
            return [];
        }

        $yerTutucular = implode(', ', array_fill(0, count($menuIdleri), '?'));
        $sorgu = $this->veritabani->replicaDb()->prepare(
            "SELECT menu_id, id, durum
               FROM rezervasyonlar
              WHERE kullanici_id = ? AND menu_id IN ({$yerTutucular})",
        );
        $sorgu->execute([$kullaniciId, ...$menuIdleri]);

        $durumlar = [];
        foreach ($sorgu->fetchAll() as $satir) {
            $durumlar[(int) $satir['menu_id']] = ['id' => (int) $satir['id'], 'durum' => $satir['durum']];
        }

        return $durumlar;
    }
}
