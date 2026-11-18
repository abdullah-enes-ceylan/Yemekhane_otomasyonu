# Kendi kararlarımız

Ödev PDF'inin açıkça söylemediği, bizim verdiğimiz kararlar. Her kararda: ne karar verildi, neden, PDF'te dayanak varsa o.
Bu kararlar raporun "Varsayımlar" bölümüne de yazılır.

---

## 1. Yönetici hangi fiyatı öder?
**Karar:** Yönetici personel fiyatını öder ve rezervasyon yapabilir.
**Neden:** `sp_rezervasyon_yap` fiyatı `IF(rol = 'ogrenci', ogrenci_fiyati, personel_fiyati)` ile seçiyor; şemadaki yorum da "yönetici personel fiyatı öder" diyor. API de aynı kuralla `fiyat` alanını döner.

## 2. Menü önbelleği ne kadar tutulur?
**Karar:** Anahtardaki tarihin gün sonuna kadar (Europe/Istanbul 23:59:59), `EXPIREAT` ile. Tarihi geçmiş menüler önbelleğe alınmaz.
**Neden:** PDF süreyi vermiyor. Bir günün menüsü o gün geçince işe yaramaz; yönetici değiştirirse zaten silinir.

## 3. Migration dosyası nerede?
**Karar:** `db/init/04_degisiklik.sql`, idempotent (`information_schema` kontrolü, `DROP PROCEDURE IF EXISTS` + `CREATE`).
**Neden:** Temiz `docker compose up -d` sonrası da yeni sütunlar olmalı; yoksa uygulama çalışmaz. Çalışan sisteme aynı dosya primary'ye elle uygulanır, replikasyonla replikaya geçer.

## 4. Yarım porsiyon iptal edilince ne kadar iade?
**Karar:** Ödenen tutar iade edilir; ayrıca sütun eklenmez, `sp_rezervasyon_iptal` değişmez.
**Neden:** `rezervasyonlar.ucret` zaten ödenen tutarı tutuyor ve iptal yordamı iadeyi bundan yapıyor.

## 5. Yarım porsiyon kapasitede kaç yer kaplar?
**Karar:** Tam bir yer.
**Neden:** Kişi sayısı aynı; masa/sandalye yarım kullanılmaz.

## 6. Turnike nerede?
**Karar:** Yönetici panelinde "Turnike geçişi" bölümü (ayrı ekran yok).
**Neden:** Rezervasyonun "kullanıldı" olması ve değerlendirmenin test edilebilmesi için `sp_turnike_gecis` çağrılabilmeli; PDF bunun için ekran tanımlamıyor.

## 7. Yarım porsiyon fiyatı nasıl yuvarlanır?
**Karar:** `ROUND(fiyat * 0.60, 2)`, DECIMAL.
**Neden:** Para kuruş hassasiyetinde; float kullanılmaz.

## 8. Giriş sorgusu hangi veritabanından?
**Karar:** Replikadan.
**Neden:** Okuma işlemi; girişten hemen önce kullanıcıya yazan bir işlem olmadığı için gecikme etkilemez.

## 9. Yordam hataları hangi HTTP koduyla döner?
**Karar:** 422 + `{"mesaj": ...}`.
**Neden:** Mesaja bakıp 400/409 ayırmak kırılgan olurdu; 422 "istek anlaşıldı ama iş kuralına takıldı" demek.

## 10. Rol kontrolü nerede?
**Karar:** `OturumFiltresi`'nde: `/api/admin/` ile başlayan her yol yönetici ister (403).
**Neden:** Tek yerde kontrol; yeni yönetici uç noktası eklerken unutulmaz.

## 11. Oturum cookie'si
**Karar:** `yemekhane_oturum`, `HttpOnly; SameSite=Strict; Path=/`, süresiz; asıl süre Redis TTL'i. `Secure` yok.
**Neden:** HttpOnly → JS okuyamaz (XSS); SameSite=Strict → CSRF'e karşı. `Secure` http://localhost'ta çalışmaz; üretimde HTTPS + Secure.

