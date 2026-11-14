'use strict';

/* Yalnızca gösterim biçimi; hesap ve kural yok. */

const paraBicimi = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });

function paraYaz(tutar) {
  return paraBicimi.format(Number(tutar));
}

/* "2026-11-15" → yerel tarih (saat dilimi kaymasın diye parçalardan kurulur) */
function tarihNesnesi(gun) {
  const [yil, ay, gunNo] = gun.split('-').map(Number);
  return new Date(yil, ay - 1, gunNo);
}

function tarihYaz(gun) {
  return tarihNesnesi(gun).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', weekday: 'long' });
}

function tarihKisaYaz(gun) {
  return tarihNesnesi(gun).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' });
}

/* "2026-11-15 10:00:00" → "10:00" */
function saatYaz(tarihSaat) {
  return tarihSaat ? tarihSaat.slice(11, 16) : '';
}

/* Kısa yardımcı: etiket + metin ile öğe oluşturur (metin her zaman textContent) */
function oge(etiket, sinif, metin) {
  const el = document.createElement(etiket);
  if (sinif) el.className = sinif;
  if (metin !== undefined) el.textContent = metin;
  return el;
}
