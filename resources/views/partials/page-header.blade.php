<div class="page-heading">
    <div><p class="eyebrow">{{ $eyebrow ?? 'Enterprise workspace' }}</p><h1>{{ $title }}</h1><p>{{ $description ?? '' }}</p></div>
    @isset($actionUrl)<a href="{{ $actionUrl }}" class="btn btn-primary" @if($requiresCentre ?? false) data-requires-centre @endif><i class="fa-solid {{ $actionIcon ?? 'fa-plus' }} me-2"></i>{{ $actionLabel ?? 'Add New' }}</a>@endisset
</div>
