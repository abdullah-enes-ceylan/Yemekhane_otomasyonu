'use strict';

/*
 * Oturum isteyen her sayfanın başında çalışır: oturumu API'den doğrular
 * (yoksa api.js giriş sayfasına yönlendirir), üst çubuğu doldurur, çıkışı bağlar.
 */

const ROL_ADLARI = { ogrenci: 'Öğrenci', personel: 'Personel', yonetici: 'Yönetici' };

async function oturumuBaslat() {
  const { kullanici } = await Api.get('/auth/ben');

  document.getElementById('kullanici-adi').textContent = `${kullanici.ad} ${kullanici.soyad}`;
  document.getElementById('kullanici-rolu').textContent = ROL_ADLARI[kullanici.rol] ?? kullanici.rol;

  document.getElementById('cikis-dugmesi').addEventListener('click', async () => {
    try {
      await Api.post('/auth/cikis');
    } finally {
      location.replace('/index.html');
    }
  });

  return kullanici;
}
