<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  {{-- Aplikasi hanya punya tampilan terang; memberi tahu browser mencegah
       kilatan gelap pada frame pertama. --}}
  <meta name="color-scheme" content="light">
  <title>@yield('title', 'Dashboard') — {{ setting('app_name', config('app.name')) }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="preconnect" href="https://unpkg.com">

  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="{{ asset_v('assets/css/tailwind.css') }}">
  <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  @stack('styles')
  @livewireStyles
</head>
<body>

  <div class="shell">
    @include('partials.sidebar')
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <main class="main" id="page-content">
      <header class="page-header">
        <div>
          <div class="row">
            <button class="btn-icon sidebar-toggle" id="sidebar-toggle" aria-label="Buka menu">
              <i data-lucide="menu" style="width:16px;height:16px"></i>
            </button>
            <h1 class="page-title">@yield('title', 'Dashboard')</h1>
          </div>
          <div class="page-sub">@yield('subtitle')</div>
        </div>
        <div class="page-actions">
          @yield('actions')
        </div>
      </header>

      @yield('content')
    </main>
  </div>

  <script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

  <script src="{{ asset_v('assets/js/core/spa-navigate.js') }}"></script>
  <script defer src="{{ asset_v('assets/js/core/toast.js') }}"></script>
  <script defer src="{{ asset_v('assets/js/core/utils.js') }}"></script>

  {{-- Harus dimuat sebelum @livewireScripts: Alpine dibundel Livewire dan
       memanggil alpine:init saat boot, jadi Alpine.data() perlu sudah
       terdaftar sebelum itu. --}}
  <script src="{{ asset_v('assets/js/core/select-search.js') }}"></script>

  <script>
    // Lucide mengganti setiap <i data-lucide> dengan <svg> sekali jalan, jadi
    // harus dipanggil ulang setelah wire:navigate dan setelah Livewire
    // me-render ulang bagian DOM — kalau tidak, ikon hilang.
    (function () {
      function renderIcons() {
        if (window.lucide) window.lucide.createIcons();
      }

      App.onNavigate(renderIcons);

      document.addEventListener('livewire:init', function () {
        Livewire.hook('morph.added', renderIcons);
        Livewire.hook('morph.updated', renderIcons);

        // Jembatan notifikasi: komponen Livewire cukup memanggil
        // $this->dispatch('toast', type: 'success', message: '...').
        Livewire.on('toast', function (payload) {
          var data = Array.isArray(payload) ? payload[0] : payload;
          if (!data || !window.Toast) return;
          (Toast[data.type] || Toast.info)(data.message);
        });
      });
    })();

    // Sidebar mobile. Didelegasikan ke document karena sidebar ikut di-morph
    // oleh wire:navigate — listener yang menempel langsung ke tombol akan
    // hilang setelah transisi pertama.
    document.addEventListener('click', function (e) {
      var sidebar = document.getElementById('sidebar');
      var backdrop = document.getElementById('sidebar-backdrop');
      if (!sidebar || !backdrop) return;

      if (e.target.closest('#sidebar-toggle')) {
        sidebar.classList.add('open');
        backdrop.classList.add('open');
        return;
      }

      var clickedBackdrop = e.target === backdrop;
      var clickedMenuLink = e.target.closest('.sidebar-link, .sidebar-sublink');

      if (clickedBackdrop || clickedMenuLink) {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      document.getElementById('sidebar')?.classList.remove('open');
      document.getElementById('sidebar-backdrop')?.classList.remove('open');
    });
  </script>

  @stack('scripts')
  @livewireScripts
</body>
</html>
