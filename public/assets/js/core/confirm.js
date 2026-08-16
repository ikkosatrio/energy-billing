/**
 * ConfirmDialog — dialog konfirmasi bergaya aplikasi, pengganti window.confirm()
 * bawaan browser (dipakai lewat wire:confirm) yang tampilannya generik dan
 * tidak mengikuti identitas visual aplikasi.
 *
 * Usage:
 *   ConfirmDialog.show({
 *     title: 'Hapus pelanggan PT Sinar Abadi?',
 *     text: 'Invoice dan riwayat pembacaan meternya tidak ikut terhapus.',
 *     danger: true,                    // tombol konfirmasi merah — aksi merusak
 *     confirmText: 'Ya, Hapus',
 *     onConfirm: () => $wire.delete(id),
 *   });
 *
 * Dipanggil dari atribut Alpine (x-on:click), bukan wire:click langsung,
 * karena aksinya baru boleh dikirim ke server setelah dikonfirmasi di sini.
 *
 * Opsi `prompt` menambahkan satu textarea opsional — dipakai saat aksinya
 * perlu keterangan (mis. alasan pembatalan invoice). Isinya diteruskan
 * sebagai argumen pertama onConfirm:
 *
 *   ConfirmDialog.show({
 *     title: 'Batalkan invoice INV-001?',
 *     prompt: { label: 'Alasan pembatalan', placeholder: 'Opsional…' },
 *     onConfirm: (reason) => $wire.cancel(id, reason),
 *   });
 */
window.ConfirmDialog = (function () {
  'use strict';

  var overlay = null;
  var triggerEl = null;

  function close(cancelled) {
    if (!overlay) return;

    var toRemove = overlay;
    var onCancel = toRemove._onCancel;
    overlay = null;

    toRemove.remove();
    document.removeEventListener('keydown', onKeydown);

    // Fokus dikembalikan ke elemen yang membuka dialog supaya pengguna
    // keyboard tidak kehilangan posisi setelah dialog tertutup.
    if (triggerEl && typeof triggerEl.focus === 'function') triggerEl.focus();
    triggerEl = null;

    if (cancelled && typeof onCancel === 'function') onCancel();
  }

  function onKeydown(e) {
    if (e.key === 'Escape') close(true);
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function show(options) {
    options = options || {};

    // Hanya satu dialog aktif dalam satu waktu.
    close(false);

    triggerEl = document.activeElement;

    var box = document.createElement('div');
    box.className = 'confirm-overlay';
    box.innerHTML = [
      '<div class="confirm-box" role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title">',
      '  <div class="confirm-icon' + (options.danger ? ' is-danger' : '') + '">',
      '    <i data-lucide="' + escapeHtml(options.icon || (options.danger ? 'triangle-alert' : 'circle-help')) + '"></i>',
      '  </div>',
      '  <div class="confirm-title" id="confirm-dialog-title">' + escapeHtml(options.title || 'Konfirmasi') + '</div>',
      options.text ? '  <div class="confirm-text">' + escapeHtml(options.text) + '</div>' : '',
      options.prompt ? [
        '  <div class="confirm-prompt">',
        options.prompt.label ? '    <label class="field-label" for="confirm-dialog-prompt">' + escapeHtml(options.prompt.label) + '</label>' : '',
        '    <textarea id="confirm-dialog-prompt" class="input" rows="3" data-role="prompt"',
        '              placeholder="' + escapeHtml(options.prompt.placeholder || '') + '"></textarea>',
        '  </div>',
      ].join('\n') : '',
      '  <div class="confirm-actions">',
      '    <button type="button" class="btn btn-outline" data-role="cancel">' + escapeHtml(options.cancelText || 'Batal') + '</button>',
      '    <button type="button" class="btn ' + (options.danger ? 'btn-danger' : 'btn-primary') + '" data-role="confirm">' + escapeHtml(options.confirmText || 'Ya, Lanjutkan') + '</button>',
      '  </div>',
      '</div>',
    ].join('\n');

    box._onCancel = options.onCancel;

    box.addEventListener('mousedown', function (e) {
      // Klik pada latar (bukan kotaknya) membatalkan, sama seperti perilaku
      // .modal-overlay yang sudah dipakai untuk form di aplikasi ini.
      if (e.target === box) close(true);
    });

    box.querySelector('[data-role="cancel"]').addEventListener('click', function () {
      close(true);
    });
    box.querySelector('[data-role="confirm"]').addEventListener('click', function () {
      var onConfirm = options.onConfirm;
      // Nilai prompt dibaca SEBELUM close() karena close() melepas elemennya.
      var field = box.querySelector('[data-role="prompt"]');
      var value = field ? field.value.trim() : null;

      close(false);
      if (typeof onConfirm === 'function') onConfirm(value);
    });

    document.body.appendChild(box);
    overlay = box;
    document.addEventListener('keydown', onKeydown);

    if (window.lucide) window.lucide.createIcons();

    // Kalau ada isian, fokus ke sana — pengguna sedang diminta mengetik,
    // bukan sekadar menyetujui.
    var promptField = box.querySelector('[data-role="prompt"]');
    (promptField || box.querySelector('[data-role="confirm"]')).focus();
  }

  return { show: show, close: close };
})();
