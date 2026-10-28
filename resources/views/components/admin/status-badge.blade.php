@props(['group', 'value'])

@php
    $color = match ($value) {
        'new', 'requested', 'draft' => 'secondary',
        'contacted', 'testing', 'building' => 'info',
        'interested' => 'primary',
        'prepaid', 'customer', 'converted', 'validated', 'launched' => 'success',
        'rejected' => 'danger',
        default => 'light',
    };
@endphp

<span {{ $attributes->class(['badge', "text-bg-{$color}"]) }}>
    {{ \App\Support\Format::status($group, $value) }}
</span>
