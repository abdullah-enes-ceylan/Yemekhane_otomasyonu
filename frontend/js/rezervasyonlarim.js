'use strict';

/*
 * Rezervasyonlarım: GET /api/rezervasyonlar gruplu listeyi (aktif / kullanıldı / iptal) döner.
 * İptal düğmesi yalnızca API "iptalEdilebilir: true" dediyse çizilir; son saat kontrolünü
 * arayüz yapmaz. İptal POST /api/rezervasyonlar/{id}/iptal (sp_rezervasyon_iptal).
 */

document.addEventListener('DOMContentLoaded', async () => {
  const bildirim = document.getElementById('sayfa-bildirimi');
  const alan = document.getElementById('rezervasyon-alani');

  async function listeyiGetir() {
    try {
      const { gruplar } = await Api.get('/rezervasyonlar');
      gruplariCiz(alan, gruplar);
    } catch (hata) {
      alan.replaceChildren();
      bildirimGoster(bildirim, hata.message);
    }
  }

  alan.addEventListener('click', async (olay) => {
    const dugme = olay.target.closest('button[data-rezervasyon-id]');
    if (!dugme || dugme.disabled) return;
    if (!confirm('Bu rezervasyonu iptal etmek istiyor musunuz? Ücret bakiyenize iade edilir.')) return;

    dugme.disabled = true;
    dugme.setAttribute('aria-busy', 'true');
    try {
      const sonuc = await Api.post(`/rezervasyonlar/${encodeURIComponent(dugme.dataset.rezervasyonId)}/iptal`);
      await listeyiGetir();
      bildirimGoster(
        bildirim,
        `Rezervasyon iptal edildi. İade: ${paraYaz(sonuc.iade)} · Güncel bakiye: ${paraYaz(sonuc.bakiye)}`,
        'basari',
      );
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

function gruplariCiz(alan, gruplar) {
  const bolumler = gruplar.map((grup) => {
    const bolum = oge('section', 'kart rezervasyon-grubu');
    const baslik = oge('h2', null, grup.baslik);
    baslik.append(' ', oge('span', 'rozet rozet-sayi', String(grup.rezervasyonlar.length)));
    bolum.append(baslik);

    if (grup.rezervasyonlar.length === 0) {
      bolum.append(oge('p', 'soluk', 'Bu grupta rezervasyon yok.'));
      return bolum;
    }

    const liste = oge('ul', 'rezervasyon-listesi');
    for (const rezervasyon of grup.rezervasyonlar) {
      liste.append(rezervasyonSatiri(rezervasyon));
    }
    bolum.append(liste);
    return bolum;
  });

  alan.replaceChildren(...bolumler);
}

function rezervasyonSatiri(r) {
  const satir = oge('li', 'rezervasyon-satiri');
  satir.append(
    oge('span', 'rezervasyon-baslik', `${tarihYaz(r.tarih)} · ${r.ogun}`),
    oge('span', 'fiyat', paraYaz(r.ucret)),
    oge('span', 'rezervasyon-ayrinti', r.yemekhane),
  );

  const ayrinti = oge('span', 'rezervasyon-ayrinti');
  if (r.durum === 'kullanildi' && r.kullanmaZamani) {
    ayrinti.textContent = `Kullanıldı: ${saatYaz(r.kullanmaZamani)}`;
  } else if (r.durum === 'iptal' && r.iptalZamani) {
    ayrinti.textContent = `İptal: ${tarihKisaYaz(r.iptalZamani.slice(0, 10))} ${saatYaz(r.iptalZamani)}`;
  } else if (r.iptalEdilebilir) {
    ayrinti.textContent = `Son iptal: ${saatYaz(r.rezervasyonSon)}`;
  } else {
    ayrinti.textContent = 'İptal süresi doldu.';
  }
  satir.append(ayrinti);

  if (r.iptalEdilebilir) {
    const dugme = oge('button', 'dugme dugme-tehlike', 'İptal et');
    dugme.type = 'button';
    dugme.dataset.rezervasyonId = String(r.id);
    satir.append(dugme);
  }
  return satir;
}
