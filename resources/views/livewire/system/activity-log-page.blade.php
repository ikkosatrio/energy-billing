<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari Keterangan</label>
                <input type="text" class="input" wire:model.live.debounce.400ms="search">
            </div>
            <div class="field">
                <label class="field-label">Aksi</label>
                <x-select-search
                    wire:model.live="actionFilter"
                    placeholder="Semua aksi"
                    search-placeholder="Cari aksi…"
                    :options="$actions
                        ->map(fn ($action) => ['value' => $action, 'label' => $action])
                        ->prepend(['value' => '', 'label' => 'Semua aksi'])" />
            </div>
            <div class="field">
                <label class="field-label">User</label>
                <x-select-search
                    wire:model.live="userFilter"
                    placeholder="Semua user"
                    search-placeholder="Cari nama user…"
                    :options="$users
                        ->map(fn ($user) => ['value' => $user->id, 'label' => $user->name])
                        ->prepend(['value' => '', 'label' => 'Semua user'])" />
            </div>
            <div class="field">
                <label class="field-label">Dari</label>
                <input type="date" class="input mono" wire:model.live="from">
            </div>
            <div class="field">
                <label class="field-label">Sampai</label>
                <input type="date" class="input mono" wire:model.live="to">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Keterangan</th>
                        <th>Objek</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="mono text-muted">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</td>
                            <td>
                                {{ $log->user?->name ?? 'Sistem' }}
                                @if ($log->user)
                                    <div class="sub mono">{{ $log->user->username }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-neutral">{{ $log->action }}</span></td>
                            <td>{{ $log->description ?? '—' }}</td>
                            <td class="text-muted mono" style="font-size:11px">
                                {{ $log->model_type ? class_basename($log->model_type).'#'.$log->model_id : '—' }}
                            </td>
                            <td class="text-muted mono">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">Tidak ada aktivitas pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div style="margin-top:16px">{{ $logs->links() }}</div>
        @endif
    </div>

</div>
