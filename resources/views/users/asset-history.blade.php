@extends('layouts.app')

@section('title', 'User Asset History')

@section('content')

@include('partials.page-header', [
    'title' => $user->name,
    'description' => 'User profile, current assets and complete assignment history.'
])

<style>
.history-profile {
    display: flex;
    align-items: center;
    gap: 16px;
}

.history-avatar {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: #3f51b5;
    font-size: 20px;
    font-weight: 700;
    flex: 0 0 58px;
}

.history-profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    color: #64748b;
    font-size: 14px;
    margin-top: 6px;
}

.history-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.history-summary-value {
    font-weight: 700;
}

.history-empty {
    padding: 45px 20px;
    text-align: center;
    color: #64748b;
}

.history-search-box {
    position: relative;
    max-width: 420px;
}

.history-search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.history-search-box input {
    padding-left: 36px;
}

@media(max-width:768px) {
    .history-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>


<div class="panel mb-3">

    <div class="panel-header">

        <div class="history-profile">

            @if($user->image_path)

                <img
                    src="{{ \App\Support\PublicUrl::storage($user->image_path) }}"
                    alt="{{ $user->name }}"
                    style="width:58px;height:58px;object-fit:cover;border-radius:50%;"
                >

            @else

                <span class="history-avatar">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </span>

            @endif

            <div>

                <h2 class="mb-0">{{ $user->name }}</h2>

                <div class="history-profile-meta">

                    <span>
                        <i class="fa-solid fa-id-card me-1"></i>
                        {{ $user->unique_id ?: '—' }}
                    </span>

                    <span>
                        <i class="fa-regular fa-envelope me-1"></i>
                        {{ $user->email ?: '—' }}
                    </span>

                    <span>
                        <i class="fa-solid fa-phone me-1"></i>
                        {{ $user->contact ?: '—' }}
                    </span>

                    <span>
                        <i class="fa-solid fa-location-dot me-1"></i>
                        {{ $user->address ?: '—' }}
                    </span>

                </div>

            </div>

        </div>

        <div class="d-flex gap-2">

            <a
                href="{{ route('users.index') }}"
                class="btn btn-soft"
            >
                <i class="fa-solid fa-arrow-left me-2"></i>
                Back
            </a>

            @permission('users','export')
            <a
                href="{{ route('users.asset-history.export', $user) }}"
                class="btn btn-primary"
            >
                <i class="fa-solid fa-file-export me-2"></i>
                Export History
            </a>
            @endpermission

        </div>

    </div>

</div>


<div class="row row-cols-2 g-3 mb-3">

    @include('partials.stat-card', [
        'icon' => 'fa-clock-rotate-left',
        'label' => 'Total Assignments',
        'value' => number_format($totalAssignments)
    ])

    @include('partials.stat-card', [
        'icon' => 'fa-laptop',
        'label' => 'Current Assets',
        'value' => number_format($currentAssignments->count()),
        'class' => 'success'
    ])

</div>


@if($currentAssignments->isNotEmpty())

<div class="panel mb-3">

    <div class="panel-header">
        <div>
            <h2>Current Assigned Assets</h2>
            <p>{{ $currentAssignments->count() }} currently assigned</p>
        </div>
    </div>

    <div class="table-responsive">

        <table class="table data-table">

            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Asset Tag</th>
                    <th>Type</th>
                    <th>Serial Number</th>
                    <th>Assigned At</th>
                    <th>Assigned By</th>
                </tr>
            </thead>

            <tbody>

                @foreach($currentAssignments as $item)

                    <tr>

                        <td>
                            <strong>{{ $item->asset?->name ?: 'Deleted Asset' }}</strong>
                        </td>

                        <td>
                            {{ $item->asset?->asset_tag ?: '—' }}
                        </td>

                        <td>
                            {{ $item->asset?->type?->name ?: '—' }}
                        </td>

                        <td>
                            {{ $item->asset?->serial_number ?: '—' }}
                        </td>

                        <td>
                            {{ $item->assigned_at?->format('d M Y h:i A') ?: '—' }}
                        </td>

                        <td>
                            {{ $item->assigned_by ?: '—' }}
                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>

</div>

@endif


<div class="panel">

    <div class="panel-header history-toolbar">

        <div>
            <h2>Assignment History</h2>
            <p>{{ number_format($history->total()) }} assignment records</p>
        </div>

        <span class="badge-soft {{ $user->status === 'Active' ? 'success' : 'muted' }}">
            {{ $user->status }}
        </span>

    </div>


    <form
        method="GET"
        action="{{ route('users.asset-history', $user) }}"
        class="p-3 border-bottom"
        id="historyFilterForm"
    >

        <div class="history-search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="search"
                name="search"
                id="historySearch"
                value="{{ request('search') }}"
                placeholder="Search asset, tag, type, serial, department or assigned by..."
                autocomplete="off"
                class="form-control"
            >

        </div>

    </form>


    <div class="table-responsive">

        <table class="table data-table">

            <thead>

                <tr>
                    <th>#</th>
                    <th>Asset</th>
                    <th>Asset Tag</th>
                    <th>Type</th>
                    <th>Serial Number</th>
                    <th>Department</th>
                    <th>Assigned At</th>
                    <th>Unassigned At</th>
                    <th>Assigned By</th>
                </tr>

            </thead>

            <tbody>

                @forelse($history as $item)

                    <tr>

                        <td>
                            {{ $history->firstItem() + $loop->index }}
                        </td>

                        <td>
                            <strong>
                                {{ $item->asset?->name ?: 'Deleted Asset' }}
                            </strong>
                        </td>

                        <td>
                            {{ $item->asset?->asset_tag ?: '—' }}
                        </td>

                        <td>
                            {{ $item->asset?->type?->name ?: '—' }}
                        </td>

                        <td>
                            {{ $item->asset?->serial_number ?: '—' }}
                        </td>

                        <td>
                            {{ $item->asset?->department?->name ?: '—' }}
                        </td>

                        <td>
                            {{ $item->assigned_at?->format('d M Y h:i A') ?: '—' }}
                        </td>

                        <td>

                            @if($item->unassigned_at)

                                {{ $item->unassigned_at->format('d M Y h:i A') }}

                            @else

                                <span class="badge-soft success">
                                    Current
                                </span>

                            @endif

                        </td>

                        <td>
                            {{ $item->assigned_by ?: '—' }}
                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="9"
                            class="history-empty"
                        >
                            <i class="fa-solid fa-box-open fa-2x mb-3 d-block"></i>

                            No asset assignment history available yet.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    @if($history->hasPages())

        <div class="p-3 border-top">
            @include('partials.pagination', [
                'paginator' => $history
            ])
        </div>

    @endif

</div>

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('historyFilterForm');
    const search = document.getElementById('historySearch');

    if (!form || !search) {
        return;
    }

    let searchTimer;

    search.addEventListener('input', function () {

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {
            form.submit();
        }, 450);

    });

});
</script>

@endpush

@endsection
