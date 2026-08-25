{{--
  Sidebar portal, dirender dari config/portal-menu.php.

  Penyaringnya sama dengan sidebar staf (Route::has + hasAnyPermission), tapi
  sumber menunya berbeda file supaya menu admin tidak pernah bisa muncul di
  sini hanya karena satu permission salah tulis.

  Yang khas portal: badge jumlah pelanggan yang diakses. Akun yang memegang
  beberapa pelanggan perlu tahu angka yang dilihatnya gabungan berapa lokasi —
  tanpa itu, total kWh di dashboard mudah disalahartikan sebagai satu lokasi.
--}}
@php
    $akun = auth('customer')->user();

    $visible = fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route'])
        && $akun?->hasAnyPermission($item['permits'] ?? []);

    $isActive = fn (array $item) => request()->routeIs($item['active'] ?? $item['route']);

    $jumlahPelanggan = count($akun?->accessibleCustomerIds() ?? []);
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
            <div class="sidebar-brand-name">Portal Pelanggan</div>
            <div class="sidebar-brand-sub">{{ setting('company_name') }}</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        @foreach (config('portal-menu') as $entry)
            @if (!isset($entry['items']))
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

    <div class="sidebar-user">
        <div class="sidebar-avatar">{{ $akun?->initials }}</div>
        <div style="flex:1;min-width:0">
            <div class="sidebar-user-name">{{ $akun?->name }}</div>
            <div class="sidebar-user-role">
                {{ $jumlahPelanggan }} {{ $jumlahPelanggan > 1 ? 'lokasi pelanggan' : 'pelanggan' }}
            </div>
        </div>
        <form method="POST" action="{{ route('portal.logout') }}">
            @csrf
            <button type="submit" class="sidebar-logout" title="Keluar">
                <i data-lucide="log-out" style="width:16px;height:16px"></i>
            </button>
        </form>
    </div>
</aside>
