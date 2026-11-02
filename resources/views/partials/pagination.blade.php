@if ($paginator->hasPages())
@php
 // First, last and two pages either side of the current one; gaps collapse into an ellipsis.
 $current = $paginator->currentPage();
 $last = $paginator->lastPage();
 $pages = collect(range(1, $last))->filter(fn ($page) => $page === 1 || $page === $last || abs($page - $current) <= 1)->values();
@endphp
<nav class="pager" aria-label="Paginación">
 @if($paginator->onFirstPage())<span class="pager-step is-disabled" aria-disabled="true"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#chevron-left"/></svg><span class="sr-only">Anterior</span></span>@else<a class="pager-step" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#chevron-left"/></svg></a>@endif
 @foreach($pages as $index => $page)
  @if($index && $page - $pages[$index - 1] > 1)<span class="pager-gap" aria-hidden="true">…</span>@endif
  @if($page === $current)<span class="pager-page is-current" aria-current="page">{{ $page }}</span>@else<a class="pager-page" href="{{ $paginator->url($page) }}" aria-label="Página {{ $page }}">{{ $page }}</a>@endif
 @endforeach
 @if($paginator->hasMorePages())<a class="pager-step" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#chevron-right"/></svg></a>@else<span class="pager-step is-disabled" aria-disabled="true"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#chevron-right"/></svg><span class="sr-only">Siguiente</span></span>@endif
</nav>
@endif
