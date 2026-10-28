@props(['group', 'value'])

@php
    $color = match ($value) {
        'new', 'requested', 'draft', 'unpaid', 'cancelled', 'ended' => 'secondary',
        'contacted', 'testing', 'building', 'planned' => 'info',
        'running' => 'success',
        'paused' => 'warning',
        'interested' => 'primary',
        'pending' => 'warning',
        'prepaid', 'customer', 'validated', 'launched', 'paid' => 'success',
        'rejected', 'failed' => 'danger',
        default => 'light',
    };
@endphp

<span {{ $attributes->class(['badge', "text-bg-{$color}"]) }}>
    {{ \App\Support\Format::status($group, $value) }}
</span>
