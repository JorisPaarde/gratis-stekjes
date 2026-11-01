@if($paginator->hasPages())
  <nav class="beheer-paginatie" aria-label="Pagina's">
    @if($paginator->onFirstPage())
      <span class="muted">← Vorige</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Vorige</a>
    @endif
    <span>Pagina {{ $paginator->currentPage() }} van {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">Volgende →</a>
    @else
      <span class="muted">Volgende →</span>
    @endif
  </nav>
@endif
