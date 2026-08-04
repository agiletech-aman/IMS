@php $segments = request()->segments(); @endphp
<nav class="breadcrumb-wrap" aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fa-solid fa-house"></i></a></li>
        @foreach($segments as $segment)
            <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">{{ is_numeric($segment) ? '#'.$segment : ucwords(str_replace('-', ' ', $segment)) }}</li>
        @endforeach
    </ol>
</nav>
