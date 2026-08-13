@props([
    // Daftar opsi: array/Collection berisi ['value' => .., 'label' => .., 'sub' => ..]
    // 'sub' opsional — tampil sebagai baris kecil di bawah label dan ikut dicari.
    'options' => [],
    'placeholder' => '— pilih —',
    'searchPlaceholder' => 'Ketik untuk mencari…',
    'emptyText' => 'Tidak ada yang cocok.',
    // null = otomatis: kolom pencarian muncul hanya bila opsinya cukup banyak.
    'searchable' => null,
    'invalid' => false,
])

@php
    // Ambang munculnya kolom pencarian. Daftar 5 opsi ke bawah — umumnya
    // filter status atau enum — sudah terbaca sekali lihat, dan kolom
    // pencarian di situ justru menambah langkah. Di atas itu (pelanggan,
    // meter, invoice, periode) kolom cari selalu muncul.
    //
    // Bisa dipaksa per pemakaian: :searchable="true" / :searchable="false".
    $searchThreshold = 5;

    $items = collect($options)->map(fn ($o) => [
        'value' => (string) ($o['value'] ?? ''),
        'label' => (string) ($o['label'] ?? ''),
        'sub' => (string) ($o['sub'] ?? ''),
    ])->values()->all();

    $showSearch = $searchable ?? (count($items) > $searchThreshold);

    // Nama properti Livewire diambil dari wire:model pada pemanggilan komponen,
    // supaya nilainya bisa dibaca/ditulis lewat $wire tanpa input tersembunyi.
    $wireModel = $attributes->wire('model');
    $prop = $wireModel->value();
    $live = $wireModel->hasModifier('live');
@endphp

<div
    x-data="selectSearch({
        options: @js($items),
        prop: @js($prop),
        live: @js($live),
        placeholder: @js($placeholder),
        searchable: @js($showSearch)
    })"
    x-on:keydown.escape.stop="close()"
    x-on:click.outside="close()"
    {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur'])->merge(['class' => 'select-search']) }}
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        x-on:keydown.enter.prevent="enter()"
        :aria-expanded="open"
        aria-haspopup="listbox"
        class="select-search-trigger @if ($invalid) is-invalid @endif"
        :class="{ 'is-open': open, 'is-placeholder': !selected }"
    >
        <span class="select-search-label" x-text="label"></span>
        <i data-lucide="chevron-down" class="select-search-caret"></i>
    </button>

    <div x-show="open" x-cloak class="select-search-menu" role="listbox">
        @if ($showSearch)
            <div class="select-search-field">
                <i data-lucide="search" class="select-search-icon"></i>
                <input
                    type="text"
                    x-ref="search"
                    x-model="search"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="enter()"
                    x-on:input="highlighted = 0"
                    placeholder="{{ $searchPlaceholder }}"
                >
            </div>
        @endif

        <div class="select-search-list" x-ref="list">
            <template x-for="(option, index) in filtered" :key="option.value">
                <div
                    role="option"
                    :aria-selected="isSelected(option)"
                    class="select-search-option"
                    :class="{ 'is-highlighted': index === highlighted, 'is-selected': isSelected(option) }"
                    x-on:click="choose(option)"
                    x-on:mouseenter="highlighted = index"
                >
                    <div>
                        <div class="select-search-option-label" x-text="option.label"></div>
                        <div class="select-search-option-sub" x-show="option.sub" x-text="option.sub"></div>
                    </div>
                    <i data-lucide="check" class="select-search-check" x-show="isSelected(option)"></i>
                </div>
            </template>

            <div class="select-search-empty" x-show="filtered.length === 0">
                {{ $emptyText }}
            </div>
        </div>
    </div>
</div>
