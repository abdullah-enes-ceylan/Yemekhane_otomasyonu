<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use Yemekhane\Ayar\Veritabani;

final class KullaniciDeposu
{
    public function __construct(private readonly Veritabani $veritabani)
    {
    }

    /**
     * Giriş için kullanıcı kaydı. Okuma olduğu için replikadan; girişten hemen önce
     * bu kullanıcıya yazan bir işlem olmadığından replikasyon gecikmesi etkilemez.
     *
     * @return array{id: int, ad: string, soyad: string, sifre_hash: string, rol: string, aktif: int}|null
     */
    public function epostaIleBul(string $eposta): ?array
    {
        $sorgu = $this->veritabani->replicaDb()->prepare(
            'SELECT id, ad, soyad, sifre_hash, rol, aktif
               FROM kullanicilar
              WHERE eposta = ?',
        );
        $sorgu->execute([$eposta]);
        $satir = $sorgu->fetch();

        return $satir === false ? null : $satir;
    }
}
