/**
 * Utility Module - Shared helper functions
 */
window.App = window.App || {};

// SweetAlert2 alias for consistency across modules
window.AppSwal = window.Swal;

App.Utils = (function () {
  'use strict';

  // =========================================================
  // 1. CORE INTERNAL (Menangani Styling, Warna, & Logic Swal Terpusat)
  //    Hanya menerima 1 bentuk input: object config. Tidak ada overloading.
  // =========================================================
  function _baseSwal(config) {
    config = config || {};

    var primaryColor = getComputedStyle(document.documentElement)
      .getPropertyValue('--color-primary').trim() || '#008d4c';

    var isConfirm = !!config.isConfirm;

    return AppSwal.fire({
      title: config.title || (isConfirm ? 'Konfirmasi' : 'Informasi'),
      text: config.text || undefined,
      html: config.html || undefined,
      icon: config.icon !== undefined ? config.icon : (isConfirm ? 'question' : 'info'),
      showCancelButton: isConfirm,
      confirmButtonText: config.confirmText || (isConfirm ? 'Ya, Lanjutkan' : 'OK'),
      cancelButtonText: config.cancelText || 'Batal',
      confirmButtonColor: config.confirmColor || primaryColor,
      cancelButtonColor: config.cancelColor || '#ffffff',
      reverseButtons: config.reverseButtons !== undefined ? config.reverseButtons : true,
      didOpen: function () {
        var popup = AppSwal.getPopup();
        if (popup) {
          popup.style.borderRadius = '20px';
          popup.style.padding = '28px 32px';
        }

        // Tombol Cancel (hanya render kalau mode 2 tombol)
        var cancelBtn = AppSwal.getCancelButton();
        if (cancelBtn && isConfirm) {
          cancelBtn.style.border = '1px solid #cbd5e1';
          cancelBtn.style.borderRadius = '8px';
          cancelBtn.style.fontWeight = '600';
          cancelBtn.style.padding = '8px 18px';
          cancelBtn.style.backgroundColor = config.cancelColor || '#ffffff';
          cancelBtn.style.color = config.cancelColor ? '#ffffff' : '#334155';
        }

        // Tombol Confirm
        var confirmBtn = AppSwal.getConfirmButton();
        if (confirmBtn) {
          confirmBtn.style.borderRadius = '8px';
          confirmBtn.style.fontWeight = '600';
          confirmBtn.style.padding = '8px 18px';
        }

        if (typeof config.didOpen === 'function') config.didOpen();
      }
    }).then(function (result) {
      if (result.isConfirmed && typeof config.onConfirm === 'function') {
        config.onConfirm();
      } else if (result.isDismissed && typeof config.onCancel === 'function') {
        config.onCancel();
      }
      return result;
    });
  }

  // =========================================================
  // 2. HELPER: normalisasi (title, text, onConfirm) -> config object
  // =========================================================
  function _normalize(titleOrOptions, text, onConfirm) {
    if (typeof titleOrOptions === 'object' && titleOrOptions !== null) {
      return titleOrOptions;
    }
    return {
      title: titleOrOptions,
      text: text,
      onConfirm: onConfirm
    };
  }

  // =========================================================
  // 3. HELPER SPESIFIK (Intuitive & Expressive)
  // =========================================================

  function alertSuccess(titleOrOptions, text, onConfirm) {
    var cfg = _normalize(titleOrOptions, text, onConfirm);
    cfg.icon = cfg.icon || 'success';
    cfg.isConfirm = false;
    return _baseSwal(cfg);
  }

  function alertError(titleOrOptions, text, onConfirm) {
    var cfg = _normalize(titleOrOptions, text, onConfirm);
    cfg.icon = cfg.icon || 'error';
    cfg.isConfirm = false;
    return _baseSwal(cfg);
  }

  function alertInfo(titleOrOptions, text, onConfirm) {
    var cfg = _normalize(titleOrOptions, text, onConfirm);
    cfg.icon = cfg.icon || 'info';
    cfg.isConfirm = false;
    return _baseSwal(cfg);
  }

  function confirm(options) {
    options = options || {};
    options.isConfirm = true;
    return _baseSwal(options);
  }

  /**
   * Show loading modal with spinner (SweetAlert2)
   */
  function showLoadingMessage(message) {
    message = message || 'Please wait...';
    AppSwal.fire({
      title: message,
      icon: 'info',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: function () {
        AppSwal.showLoading();
      }
    });
  }

  /**
   * Hide loading modal if visible and in loading state
   */
  function hideLoadingMessage() {
    setTimeout(function () {
      if (AppSwal.isVisible() && AppSwal.isLoading()) {
        AppSwal.close();
      }
    }, 500);
  }

  /**
   * Format ISO date string to display format (e.g., "01 Jul 2026")
   */
  function formatDate(isoDate) {
    if (!isoDate) return '';
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var parts = isoDate.split('-');
    if (parts.length < 3) return isoDate;
    return parts[2] + ' ' + months[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
  }

  /**
   * Parse date string to timestamp for comparison/sorting
   */
  function parseDate(dateString) {
    var d = new Date(dateString);
    return isNaN(d.getTime()) ? 0 : d.getTime();
  }

  /**
   * Escape string for safe HTML attribute insertion
   */
  function escapeAttr(value) {
    if (!value) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  /**
   * Refresh visible table row numbers after filtering
   */
  function refreshRowNumbers(tbodyId) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    var rows = tbody.querySelectorAll('tr');
    var num = 1;
    rows.forEach(function (row) {
      if (row.style.display !== 'none') {
        var td = row.querySelector('td:first-child');
        if (td) td.textContent = num++;
      }
    });
  }

  /**
   * Generic filter function for table rows by data attributes
   */
  function filterTableRows(tbodyId, filters, searchInput) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    var searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
    var rows = tbody.querySelectorAll('tr');

    rows.forEach(function (row) {
      var visible = true;

      // Check each filter
      for (var key in filters) {
        if (filters[key] && row.dataset[key] !== filters[key]) {
          visible = false;
          break;
        }
      }

      // Search filter
      if (visible && searchTerm) {
        visible = row.textContent.toLowerCase().includes(searchTerm);
      }

      row.style.display = visible ? '' : 'none';
    });
  }

  /**
   * Show/hide modal overlay
   */
  function showModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
  }

  function hideModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
  }

  /**
   * Close modal when clicking on overlay background
   */
  function setupModalOverlayClose(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.addEventListener('click', function (e) {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  }

  /**
   * Generic tab switching
   */
  function switchTab(tabBtns, contentPanels, activeTabBtn, activePanel) {
    tabBtns.forEach(function (btn) { btn.classList.remove('active'); });
    contentPanels.forEach(function (panel) { panel.style.display = 'none'; });
    if (activeTabBtn) activeTabBtn.classList.add('active');
    if (activePanel) activePanel.style.display = 'block';
  }

  /**
   * Format number with 2 decimal places
   */
  function formatScore(value) {
    var num = parseFloat(value);
    return isNaN(num) ? '0.00' : num.toFixed(2);
  }

  // =========================================================
  // TOAST (Notifikasi ringan, auto-dismiss, pojok layar)
  //    Perlu CSS pendamping (.colored-toast)
  // =========================================================
  var _toastMixin = AppSwal.mixin({
    toast: true,
    position: 'top-end',
    customClass: { popup: 'colored-toast' },
    iconColor: 'white',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: function (toastEl) {
      toastEl.addEventListener('mouseenter', AppSwal.stopTimer);
      toastEl.addEventListener('mouseleave', AppSwal.resumeTimer);
    }
  });

  // 🔹 Toast — title saja atau object untuk opsi lebih (icon, timer, position, dst)
  function toast(titleOrOptions, icon) {
    var opts = (typeof titleOrOptions === 'object' && titleOrOptions !== null)
      ? titleOrOptions
      : { title: titleOrOptions, icon: icon || 'success' };
    return _toastMixin.fire(opts);
  }

  return {
    alertSuccess: alertSuccess,
    alertError: alertError,
    alertInfo: alertInfo,
    confirm: confirm,
    custom: _baseSwal,
    toast: toast,
    formatDate: formatDate,
    parseDate: parseDate,
    escapeAttr: escapeAttr,
    refreshRowNumbers: refreshRowNumbers,
    filterTableRows: filterTableRows,
    showModal: showModal,
    hideModal: hideModal,
    setupModalOverlayClose: setupModalOverlayClose,
    switchTab: switchTab,
    formatScore: formatScore,
    showLoadingMessage: showLoadingMessage,
    hideLoadingMessage: hideLoadingMessage,
  };
})();
