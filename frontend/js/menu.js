'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  try {
    const kullanici = await oturumuBaslat();
    document.getElementById('karsilama').textContent = `Hoş geldin, ${kullanici.ad}.`;
  } catch (hata) {
    bildirimGoster(document.getElementById('sayfa-bildirimi'), hata.message);
  }
});
