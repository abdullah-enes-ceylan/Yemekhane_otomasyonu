'use strict';

/*
 * Bakiye: GET /api/bakiye → güncel bakiye (API primary'den okur) + son hareketler (replikadan).
 * Yükleme POST /api/bakiye/yukle (sp_bakiye_yukle). 10–1000 TL kuralı yordamda; arayüz
 * aralığı kontrol etmez, yordamın mesajını olduğu gibi gösterir.
 */

document.addEventListener('DOMContentLoaded', async () => {
  const bildirim = document.getElementById('sayfa-bildirimi');
  const form = document.getElementById('yukleme-formu');
  const dugme = form.querySelector('button[type="submit"]');

  async function ozetiGetir() {
    try {
      ozetiCiz(await Api.get('/bakiye'));
    } catch (hata) {
      bildirimGoster(bildirim, hata.message);
    }
  }

  form.addEventListener('submit', async (olay) => {
    olay.preventDefault();
    bildirimGizle(bildirim);

    dugme.disabled = true;
    dugme.setAttribute('aria-busy', 'true');
    try {
      const sonuc = await Api.post('/bakiye/yukle', { tutar: form.tutar.value.trim() });
      form.reset();
      await ozetiGetir();
      bildirimGoster(bildirim, `Bakiye yüklendi. Güncel bakiye: ${paraYaz(sonuc.bakiye)}`, 'basari');
    } catch (hata) {
      bildirimGoster(bildirim, hata.message);
    } finally {
      dugme.disabled = false;
      dugme.removeAttribute('aria-busy');
    }
  });

  try {
    await oturumuBaslat();
  } catch (hata) {
    bildirimGoster(bildirim, hata.message);
    return;
  }
  await ozetiGetir();
});

function ozetiCiz(ozet) {
  document.getElementById('guncel-bakiye').textContent = paraYaz(ozet.bakiye);
  document.getElementById('hareket-notu').textContent = `Son ${ozet.hareketSiniri} hareket gösterilir.`;

  const liste = document.getElementById('hareket-listesi');
  if (ozet.hareketler.length === 0) {
    liste.replaceChildren(oge('li', 'soluk', 'Henüz hareket yok.'));
    return;
  }

  liste.replaceChildren(...ozet.hareketler.map((h) => {
    const satir = oge('li', 'hareket-satiri');
    // Tutar işaretli gelir (yükleme/iade +, rezervasyon −); renk yalnızca türe göre gösterim
    const tutar = oge('span', `hareket-tutari hareket-${h.tur}`, (Number(h.tutar) > 0 ? '+' : '') + paraYaz(h.tutar));
    satir.append(
      oge('span', 'hareket-turu', h.turAdi),
      tutar,
      oge('span', 'hareket-zamani', `${tarihKisaYaz(h.zaman.slice(0, 10))} ${saatYaz(h.zaman)}`),
    );
    return satir;
  }));
}
