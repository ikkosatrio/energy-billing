@props(['status' => null, 'showValue' => true])

{{--
    Kekuatan sinyal WiFi perangkat.

    Angka dBm sendiri tidak berarti apa-apa bagi kebanyakan orang — "-62"
    tidak memberi tahu apakah itu bagus atau bermasalah. Empat batang naik
    memberi jawaban itu dalam sekali lihat, dan angkanya tetap ditampilkan
    untuk teknisi yang memang membacanya.

    Batangnya sengaja bukan ikon: tingginya bertambah mengikuti kekuatan,
    sehingga bentuknya sendiri sudah menyampaikan besarannya.
--}}
@php
    $quality = $status?->signal_quality ?? ['level' => 0, 'label' => 'Belum ada data', 'tone' => 'unknown'];
@endphp

<span class="signal" title="{{ $quality['label'] }}{{ $status?->signal_dbm !== null ? ' · '.$status->signal_dbm.' dBm' : '' }}">
    <span class="signal-bars signal-{{ $quality['tone'] }}" aria-hidden="true">
        @foreach (range(1, 4) as $bar)
            <i class="signal-bar{{ $bar <= $quality['level'] ? ' is-on' : '' }}"></i>
        @endforeach
    </span>

    @if ($showValue)
        <span class="signal-value">
            @if ($status?->signal_dbm !== null)
                {{ $status->signal_dbm }} <span class="signal-unit">dBm</span>
            @else
                —
            @endif
        </span>
    @endif

    <span class="sr-only">{{ $quality['label'] }}</span>
</span>
