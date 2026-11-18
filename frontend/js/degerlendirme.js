'use strict';

/*
 * Değerlendirme: GET /api/degerlendirmeler yalnızca kullanılmış menüleri döner.
 * "Değerlendirilebilir mi" ve "zaten puan verilmiş mi" API'den gelir (degerlendirilebilir,
 * degerlendirme alanları); arayüz yalnızca form ya da verilen puanı çizer.
 * Gönderim POST /api/degerlendirmeler; kurallar API'de denetlenir, mesajı olduğu gibi gösterilir.
 */

document.addEventListener('DOMContentLoaded', async () => {
  const bildirim = document.getElementById('sayfa-bildirimi');
  const alan = document.getElementById('degerlendirme-alani');

  async function listeyiGetir() {
    try {
      const { menuler, yorumSiniri } = await Api.get('/degerlendirmeler');
      menuleriCiz(alan, menuler, yorumSiniri);
    } catch (hata) {
      alan.replaceChildren();
      bildirimGoster(bildirim, hata.message);
    }
  }

  alan.addEventListener('submit', async (olay) => {
    olay.preventDefault();
    const form = olay.target;
    const dugme = form.querySelector('button[type="submit"]');
    bildirimGizle(bildirim);

    dugme.disabled = true;
    dugme.setAttribute('aria-busy', 'true');
    try {
      await Api.post('/degerlendirmeler', {
        menu_id: Number(form.dataset.menuId),
        puan: form.puan.value === '' ? null : Number(form.puan.value),
        yorum: form.yorum.value,
      });
      await listeyiGetir();
      bildirimGoster(bildirim, 'Değerlendirmeniz kaydedildi. Teşekkürler!', 'basari');
    } catch (hata) {
      bildirimGoster(bildirim, hata.message);
      dugme.disabled = false;
    } finally {
      dugme.removeAttribute('aria-busy');
    }
    bildirim.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  });

  try {
    await oturumuBaslat();
  } catch (hata) {
    bildirimGoster(bildirim, hata.message);
    return;
  }
  await listeyiGetir();
});

function menuleriCiz(alan, menuler, yorumSiniri) {
  if (menuler.length === 0) {
    alan.replaceChildren(oge('p', 'kart soluk', 'Henüz kullandığınız bir menü yok. Turnikeden geçtiğiniz öğünler burada listelenir.'));
    return;
  }

  alan.replaceChildren(...menuler.map((menu) => {
    const kart = oge('article', 'kart degerlendirme-karti');
    kart.append(
      oge('h2', null, `${tarihYaz(menu.tarih)} · ${menu.ogun}`),
      oge('p', 'soluk', menu.yemekhane),
    );
    if (menu.yemekler) kart.append(oge('p', 'ogun-yemekleri', menu.yemekler));

    if (menu.degerlendirme) {
      kart.append(verilenPuan(menu.degerlendirme));
    } else if (menu.degerlendirilebilir) {
      kart.append(degerlendirmeFormu(menu, yorumSiniri));
    }
    return kart;
  }));
}

function verilenPuan(degerlendirme) {
  const kutu = oge('div', 'verilen-puan');
  const yildizlar = oge('span', 'yildizlar', '★'.repeat(degerlendirme.puan) + '☆'.repeat(5 - degerlendirme.puan));
  yildizlar.setAttribute('aria-label', `${degerlendirme.puan} / 5 puan`);
  kutu.append(oge('span', 'soluk', 'Puanınız: '), yildizlar);
  if (degerlendirme.yorum) kutu.append(oge('p', 'verilen-yorum', degerlendirme.yorum));
  return kutu;
}

function degerlendirmeFormu(menu, yorumSiniri) {
  const form = oge('form', 'degerlendirme-formu');
  form.dataset.menuId = String(menu.menuId);
  form.noValidate = true;

  const puanlar = oge('fieldset', 'puan-secimi');
  puanlar.append(oge('legend', null, 'Puanınız'));
  for (let puan = 1; puan <= 5; puan++) {
    const kimlik = `puan-${menu.menuId}-${puan}`;
    const secim = oge('input');
    secim.type = 'radio';
    secim.name = 'puan';
    secim.value = String(puan);
    secim.id = kimlik;
    const etiket = oge('label', null, String(puan));
    etiket.htmlFor = kimlik;
    puanlar.append(secim, etiket);
  }

  const yorumAlani = oge('div', 'form-alani');
  const yorumKimlik = `yorum-${menu.menuId}`;
  const yorumEtiket = oge('label', null, 'Yorum (isteğe bağlı)');
  yorumEtiket.htmlFor = yorumKimlik;
  const yorum = oge('textarea');
  yorum.id = yorumKimlik;
  yorum.name = 'yorum';
  yorum.rows = 3;
  yorum.maxLength = yorumSiniri;
  yorumAlani.append(yorumEtiket, yorum);

  const dugme = oge('button', 'dugme', 'Gönder');
  dugme.type = 'submit';

  form.append(puanlar, yorumAlani, dugme);
  return form;
}
