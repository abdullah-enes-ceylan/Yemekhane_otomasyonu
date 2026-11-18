<?php

declare(strict_types=1);

namespace Yemekhane\Depo;

use PDOStatement;
use UnexpectedValueException;

/**
 * Stored procedure çağrısının ortak kalıbı: CALL → tek satırı al → imleci kapat.
 *
 * Hangi sunucuya gidileceği burada DEĞİL, çağıran depo metodunda yazılır:
 *   $sorgu = $this->veritabani->primaryDb()->prepare('CALL sp_bakiye_yukle(?, ?)');
 *   return YordamSonucu::tekSatir($sorgu, [$kullaniciId, $tutar]);
 *
 * Yordamlar kendi transaction'ını açıp kapattığı için burada beginTransaction() yoktur.
 * Hata durumunda (SIGNAL SQLSTATE '45000') execute() PDOException fırlatır; HataYakalayici
 * yordamın Türkçe mesajını HTTP 422 ile arayüze taşır.
 */
final class YordamSonucu
{
    /**
     * @param list<int|string> $parametreler
     * @return array<string, mixed>
     */
    public static function tekSatir(PDOStatement $sorgu, array $parametreler): array
    {
        try {
            $sorgu->execute($parametreler);
            $satir = $sorgu->fetch();
        } finally {
            // CALL, sonuç satırından sonra ayrıca bir durum sonucu döndürür. İmleç kapatılmazsa
            // aynı bağlantıdaki bir sonraki sorgu "2014 Commands out of sync" hatası verir.
            $sorgu->closeCursor();
        }

        if (!is_array($satir)) {
            // Sözleşmeye göre başarılı çağrı tek satır döner; dönmüyorsa programlama hatasıdır (genel mesaj + log)
            throw new UnexpectedValueException('Yordam beklenen sonuç satırını döndürmedi.');
        }

        return $satir;
    }
}
