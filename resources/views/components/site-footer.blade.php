{{-- پاورقی صفحات عمومی (مراحل ۱۱۵ و ۱۱۶): پیگیری سفارش، پشتیبانی، شرکت و نماد اعتماد --}}
{{-- صفحه فرود پشتیبانی و شرکت را در بدنه خود نشان می‌دهد و این دو بخش را خاموش می‌کند --}}
@props(['support' => true, 'company' => true])

@php
    $fa = fn ($value) => \App\Support\Format::faDigits($value);
    $showCompany = (bool) $company;
    $site = app(\App\Services\SiteSettings::class);
    $channels = $support ? $site->supportChannels() : [];
    $helpText = $support ? $site->get('support')['help_text'] : null;
    $company = $site->get('company');
    $enamad = $showCompany ? $site->enamad() : null;
    if (! $showCompany) {
        $company = ['name' => null, 'website' => $company['website'], 'logo' => null, 'intro' => null];
    }
    $logoUrl = $showCompany ? $site->logoUrl() : null;
    $privacyUrl = config('app.privacy_url');
    $loggedIn = auth('contact')->check();
    // روی خود صفحه ورود، دکمه پیگیری تکراری است
    $showTrack = ! request()->routeIs('account.login', 'account.verify');
@endphp

<footer class="site-footer">
    <style>
        .site-footer { margin-top: 32px; padding: 28px 0 20px; background: var(--surface, #fff); border-top: 1px solid var(--border, #dfe3ea); color: var(--text, #172033); font-size: 14px; line-height: 1.9; text-align: right; }
        .site-footer .sf-container { width: min(100% - 32px, 960px); margin-inline: auto; display: grid; gap: 20px; }
        .site-footer a { color: inherit; }
        .site-footer h2 { margin: 0 0 6px; font-size: 15px; }
        .site-footer p { margin: 0; }
        .sf-track { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px 16px; padding: 14px 18px; border-radius: 12px; background: var(--primary, #4f46e5); color: #fff !important; text-decoration: none; }
        .sf-track:hover { background: var(--primary-dark, #3730a3); }
        .sf-track strong { font-size: 16px; }
        .sf-track span { opacity: .9; font-size: 13px; }
        .sf-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .sf-muted { color: var(--muted, #5b6475); }
        .sf-channels { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0 0; padding: 0; list-style: none; }
        .sf-channels a, .sf-channels span.sf-chip { display: inline-block; padding: 4px 12px; border: 1px solid var(--border, #dfe3ea); border-radius: 20px; text-decoration: none; background: var(--background, #f5f7fb); }
        .sf-company { display: flex; gap: 12px; align-items: flex-start; }
        .sf-company img.sf-logo { max-height: 44px; max-width: 120px; object-fit: contain; }
        .sf-seal img { display: block; width: 88px; height: auto; }
        .sf-bottom { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding-top: 12px; border-top: 1px solid var(--border, #dfe3ea); font-size: 13px; }
        .sf-bottom nav { display: flex; gap: 14px; }
    </style>

    <div class="sf-container">
        @if ($showTrack)
        <a class="sf-track" href="{{ $loggedIn ? route('account.dashboard') : route('account.login') }}">
            <strong>{{ $loggedIn ? 'سفارش‌های من' : 'پیگیری سفارش و ورود' }}</strong>
            <span>وضعیت پیش‌خرید، رسید پرداخت و درخواست بازپرداخت</span>
        </a>
        @endif

        @if ($channels || $helpText || $company['name'] || $enamad)
            <div class="sf-row">
                @if ($channels || $helpText)
                    <section aria-labelledby="sf-support-title">
                        <h2 id="sf-support-title">پشتیبانی</h2>
                        @if ($helpText)
                            <p class="sf-muted">{{ $helpText }}</p>
                        @endif
                        @if ($channels)
                            <ul class="sf-channels">
                                @foreach ($channels as $channel)
                                    <li>
                                        @if ($url = $channel->url())
                                            <a href="{{ $url }}" @if (str_starts_with($url, 'http')) target="_blank" rel="noopener" @endif>{{ $channel->name() }}: <bdi dir="ltr">{{ $fa($channel->display()) }}</bdi></a>
                                        @else
                                            <span class="sf-chip">{{ $channel->name() }}: <bdi dir="ltr">{{ $fa($channel->display()) }}</bdi></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                @if ($company['name'] || $enamad)
                    <section class="sf-company" aria-label="ارائه‌دهنده">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $company['name'] ?? 'لوگو' }}" class="sf-logo" loading="lazy">
                        @endif
                        <div style="flex: 1">
                            @if ($company['name'])
                                <p>
                                    ارائه‌شده توسط
                                    @if ($company['website'])
                                        <a href="{{ $company['website'] }}" target="_blank" rel="noopener"><strong>{{ $company['name'] }}</strong></a>
                                    @else
                                        <strong>{{ $company['name'] }}</strong>
                                    @endif
                                </p>
                            @endif
                            @if ($company['intro'])
                                <p class="sf-muted">{{ $company['intro'] }}</p>
                            @endif
                        </div>
                        @if ($enamad)
                            <a class="sf-seal" href="{{ $enamad['url'] }}" target="_blank" referrerpolicy="origin" rel="noopener">
                                <img src="{{ $enamad['image'] }}" alt="نماد اعتماد الکترونیکی" referrerpolicy="origin" code="{{ $enamad['code'] }}" loading="lazy" style="cursor: pointer">
                            </a>
                        @endif
                    </section>
                @endif
            </div>
        @endif

        <div class="sf-bottom sf-muted">
            <span>© {{ $fa(\App\Support\Jalali::format(now(), 'Y')) }}{{ ($name = app(\App\Services\SiteSettings::class)->companyName()) ? ' ' . $name : '' }}</span>
            <nav aria-label="پیوندهای پاورقی">
                @if ($company['website'])
                    <a href="{{ $company['website'] }}" target="_blank" rel="noopener">سایت اصلی</a>
                @endif
                @if ($privacyUrl)
                    <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">حریم خصوصی</a>
                @endif
            </nav>
        </div>
    </div>
</footer>