## 12. Menü yanıtı: önbelleğe giren / girmeyen kısım
**Karar:** Menü içeriği (yemekler, kalori, alerjen, iki fiyat) önbelleğe girer; kişiye ve ana bağlı alanlar (`fiyat`, `bosYer`, `rezervasyonaAcik`, `kapaliNedeni`, `rezervasyonum`) her istekte eklenir.
**Neden:** Önbellekteki veri yalnızca yönetici değiştirince bayatlar; boş yer ve rezervasyon durumu her an değişir.

## 13. Giriş güvenliği
**Karar:** Hatalı e-posta ve hatalı şifre aynı mesaj ve aynı süre (sahte bcrypt özeti). Deneme kilidi yok.
**Neden:** Hangi e-postaların kayıtlı olduğu dışarıdan anlaşılmasın. Kilit şart değil.

## 14. Bakiye ekranındaki güncel bakiye hangi veritabanından? (16 Kas 2026)
**Karar:** Bakiye sayısı **primary**'den, hareket geçmişi **replika**'dan okunur. Rezervasyon/iptal/yükleme sonrası gösterilen bakiye zaten yordamın döndürdüğü (primary) değerdir.
**Neden:** PDF yalnızca "rezervasyondan hemen sonra gösterilen bakiye kaynaktan okunur" ve "listeleme replikaya" diyor; ayrıca açılan Bakiye sayfasını söylemiyor. Kullanıcı rezervasyondan hemen sonra Bakiye sayfasına geçerse replika bir an geride olabilir ve eski bakiye görünür. Para bilgisinin her zaman doğru olması, tek satırlık bir sorgunun primary'ye gitmesinden daha önemli. Hareket geçmişi listeleme olduğu için kurala uygun olarak replikada kalır.

## 15. Yazmadan sonra replikadan okuma ("kendi yazdığını oku") (18 Kas 2026)
**Karar:** Para hareketi yapan her yazmadan (rezervasyon, iptal, yükleme) sonra primary'nin GTID kümesinin replikaya geçmesi en çok 1 sn beklenir (`ReplikasyonDeposu`, `WAIT_FOR_EXECUTED_GTID_SET`). Süre dolarsa yazma geçerlidir, yalnızca günlüğe uyarı düşer.
**Neden:** Ölçüldü: primary'ye yazıp hemen replikadan okuyunca 20 denemenin 20'sinde eski değer geldi; tarayıcıda iptal edilen rezervasyon listede hâlâ "aktif" görünüyordu. Listeleri primary'den okumak okuma/yazma ayrımını bozardı; arayüzde satırı elle değiştirmek iş kuralını JS'e taşırdı. Bekleme yaklaşık 10 ms.

## 16. İptal uç noktası
**Karar:** `POST /api/rezervasyonlar/{id}/iptal` (DELETE değil).
**Neden:** Kayıt silinmiyor; durumu 'iptal' oluyor ve ücret iade ediliyor.

## 17. Rezervasyonlarım: gruplama ve "iptal edilebilir mi"
**Karar:** API aktif / kullanıldı / iptal gruplarını ve her kayıt için `iptalEdilebilir` alanını döner. `iptalEdilebilir`, `sp_rezervasyon_iptal` ile aynı koşul (`durum = 'aktif' AND NOW() < TIMESTAMP(tarih, son_saat)`) ile SQL'de hesaplanır.
**Neden:** Arayüzde iş kuralı olmasın; düğme açıkken yordamın reddetmesi (ya da tersi) olmasın.

## 18. Bakiye hareket geçmişi
**Karar:** Son 50 hareket, sayfalama yok.
**Neden:** Şart sayfalama istemiyor; telefonda okunabilir kalır.

