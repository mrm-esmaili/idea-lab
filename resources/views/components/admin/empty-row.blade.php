@props(['colspan', 'icon' => 'bi-inbox'])

<tr>
    <td colspan="{{ $colspan }}" class="text-center text-body-secondary py-5">
        <i class="bi {{ $icon }} fs-2 d-block mb-2"></i>
        {{ $slot }}
    </td>
</tr>
