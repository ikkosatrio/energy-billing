/**
 * SPA Navigation Bridge
 * ----------------------------------------------------------------
 * This app uses Livewire's `wire:navigate` on internal links to get
 * SPA-like page transitions (no full page reload / no white-or-black
 * flash between pages).
 *
 * Because `wire:navigate` swaps the page content via a background
 * fetch + DOM morph instead of a full browser reload, a native
 * `DOMContentLoaded` listener only fires once -- on the very first
 * page load -- and will NOT fire again on subsequent in-app
 * navigations.
 *
 * CONVENTION FOR NEW MODULES (please follow this for new pages/widgets):
 * Any script that needs to (re)initialize DOM/plugins on every page
 * view -- e.g. Select2, DataTables, Flatpickr, a page-specific chart --
 * must register itself here instead of listening to `DOMContentLoaded`
 * directly:
 *
 *     App.onNavigate(function () {
 *         // (re)initialize your widget/component here.
 *         // Make sure the logic is idempotent (safe to run again),
 *         // e.g. guard DataTables with `$.fn.dataTable.isDataTable()`.
 *     });
 *
 * This guarantees the callback runs both on the first page load AND
 * on every subsequent `wire:navigate` transition, so new modules stay
 * SPA-compatible by default without extra plumbing.
 *
 * NOTE: this pattern is only meant for content that lives INSIDE the
 * page body (`@yield('content')`), which is replaced on every
 * navigation. Persistent layout elements (navbar, sidebar, topbar)
 * keep the same DOM nodes across navigations (Livewire morphs them in
 * place), so their listeners should still be bound once on
 * `DOMContentLoaded` only -- registering them here would double-bind
 * them on every navigation.
 */
window.App = window.App || {};

App.onNavigate = (function () {
  var callbacks = [];

  function run() {
    callbacks.forEach(function (callback) {
      try {
        callback();
      } catch (error) {
        console.error('[App.onNavigate] callback failed:', error);
      }
    });
  }

  // First page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }

  // Every subsequent Livewire `wire:navigate` page transition
  document.addEventListener('livewire:navigated', run);

  return function register(callback) {
    callbacks.push(callback);
  };
})();
