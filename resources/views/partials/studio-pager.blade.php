@if($paginator->total() > 0)
<nav class="gm-studio-pager" aria-label="Pagination">
  <p>Showing <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong> of {{ number_format($paginator->total()) }}</p>
  <div class="gm-pager-controls">
    @if($paginator->onFirstPage())<span class="gm-pager-link is-disabled">Previous</span>
    @else<a class="gm-pager-link" href="{{ $paginator->previousPageUrl() }}">Previous</a>@endif
    <span class="gm-pager-numbers">Page {{ $paginator->currentPage() }} of {{ max(1,$paginator->lastPage()) }}</span>
    @if($paginator->hasMorePages())<a class="gm-pager-link" href="{{ $paginator->nextPageUrl() }}">Next</a>
    @else<span class="gm-pager-link is-disabled">Next</span>@endif
    <form action="{{ url()->current() }}" method="GET" class="gm-pager-goto">
      @foreach(request()->except('page') as $key=>$value)
        @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
      @endforeach
      <label for="{{ $id ?? 'gm-go-to' }}">Go to</label>
      <input id="{{ $id ?? 'gm-go-to' }}" name="page" type="number" min="1" max="{{ max(1,$paginator->lastPage()) }}" value="{{ $paginator->currentPage() }}" required>
      <button type="submit">Go</button>
    </form>
  </div>
</nav>
@endif