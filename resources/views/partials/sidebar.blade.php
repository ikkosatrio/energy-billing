{{--
  Sidebar dirender sepenuhnya dari config/menu.php.

  Dua penyaring diterapkan ke setiap item:
    1. Route::has() — item dilewati bila route-nya belum terdaftar, sehingga
       menu boleh memuat modul yang belum dibangun tanpa memicu error.
    2. hasAnyPermission() — item disembunyikan bila user tidak berhak.

  Grup memakai <details> agar buka/tutupnya ditangani browser, tidak perlu JS,
  dan tetap berfungsi setelah transisi wire:navigate.
--}}
@php
    $user = auth()->user();

    /** Item tampil bila route-nya ada DAN user berhak mengaksesnya. */
    $visible = fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route'])
        && $user?->hasAnyPermission($item['permits'] ?? []);

    $isActive = fn (array $item) => request()->routeIs($item['active'] ?? $item['route']);
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-mark">
            @if ($logo = setting('company_logo'))
                <img src="{{ \Illuminate\Support\Facades\Storage::url($logo) }}" alt="{{ setting('company_name') }}">
            @else
                <i data-lucide="zap" style="width:19px;height:19px"></i>
            @endif
        </div>
        <div>
            <div class="sidebar-brand-name">{{ setting('app_name', config('app.name')) }}</div>
            <div class="sidebar-brand-sub">{{ setting('company_name') }}</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        @foreach (config('menu') as $entry)
            @if (!isset($entry['items']))
                {{-- Menu tunggal, mis. Dashboard --}}
                @if ($visible($entry))
                    <a href="{{ route($entry['route']) }}" wire:navigate
                       class="sidebar-link{{ $isActive($entry) ? ' active' : '' }}">
                        <i data-lucide="{{ $entry['icon'] }}" class="sidebar-icon"></i>
                        <span>{{ $entry['title'] }}</span>
                    </a>
                @endif
            @else
                @php
                    $items = array_values(array_filter($entry['items'], $visible));
                    $groupActive = collect($items)->contains($isActive);
                @endphp

                @if ($items)
                    <details class="sidebar-group" {{ $groupActive ? 'open' : '' }}>
                        <summary class="sidebar-group-toggle{{ $groupActive ? ' has-active' : '' }}">
                            <i data-lucide="{{ $entry['icon'] }}" class="sidebar-icon"></i>
                            <span class="label">{{ $entry['title'] }}</span>
                            <span class="sidebar-count">{{ count($items) }}</span>
                            <i data-lucide="chevron-down" class="sidebar-chevron"></i>
                        </summary>

                        <div class="sidebar-sub">
                            @foreach ($items as $item)
                                <a href="{{ route($item['route']) }}" wire:navigate
                                   class="sidebar-sublink{{ $isActive($item) ? ' active' : '' }}">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ $item['title'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endif
        @endforeach
    </nav>

    @include('partials.gateway-status')

    <div class="sidebar-user">
        <div class="sidebar-avatar">{{ $user?->initials }}</div>
        <div style="flex:1;min-width:0">
            <div class="sidebar-user-name">{{ $user?->name }}</div>
            <div class="sidebar-user-role">{{ $user?->role?->name ?? '—' }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-logout" title="Logout">
                <i data-lucide="log-out" style="width:16px;height:16px"></i>
            </button>
        </form>
    </div>
</aside>
