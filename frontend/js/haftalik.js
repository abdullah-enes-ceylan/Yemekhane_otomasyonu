'use strict';

/*
 * Haftalık menü: /api/menu/haftalik'i çağırır ve 7 günü çizer. Fiyat (rolüne göre),
 * boş yer, düğmenin açık/pasif olması ve pasifse nedeni API'den hazır gelir.
 */

document.addEventListener('DOMContentLoaded', async () => {
  const bildirim = document.getElementById('sayfa-bildirimi');
  const form = document.getElementById('hafta-secimi');
  const alan = document.getElementById('hafta-alani');
  const oncekiDugme = document.getElementById('onceki-hafta');
  const sonrakiDugme = document.getElementById('sonraki-hafta');
  let hafta = null;

  try {
    await oturumuBaslat();
    const { yemekhaneler } = await Api.get('/menu/secenekler');
    for (const yemekhane of yemekhaneler) {
      const secenek = oge('option', null, yemekhane.ad);
      secenek.value = String(yemekhane.id);
      form.yemekhane.append(secenek);
    }
  } catch (hata) {
    bildirimGoster(bildirim, hata.message);
    return;
  }

  async function haftayiGetir(baslangic) {
    bildirimGizle(bildirim);
    const parametreler = new URLSearchParams({ yemekhane_id: form.yemekhane.value });
    if (baslangic) parametreler.set('baslangic', baslangic);

    try {
      hafta = await Api.get('/menu/haftalik?' + parametreler);
      haftayiCiz(alan, hafta);
    } catch (hata) {
      alan.replaceChildren();
      bildirimGoster(bildirim, hata.message);
    }
  }

  form.addEventListener('change', () => haftayiGetir(hafta?.baslangic));
  form.addEventListener('submit', (olay) => olay.preventDefault());
  oncekiDugme.addEventListener('click', () => haftayiGetir(hafta?.oncekiBaslangic));
  sonrakiDugme.addEventListener('click', () => haftayiGetir(hafta?.sonrakiBaslangic));

  await haftayiGetir(null);
});

function haftayiCiz(alan, hafta) {
  document.getElementById('hafta-araligi').textContent =
    `${tarihKisaYaz(hafta.baslangic)} – ${tarihKisaYaz(hafta.bitis)}`;

  const kartlar = hafta.gunler.map((gun) => {
    const kart = oge('article', 'kart gun-karti');
    kart.append(oge('h2', null, tarihYaz(gun.tarih)));

    if (gun.menuler.length === 0) {
      kart.append(oge('p', 'soluk', 'Bu gün için menü yayımlanmadı.'));
      return kart;
    }
    for (const menu of gun.menuler) {
      kart.append(ogunSatiri(menu));
    }
    return kart;
  });

  alan.replaceChildren(...kartlar);
}

function ogunSatiri(menu) {
  const satir = oge('div', 'ogun-satiri');
  satir.append(
    oge('span', 'ogun-adi', menu.ogun.ad),
    oge('span', 'fiyat', paraYaz(menu.fiyat)),
    oge('span', 'ogun-yemekleri', menu.yemekler.map((y) => y.ad).join(', ')),
  );

  const eylem = oge('div', 'ogun-eylem');
  const dugme = oge('button', 'dugme', 'Rezervasyon yap');
  dugme.type = 'button';
  dugme.dataset.menuId = String(menu.id);
  dugme.disabled = !menu.rezervasyonaAcik;
  eylem.append(dugme, oge('span', 'rozet rozet-bos-yer', `${menu.bosYer} boş yer`));
  if (menu.kapaliNedeni) {
    eylem.append(oge('span', 'kapali-nedeni', menu.kapaliNedeni));
  } else {
    eylem.append(oge('span', 'kapali-nedeni', `Son: ${saatYaz(menu.rezervasyonSon)}`));
  }
  satir.append(eylem);

  return satir;
}
