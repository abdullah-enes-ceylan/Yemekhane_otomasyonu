'use strict';

/*
 * API ile konuşan tek yer. İş kuralı içermez: istek gönderir, JSON'u döndürür,
 * hata yanıtındaki "mesaj" alanını (ör. yordamdan gelen "Bakiye yetersiz.") ApiHatasi olarak fırlatır.
 */

class ApiHatasi extends Error {
  constructor(mesaj, durumKodu) {
    super(mesaj);
    this.durumKodu = durumKodu;
  }
}

function girisSayfasindaMi() {
  return location.pathname === '/' || location.pathname.endsWith('/index.html');
}

async function apiIstegi(yontem, yol, govde) {
  const ayarlar = {
    method: yontem,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  };
  if (govde !== undefined) {
    ayarlar.headers['Content-Type'] = 'application/json';
    ayarlar.body = JSON.stringify(govde);
  }

  let yanit;
  try {
    yanit = await fetch('/api' + yol, ayarlar);
  } catch {
    throw new ApiHatasi('Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.', 0);
  }

  if (yanit.status === 502 || yanit.status === 503) {
    throw new ApiHatasi('Uygulama servisine şu an ulaşılamıyor. Lütfen biraz sonra tekrar deneyin.', yanit.status);
  }

  const veri = yanit.status === 204 ? null : await yanit.json().catch(() => null);

  if (!yanit.ok) {
    if (yanit.status === 401 && !girisSayfasindaMi()) {
      location.replace('/index.html');
    }
    throw new ApiHatasi(veri?.mesaj ?? 'Beklenmeyen bir hata oluştu.', yanit.status);
  }

  return veri;
}

const Api = {
  get: (yol) => apiIstegi('GET', yol),
  post: (yol, govde) => apiIstegi('POST', yol, govde),
  put: (yol, govde) => apiIstegi('PUT', yol, govde),
  sil: (yol) => apiIstegi('DELETE', yol),
};

/* Bildirim kutusu: metin her zaman textContent ile yazılır (XSS'e karşı). */
function bildirimGoster(kutu, mesaj, tur = 'hata') {
  kutu.textContent = mesaj;
  kutu.className = 'bildirim bildirim-' + tur;
  kutu.setAttribute('role', tur === 'hata' ? 'alert' : 'status');
  kutu.hidden = false;
}

function bildirimGizle(kutu) {
  kutu.hidden = true;
  kutu.textContent = '';
}
