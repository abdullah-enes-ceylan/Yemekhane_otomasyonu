<?php
// Primary'ye yazıp HEMEN ardından replikadan okur: replikasyon gecikmesini gösterir.
$o = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$dsn = fn ($h) => "mysql:host=$h;dbname=" . getenv('DB_ADI') . ';charset=utf8mb4';
$p = new PDO($dsn(getenv('DB_YAZMA_HOST')), getenv('DB_KULLANICI'), getenv('DB_SIFRE'), $o);
$r = new PDO($dsn(getenv('DB_OKUMA_HOST')), getenv('DB_KULLANICI'), getenv('DB_SIFRE'), $o);
$eski = 0;
for ($i = 1; $i <= 20; $i++) {
    $s = $p->prepare('CALL sp_bakiye_yukle(7, 10)'); $s->execute(); $yeni = $s->fetch()['bakiye']; $s->closeCursor();
    $rep = $r->query('SELECT bakiye FROM kullanicilar WHERE id = 7')->fetchColumn();
    if ($rep !== $yeni) { $eski++; }
    printf("%2d. primary (yordamın döndürdüğü): %s   hemen ardından replika: %s%s\n", $i, $yeni, $rep, $rep !== $yeni ? '   <- ESKİ' : '');
}
echo "20 denemenin $eski tanesinde replika henüz eski bakiyeyi gösteriyordu.\n";
