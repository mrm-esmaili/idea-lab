{{-- فیلدهای محافظت فرم عمومی؛ App\Http\Middleware\GuardPublicForm --}}
<div style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap" aria-hidden="true">
    <label for="{{ config('security.honeypot_field') }}-{{ $attributes->get('id', 'f') }}">این فیلد را خالی بگذارید</label>
    <input type="text" id="{{ config('security.honeypot_field') }}-{{ $attributes->get('id', 'f') }}" name="{{ config('security.honeypot_field') }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="_form_started" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->getTimestamp()) }}">
