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
