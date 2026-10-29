@props(['group', 'value'])

@php
    $color = match ($value) {
        'new', 'requested', 'draft', 'unpaid', 'cancelled', 'ended', 'skipped' => 'secondary',
        'contacted', 'testing', 'building', 'planned' => 'info',
        'running' => 'success',
        'paused' => 'warning',
        'interested' => 'primary',
        'pending', 'partially_refunded', 'queued', 'sending' => 'warning',
        'approved' => 'primary',
        'refunded', 'completed', 'decided' => 'dark',
        'continue', 'modify' => 'primary',
        'execute' => 'success',
        'stop' => 'danger',
        'prepaid', 'customer', 'validated', 'launched', 'paid', 'sent' => 'success',
        'rejected', 'failed' => 'danger',
        default => 'light',
    };
@endphp

<span {{ $attributes->class(['badge', "text-bg-{$color}"]) }}>
    {{ \App\Support\Format::status($group, $value) }}
</span>
