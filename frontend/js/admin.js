'use strict';

/*
 * Yönetici paneli. Üç bölüm:
 *  1. Menü raporu   GET  /api/admin/menuler       (doluluk + ortalama puan, replikadan)
 *  2. Menü formu    POST /api/admin/menuler, PUT /api/admin/menuler/{id}
 *  3. Turnike       POST /api/admin/turnike       (sp_turnike_gecis)
 * "Düzenlenebilir mi", doluluk yüzdesi, ortalama puan API'den gelir; kurallar (geçmiş tarih,
 * aynı menü, en az bir yemek) API'de denetlenir, mesajı olduğu gibi gösterilir.
 */

document.addEventListener('DOMContentLoaded', async () => {
  const sayfaBildirimi = document.getElementById('sayfa-bildirimi');
  const raporFormu = document.getElementById('rapor-secimi');
  const raporAlani = document.getElementById('rapor-alani');
  const menuFormu = document.getElementById('menu-formu');
  const formBildirimi = document.getElementById('form-bildirimi');
  const turnikeFormu = document.getElementById('turnike-formu');
  const turnikeBildirimi = document.getElementById('turnike-bildirimi');
  let rapor = null;
  let duzenlenenMenuId = null;

  try {
    await oturumuBaslat();
    const [secenekler, { kategoriler }] = await Promise.all([Api.get('/menu/secenekler'), Api.get('/admin/yemekler')]);
    for (const secim of [raporFormu.yemekhane, menuFormu.yemekhane_id, turnikeFormu.yemekhane_id]) {
      secenekleriDoldur(secim, secenekler.yemekhaneler);
    }
    secenekleriDoldur(menuFormu.ogun_id, secenekler.ogunler);
    yemekSeciminiCiz(document.getElementById('yemek-secimi'), kategoriler);
  } catch (hata) {
    bildirimGoster(sayfaBildirimi, hata.message);
    return;
  }

  /* ---------- 1. Rapor ---------- */
  async function raporuGetir(baslangic) {
    const parametreler = new URLSearchParams({ yemekhane_id: raporFormu.yemekhane.value });
    if (baslangic) parametreler.set('baslangic', baslangic);
    try {
      rapor = await Api.get('/admin/menuler?' + parametreler);
      raporuCiz(raporAlani, rapor);
    } catch (hata) {
      raporAlani.replaceChildren();
      bildirimGoster(sayfaBildirimi, hata.message);
    }
  }

  raporFormu.addEventListener('change', () => raporuGetir(rapor?.baslangic));
  raporFormu.addEventListener('submit', (olay) => olay.preventDefault());
  document.getElementById('onceki-hafta').addEventListener('click', () => raporuGetir(rapor?.oncekiBaslangic));
  document.getElementById('sonraki-hafta').addEventListener('click', () => raporuGetir(rapor?.sonrakiBaslangic));

  raporAlani.addEventListener('click', async (olay) => {
    const dugme = olay.target.closest('button[data-menu-id]');
    if (!dugme) return;
    try {
      const { menu } = await Api.get(`/admin/menuler/${encodeURIComponent(dugme.dataset.menuId)}`);
      duzenlemeyiBaslat(menu);
    } catch (hata) {
      bildirimGoster(sayfaBildirimi, hata.message);
    }
  });

  /* ---------- 2. Menü formu ---------- */
  function duzenlemeyiBaslat(menu) {
    duzenlenenMenuId = menu.id;
    document.getElementById('form-baslik').textContent = 'Menüyü düzenle';
    menuFormu.yemekhane_id.value = String(menu.yemekhaneId);
    menuFormu.tarih.value = menu.tarih;
    menuFormu.ogun_id.value = String(menu.ogunId);
    // Yemekhane, tarih ve öğün menünün kimliğidir; düzenlemede değişmez
    for (const alan of [menuFormu.yemekhane_id, menuFormu.tarih, menuFormu.ogun_id]) alan.disabled = true;
    menuFormu.ogrenci_fiyati.value = menu.ogrenciFiyati;
    menuFormu.personel_fiyati.value = menu.personelFiyati;
    const secili = new Set(menu.yemekIdleri);
    for (const kutu of menuFormu.querySelectorAll('input[name="yemek"]')) kutu.checked = secili.has(Number(kutu.value));
    document.getElementById('vazgec').hidden = false;
    bildirimGizle(formBildirimi);
    document.getElementById('menu-formu-bolumu').scrollIntoView({ behavior: 'smooth' });
  }

  function yeniMenuyeDon() {
    duzenlenenMenuId = null;
    document.getElementById('form-baslik').textContent = 'Yeni menü';
    menuFormu.reset();
    for (const alan of [menuFormu.yemekhane_id, menuFormu.tarih, menuFormu.ogun_id]) alan.disabled = false;
    document.getElementById('vazgec').hidden = true;
  }

  document.getElementById('vazgec').addEventListener('click', () => {
    yeniMenuyeDon();
    bildirimGizle(formBildirimi);
  });

  menuFormu.addEventListener('submit', async (olay) => {
    olay.preventDefault();
    const dugme = menuFormu.querySelector('button[type="submit"]');
    bildirimGizle(formBildirimi);

    const govde = {
      ogrenci_fiyati: menuFormu.ogrenci_fiyati.value.trim(),
      personel_fiyati: menuFormu.personel_fiyati.value.trim(),
      yemek_idleri: [...menuFormu.querySelectorAll('input[name="yemek"]:checked')].map((k) => Number(k.value)),
    };

    dugme.disabled = true;
    dugme.setAttribute('aria-busy', 'true');
    try {
      if (duzenlenenMenuId === null) {
        await Api.post('/admin/menuler', {
          ...govde,
          yemekhane_id: Number(menuFormu.yemekhane_id.value),
          tarih: menuFormu.tarih.value,
          ogun_id: Number(menuFormu.ogun_id.value),
        });
        bildirimGoster(formBildirimi, 'Menü oluşturuldu.', 'basari');
      } else {
        await Api.put(`/admin/menuler/${duzenlenenMenuId}`, govde);
        bildirimGoster(formBildirimi, 'Menü güncellendi.', 'basari');
      }
      yeniMenuyeDon();
      await raporuGetir(rapor?.baslangic);
    } catch (hata) {
      bildirimGoster(formBildirimi, hata.message);
    } finally {
      dugme.disabled = false;
      dugme.removeAttribute('aria-busy');
    }
  });

  /* ---------- 3. Turnike ---------- */
  turnikeFormu.addEventListener('submit', async (olay) => {
    olay.preventDefault();
    const dugme = turnikeFormu.querySelector('button[type="submit"]');
    bildirimGizle(turnikeBildirimi);

    dugme.disabled = true;
    dugme.setAttribute('aria-busy', 'true');
    try {
      const sonuc = await Api.post('/admin/turnike', {
        kullanici: turnikeFormu.kullanici.value,
        yemekhane_id: Number(turnikeFormu.yemekhane_id.value),
        zaman: turnikeFormu.zaman.value || null,
      });
      bildirimGoster(turnikeBildirimi, `${sonuc.kullanici} · ${sonuc.ogun}: geçiş kaydedildi (rezervasyon ${sonuc.rezervasyonId}).`, 'basari');
      turnikeFormu.reset();
    } catch (hata) {
      bildirimGoster(turnikeBildirimi, hata.message);
    } finally {
      dugme.disabled = false;
      dugme.removeAttribute('aria-busy');
    }
  });

  await raporuGetir(null);
});

