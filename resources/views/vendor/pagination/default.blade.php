{{--
  Paginator bawaan Laravel merender markup Tailwind yang mengandalkan
  preflight; preflight dimatikan di project ini, jadi tampilannya rusak.
  View ini memakai kelas dari design system sendiri.
--}}
@if ($paginator->hasPages())
    <nav class="row" style="justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div class="text-muted" style="font-size:13px">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            dari {{ $paginator->total() }} data
        </div>

        <div class="row" style="gap:6px">
            @if ($paginator->onFirstPage())
                <span class="btn btn-outline btn-sm" style="opacity:.5">Sebelumnya</span>
            @else
                <button type="button" class="btn btn-outline btn-sm" wire:click="previousPage" wire:loading.attr="disabled">
                    Sebelumnya
                </button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="text-faint" style="padding:0 4px">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary btn-sm mono">{{ $page }}</span>
                        @else
                            <button type="button" class="btn btn-outline btn-sm mono" wire:click="gotoPage({{ $page }})">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" class="btn btn-outline btn-sm" wire:click="nextPage" wire:loading.attr="disabled">
                    Berikutnya
                </button>
            @else
                <span class="btn btn-outline btn-sm" style="opacity:.5">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
