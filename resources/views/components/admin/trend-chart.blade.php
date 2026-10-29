{{-- نمودار ستونی روزانه (SVG سمت سرور، بدون کتابخانه)؛ $series: [['date' => Y-m-d, $key => n], ...] --}}
@props(['series', 'key', 'label', 'color' => 'primary'])

@php
    $values = array_column($series, $key);
    $max = max(1, max($values ?: [0]));
    $count = max(1, count($series));
    $width = 600;
    $height = 70;
    $gap = 2;
    $barWidth = ($width - $gap * ($count - 1)) / $count;
    $total = array_sum($values);
@endphp

<div {{ $attributes->class(['mb-3']) }}>
    <div class="d-flex justify-content-between small mb-1">
        <span class="fw-semibold">{{ $label }}</span>
        <span class="text-body-secondary">جمع {{ number_format($total) }} — بیشینه روزانه {{ number_format(max($values ?: [0])) }}</span>
    </div>
    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" width="100%" height="{{ $height }}" role="img"
         aria-label="{{ $label }} در {{ $count }} روز اخیر: جمع {{ number_format($total) }}" class="d-block" style="transform: scaleX(-1)">
        @foreach ($series as $i => $point)
            @php($h = $point[$key] > 0 ? max(2, $point[$key] / $max * ($height - 2)) : 1)
            <rect x="{{ $i * ($barWidth + $gap) }}" y="{{ $height - $h }}" width="{{ $barWidth }}" height="{{ $h }}" rx="1"
                  fill="var(--bs-{{ $point[$key] > 0 ? $color : 'secondary-bg' }})">
                <title>{{ \App\Support\Format::date(\Illuminate\Support\Carbon::parse($point['date'])) }}: {{ number_format($point[$key]) }}</title>
            </rect>
        @endforeach
    </svg>
</div>
