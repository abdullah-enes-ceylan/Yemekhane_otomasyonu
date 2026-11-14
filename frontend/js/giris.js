'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('giris-formu');
  const bildirim = document.getElementById('giris-bildirimi');
  const dugme = form.querySelector('button[type="submit"]');

  // Oturum zaten açıksa doğrudan menüye geç
  try {
    await Api.get('/auth/ben');
    location.replace('/menu.html');
    return;
  } catch {
    // Oturum yok: giriş formu gösterilir
  }

  form.addEventListener('submit', async (olay) => {
    olay.preventDefault();
    bildirimGizle(bildirim);
    dugme.disabled = true;
    dugme.setAttribute('aria-busy', 'true');

    try {
      await Api.post('/auth/giris', {
        eposta: form.eposta.value,
        sifre: form.sifre.value,
      });
      location.replace('/menu.html');
    } catch (hata) {
      bildirimGoster(bildirim, hata.message);
      form.sifre.value = '';
      form.sifre.focus();
    } finally {
      dugme.disabled = false;
      dugme.removeAttribute('aria-busy');
    }
  });
});
