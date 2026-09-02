<div class="col">
    @if(isset($href))
        <a href="{{ $href }}" class="stat-card {{ $class ?? '' }}">
            <span class="stat-icon"><i class="fa-solid {{ $icon }}"></i></span>
            <div class="stat-copy"><span>{{ $label }}</span><strong>{{ $value }}</strong>@isset($trend)<small class="{{ str_contains($trend, '-') ? 'down' : 'up' }}">{{ $trend }} <span>vs last month</span></small>@endisset</div>
        </a>
    @else
        <div class="stat-card {{ $class ?? '' }}">
            <span class="stat-icon"><i class="fa-solid {{ $icon }}"></i></span>
            <div class="stat-copy"><span>{{ $label }}</span><strong>{{ $value }}</strong>@isset($trend)<small class="{{ str_contains($trend, '-') ? 'down' : 'up' }}">{{ $trend }} <span>vs last month</span></small>@endisset</div>
        </div>
    @endif
</div>