function secenekleriDoldur(secim, liste) {
  secim.replaceChildren(...liste.map((oge_) => {
    const secenek = oge('option', null, oge_.ad);
    secenek.value = String(oge_.id);
    return secenek;
  }));
}

function yemekSeciminiCiz(alan, kategoriler) {
  alan.replaceChildren(...kategoriler.map((kategori) => {
    const grup = oge('fieldset', 'yemek-grubu');
    grup.append(oge('legend', null, kategori.ad));
    for (const yemek of kategori.yemekler) {
      const etiket = oge('label', 'yemek-secenegi');
      const kutu = oge('input');
      kutu.type = 'checkbox';
      kutu.name = 'yemek';
      kutu.value = String(yemek.id);
      etiket.append(kutu, ` ${yemek.ad} `, oge('span', 'soluk', `${yemek.kalori} kcal`));
      grup.append(etiket);
    }
    return grup;
  }));
}

function raporuCiz(alan, rapor) {
  document.getElementById('hafta-araligi').textContent =
    `${tarihKisaYaz(rapor.baslangic)} – ${tarihKisaYaz(rapor.bitis)}`;

  if (rapor.menuler.length === 0) {
    alan.replaceChildren(oge('p', 'soluk', 'Bu hafta için menü yok.'));
    return;
  }

  alan.replaceChildren(...rapor.menuler.map((menu) => {
    const satir = oge('article', 'rapor-satiri');
    const baslik = oge('div', 'rapor-baslik');
    baslik.append(
      oge('strong', null, `${tarihYaz(menu.tarih)} · ${menu.ogun.ad}`),
      oge('span', 'soluk', `${paraYaz(menu.ogrenciFiyati)} / ${paraYaz(menu.personelFiyati)}`),
    );
    satir.append(baslik, oge('p', 'ogun-yemekleri', menu.yemekler ?? ''));

    const doluluk = oge('div', 'doluluk');
    const cubuk = oge('div', 'doluluk-cubugu');
    const dolu = oge('span');
    dolu.style.width = `${Math.min(100, Number(menu.dolulukYuzde))}%`;
    cubuk.append(dolu);
    cubuk.setAttribute('role', 'img');
    cubuk.setAttribute('aria-label', `Doluluk yüzde ${ondalikYaz(menu.dolulukYuzde)}`);
    doluluk.append(
      oge('span', null, `Doluluk: ${menu.rezerve} / ${menu.kapasite} (%${ondalikYaz(menu.dolulukYuzde)})`),
      cubuk,
    );
    satir.append(doluluk);

    const alt = oge('div', 'rapor-alt');
    alt.append(oge('span', null, menu.ortalamaPuan === null
      ? 'Henüz puan yok'
      : `Ortalama puan: ${ondalikYaz(menu.ortalamaPuan)} / 5 (${menu.oySayisi} oy)`));
    if (menu.duzenlenebilir) {
      const dugme = oge('button', 'dugme dugme-ikincil', 'Düzenle');
      dugme.type = 'button';
      dugme.dataset.menuId = String(menu.id);
      alt.append(dugme);
    }
    satir.append(alt);
    return satir;
  }));
}
