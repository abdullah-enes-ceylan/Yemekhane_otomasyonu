'use strict';

/*
 * Oturum isteyen her sayfanın başında çalışır: oturumu API'den doğrular
 * (yoksa api.js giriş sayfasına yönlendirir), üst çubuğu ve gezinme menüsünü
 * doldurur, çıkışı bağlar. Yetki kontrolü API'dedir; menü yalnızca gösterim.
 */

const ROL_ADLARI = { ogrenci: 'Öğrenci', personel: 'Personel', yonetici: 'Yönetici' };

const SAYFALAR = [
  { adres: '/menu.html', ad: 'Günün menüsü' },
  { adres: '/haftalik.html', ad: 'Haftalık menü' },
  { adres: '/rezervasyonlarim.html', ad: 'Rezervasyonlarım' },
  { adres: '/bakiye.html', ad: 'Bakiye' },
  { adres: '/degerlendirme.html', ad: 'Değerlendirme' },
  // Yalnızca gösterim: asıl yetki kontrolü API'de (OturumFiltresi, /api/admin/ → 403)
  { adres: '/admin.html', ad: 'Yönetici paneli', rol: 'yonetici' },
];

function gezinmeyiCiz(kullanici) {
  const liste = document.getElementById('gezinme');
  if (!liste) return;

  for (const sayfa of SAYFALAR.filter((s) => !s.rol || s.rol === kullanici.rol)) {
    const baglanti = oge('a', 'gezinme-baglantisi', sayfa.ad);
    baglanti.href = sayfa.adres;
    if (location.pathname === sayfa.adres) {
      baglanti.setAttribute('aria-current', 'page');
    }
    const satir = oge('li');
    satir.append(baglanti);
    liste.append(satir);
  }
}

async function oturumuBaslat() {
  const { kullanici } = await Api.get('/auth/ben');

  document.getElementById('kullanici-adi').textContent = `${kullanici.ad} ${kullanici.soyad}`;
  document.getElementById('kullanici-rolu').textContent = ROL_ADLARI[kullanici.rol] ?? kullanici.rol;
  gezinmeyiCiz(kullanici);

  document.getElementById('cikis-dugmesi').addEventListener('click', async () => {
    try {
      await Api.post('/auth/cikis');
    } finally {
      location.replace('/index.html');
    }
  });

  return kullanici;
}
