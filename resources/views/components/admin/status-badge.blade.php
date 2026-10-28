@props(['group', 'value'])

@php
    $color = match ($value) {
        'new', 'requested', 'draft', 'unpaid', 'cancelled' => 'secondary',
        'contacted', 'testing', 'building' => 'info',
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
