@if ($paginator->hasPages())
<nav class="pager" aria-label="Paginación">
 <span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
 <div class="pager-controls">
  @if($paginator->onFirstPage())<span class="pager-disabled" aria-disabled="true">Anterior</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>@endif
  @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>@else<span class="pager-disabled" aria-disabled="true">Siguiente</span>@endif
 </div>
</nav>
@endif
