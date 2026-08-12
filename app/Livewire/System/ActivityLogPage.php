<?php

namespace App\Livewire\System;

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLogPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $actionFilter = '';

    public ?int $userFilter = null;

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.system.activity-log-page', [
            'logs' => ActivityLog::query()
                ->with('user:id,name,username')
                ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%"))
                ->when($this->actionFilter, fn ($q) => $q->where('action', $this->actionFilter))
                ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
                ->whereDate('created_at', '>=', $this->from)
                ->whereDate('created_at', '<=', $this->to)
                ->orderByDesc('created_at')
                ->paginate(30),
            // Daftar aksi diambil dari data yang benar-benar tercatat, bukan
            // enum tetap, supaya aksi baru otomatis muncul di filter.
            'actions' => ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
