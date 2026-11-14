'use strict';

/* Günün menüsü: seçimlere göre /api/menu/gunluk'u çağırır ve gelen menüyü çizer. */

document.addEventListener('DOMContentLoaded', async () => {
  const bildirim = document.getElementById('sayfa-bildirimi');
  const form = document.getElementById('menu-secimi');
  const alan = document.getElementById('menu-alani');

  try {
    await oturumuBaslat();
    const { yemekhaneler, ogunler } = await Api.get('/menu/secenekler');
    secenekleriDoldur(form.yemekhane, yemekhaneler, (y) => y.ad);
    secenekleriDoldur(form.ogun, ogunler, (o) => `${o.ad} (son ${o.sonSaat})`);
    form.ogun.value = '2';
  } catch (hata) {
    bildirimGoster(bildirim, hata.message);
    return;
  }

  async function menuyuGetir() {
    bildirimGizle(bildirim);
    const parametreler = new URLSearchParams({ yemekhane_id: form.yemekhane.value, ogun_id: form.ogun.value });
    if (form.tarih.value) parametreler.set('tarih', form.tarih.value);

    try {
      const { menu } = await Api.get('/menu/gunluk?' + parametreler);
      menuyuCiz(alan, menu);
    } catch (hata) {
      alan.replaceChildren();
      bildirimGoster(bildirim, hata.message);
    }
  }

  form.addEventListener('change', menuyuGetir);
  form.addEventListener('submit', (olay) => olay.preventDefault());
  await menuyuGetir();
});

function secenekleriDoldur(secimKutusu, kayitlar, etiket) {
  for (const kayit of kayitlar) {
    const secenek = oge('option', null, etiket(kayit));
    secenek.value = String(kayit.id);
    secimKutusu.append(secenek);
  }
}

function menuyuCiz(alan, menu) {
  if (menu === null) {
    const kart = oge('div', 'kart');
    kart.append(oge('p', 'soluk', 'Seçilen gün ve öğün için menü bulunmuyor.'));
    alan.replaceChildren(kart);
    return;
  }

  document.getElementById('tarih').value = menu.tarih;

  const kart = oge('article', 'kart');
  const baslik = oge('div', 'menu-baslik');
  baslik.append(oge('h2', null, `${menu.ogun.ad} · ${menu.yemekhane.ad}`), oge('span', 'soluk', tarihYaz(menu.tarih)));
  kart.append(baslik);

  // Yemekler API'den kategori sırasıyla gelir; burada yalnızca aynı kategoridekiler bir başlık altında toplanır
  let sonKategori = null;
  let liste = null;
  for (const yemek of menu.yemekler) {
    if (yemek.kategori !== sonKategori) {
      kart.append(oge('h3', 'kategori-baslik', yemek.kategori));
      liste = oge('ul', 'yemek-listesi');
      kart.append(liste);
      sonKategori = yemek.kategori;
    }
    liste.append(yemekSatiri(yemek));
  }

  const toplam = oge('div', 'toplam-satiri');
  toplam.append(oge('span', null, 'Toplam kalori'), oge('span', null, `${menu.toplamKalori} kcal`));
  kart.append(toplam);

  const bilgi = oge('div', 'bilgi-satiri');
  const fiyat = oge('span', null, 'Fiyat: ');
  fiyat.append(oge('span', 'fiyat', paraYaz(menu.fiyat)));
  bilgi.append(
    fiyat,
    oge('span', 'rozet rozet-bos-yer', `${menu.bosYer} boş yer`),
    oge('span', null, `Rezervasyon son: ${saatYaz(menu.rezervasyonSon)}`),
  );
  kart.append(bilgi);

  alan.replaceChildren(kart);
}

function yemekSatiri(yemek) {
  const satir = oge('li', 'yemek');
  const sol = oge('div');
  sol.append(oge('span', 'yemek-ad', yemek.ad));
  if (yemek.vejetaryen) {
    sol.append(' ', oge('span', 'rozet rozet-vejetaryen', 'Vejetaryen'));
  }
  if (yemek.alerjenler.length > 0) {
    sol.append(oge('span', 'yemek-alerjen', 'Alerjen: ' + yemek.alerjenler.join(', ')));
  }
  satir.append(sol, oge('span', 'yemek-kalori', `${yemek.kalori} kcal`));
  return satir;
}
