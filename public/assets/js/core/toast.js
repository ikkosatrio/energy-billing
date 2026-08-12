/**
 * Toast — lightweight notification system to replace alert()
 * Usage:
 *   Toast.success('Data saved successfully');
 *   Toast.error('Something went wrong');
 *   Toast.info('Exporting to XLS...');
 *   Toast.warning('Please fill all required fields');
 */
window.Toast = (function () {
  'use strict';

  var container = null;

  // Nama ikon Lucide. Dirender lewat <i data-lucide>, lalu diganti menjadi
  // <svg> oleh lucide.createIcons() yang dipanggil setelah toast disisipkan.
  var icons = {
    success: 'circle-check',
    error:   'circle-x',
    warning: 'triangle-alert',
    info:    'info',
  };

  var colors = {
    success: { bg: '#f0fdf4', border: '#86efac', icon: '#16a34a', text: '#15803d' },
    error:   { bg: '#fef2f2', border: '#fca5a5', icon: '#dc2626', text: '#b91c1c' },
    warning: { bg: '#fffbeb', border: '#fcd34d', icon: '#d97706', text: '#92400e' },
    info:    { bg: '#eff6ff', border: '#93c5fd', icon: '#2563eb', text: '#1d4ed8' },
  };

  function getContainer() {
    if (!container) {
      container = document.getElementById('toast-container');
      if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = [
          'position:fixed',
          'top:70px',
          'right:20px',
          'z-index:99999',
          'display:flex',
          'flex-direction:column',
          'gap:10px',
          'pointer-events:none',
          'max-width:360px',
          'width:calc(100vw - 40px)',
        ].join(';');
        document.body.appendChild(container);
      }
    }
    return container;
  }

  function show(message, type, duration) {
    type = type || 'info';
    duration = duration !== undefined ? duration
      : (window.AppConfig && window.AppConfig.toast ? window.AppConfig.toast.duration : 3000);

    var c = colors[type] || colors.info;
    var toastEl = document.createElement('div');

    toastEl.style.cssText = [
      'display:flex',
      'align-items:flex-start',
      'gap:12px',
      'background:' + c.bg,
      'border:1px solid ' + c.border,
      'border-left:4px solid ' + c.icon,
      'border-radius:10px',
      'padding:12px 14px',
      'box-shadow:0 4px 16px rgba(0,0,0,0.1)',
      'pointer-events:all',
      'opacity:0',
      'transform:translateX(20px)',
      'transition:all 0.25s ease',
      'cursor:pointer',
    ].join(';');

    toastEl.innerHTML = [
      '<i data-lucide="' + icons[type] + '" style="color:' + c.icon + ';width:17px;height:17px;margin-top:1px;flex-shrink:0"></i>',
      '<span style="font-size:13px;font-weight:500;color:' + c.text + ';line-height:1.5;flex:1">' + message + '</span>',
      '<button style="background:none;border:none;cursor:pointer;color:' + c.icon + ';opacity:0.6;padding:0;flex-shrink:0;line-height:1;display:flex" onclick="this.parentNode.remove()">',
        '<i data-lucide="x" style="width:14px;height:14px"></i>',
      '</button>',
    ].join('');

    getContainer().appendChild(toastEl);

    // Toast dibuat setelah createIcons() awal berjalan, jadi ikonnya harus
    // dirender ulang khusus untuk elemen ini.
    if (window.lucide) window.lucide.createIcons();

    // Animate in
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        toastEl.style.opacity = '1';
        toastEl.style.transform = 'translateX(0)';
      });
    });

    // Auto dismiss
    if (duration > 0) {
      setTimeout(function () {
        toastEl.style.opacity = '0';
        toastEl.style.transform = 'translateX(20px)';
        setTimeout(function () { toastEl.remove(); }, 260);
      }, duration);
    }

    return toastEl;
  }

  return {
    show:    function (msg, type, dur) { return show(msg, type, dur); },
    success: function (msg, dur)       { return show(msg, 'success', dur); },
    error:   function (msg, dur)       { return show(msg, 'error',   dur); },
    warning: function (msg, dur)       { return show(msg, 'warning', dur); },
    info:    function (msg, dur)       { return show(msg, 'info',    dur); },
  };
})();
