<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="brand-link">
            <i class="bi bi-rocket-takeoff fs-4 me-2"></i>
            <span class="brand-text fw-bold">ایده‌لب</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2" aria-label="منوی اصلی">
            <ul
                class="nav sidebar-menu flex-column"
                data-lte-toggle="treeview"
                role="navigation"
                data-accordion="false"
            >
                @foreach (config('admin_menu') as $group)
                    @php
                        $items = array_filter(
                            $group['items'],
                            fn ($item) => auth()->user()->can($item['permission'])
                        );
                    @endphp

                    @if ($items)
                        @isset($group['header'])
                            <li class="nav-header">{{ $group['header'] }}</li>
                        @endisset

                        @foreach ($items as $item)
                            @php($isActive = request()->routeIs(...$item['active']))

                            <li class="nav-item">
                                <a
                                    href="{{ route($item['route']) }}"
                                    @class(['nav-link', 'active' => $isActive])
                                    @if ($isActive) aria-current="page" @endif
                                >
                                    <i class="nav-icon bi {{ $item['icon'] }}"></i>
                                    <p>{{ $item['label'] }}</p>
                                </a>
                            </li>
                        @endforeach
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