## 19. Yükleme tutarının denetimi ve SP çağrı kalıbı
**Karar:** `Girdi::tutar()` yalnızca biçimi denetler (string, en çok 2 ondalık); 10–1000 TL kuralı yordamda. SP çağrısının ortak adımları (execute → fetch → closeCursor) `YordamSonucu::tekSatir()`'da; hedef bağlantı (`primaryDb()`) her depo metodunda açıkça yazılır.
**Neden:** Kural tek yerde kalır, tutar float'a çevrilmeden DECIMAL'e gider. `closeCursor()` unutulamaz; hangi sunucuya gidildiği yine o satırdan okunur.

## 20. Değerlendirme kuralları (18 Kas 2026)
**Karar:** Değerlendirme bir kez verilir, sonradan değiştirilemez (ikinci deneme 409). "Kullanılmış menü" kontrolü yazmadan hemen önce **primary**'den okunur; liste replikadan.
**Neden:** Şema "kişi başına menü başına tek oy" diyor (UNIQUE). Kontrol bir yazmanın ön koşulu; turnikeden hemen sonra replika geride olsa bile doğru karar verilmeli.

## 21. Menü oluşturma / düzenleme
**Karar:** Yordam yok; `menuler` + `menu_yemekleri` primary'de uygulamanın açtığı tek transaction'da. Düzenlemede yalnızca fiyatlar ve yemekler değişir; yemekhane/tarih/öğün sabit. Geçmiş tarihe menü oluşturulmaz, geçmiş menü düzenlenmez. En az bir yemek zorunlu.
**Neden:** İki tablo birlikte değişiyor; yarım menü oluşmamalı. Yemekhane+tarih+öğün menünün kimliği ve Aşama 3 önbellek anahtarı. Yemeksiz menü `v_menu_detay`'da görünmez (Kararlar #12).

## 22. Uygulama içi iş kuralı hatalarının HTTP kodları
**Karar:** Kural ihlali 422, tekrar eden kayıt (1062) 409, yetki 403, bulunamadı 404, biçim 400.
**Neden:** Yordam hatalarıyla (422) tutarlı; arayüz kodlara bakmaz, mesajı gösterir.

## 23. Yönetici paneli bağlantısı
**Karar:** Gezinmede yalnızca yöneticiye gösterilir; yetkinin kendisi `OturumFiltresi`'nde (403).
**Neden:** Gösterim kolaylığı; güvenlik arayüze bırakılmaz.

## 24. Menü önbelleği ayrıntıları (Aşama 3, 18 Kas 2026)
**Karar:** Anahtar `menu:{yemekhane_id}:{tarih}:{ogun_id}`; değer menünün içeriği (JSON); süre `SET ... EXAT` ile anahtardaki tarihin 23:59:59'u (Europe/Istanbul). Tarihi geçmiş menü ve "menü yok" önbelleğe alınmaz. Her okuma `[ONBELLEK] HIT/MISS`, silme `[ONBELLEK] SIL` olarak günlüğe yazılır.
**Neden:** "O günün sonuna kadar" şartı; `EXAT` değer ile bitiş anını tek komutta yazar (araya kesinti girerse süresiz kayıt kalmaz). "Menü yok" önbelleğe girseydi yönetici menüyü oluşturduğunda öğrenci gün sonuna kadar göremeyebilirdi.

## 25. Önbellek silme sırası
**Karar:** Yönetici menüyü düzenleyince: veritabanı (primary) → replikanın yetişmesini bekle → `DEL menu:...`. Oluşturmada da aynı anahtar silinir.
**Neden:** Menü replikadan okunuyor. Anahtar replika yetişmeden silinirse araya giren bir istek replikadan eski menüyü okuyup gün sonuna kadar yeniden önbelleğe yazabilir.

## 26. Redis'e ulaşılamazsa
**Karar:** Menü önbelleği atlanır, menü veritabanından gelir (günlüğe `[ONBELLEK] HATA`). Oturum doğrulanamadığı için oturum gerektiren istekler genel hata (500) alır.
**Neden:** Önbellek hızlandırıcıdır, zorunlu değildir; oturum güvenlik bilgisidir, doğrulanamıyorsa erişim verilmez.
