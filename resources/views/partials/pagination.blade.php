@if(isset($paginator))
<div class="table-pagination">
    <span>Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} entries</span>
    <nav>
        <a class="btn btn-soft btn-icon {{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() ?: '#' }}"><i class="fa-solid fa-chevron-left"></i></a>
        <span class="btn btn-primary btn-icon">{{ $paginator->currentPage() }}</span>
        <a class="btn btn-soft btn-icon {{ $paginator->hasMorePages() ? '' : 'disabled' }}" href="{{ $paginator->nextPageUrl() ?: '#' }}"><i class="fa-solid fa-chevron-right"></i></a>
    </nav>
</div>
@else
<div class="table-pagination"><span>Showing 1 to 5 of 48 entries</span><nav><button disabled><i class="fa-solid fa-chevron-left"></i></button><button class="active">1</button><button>2</button><button>3</button><button><i class="fa-solid fa-chevron-right"></i></button></nav></div>
@endif
