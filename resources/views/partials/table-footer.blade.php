{{-- List table footer: range shown, rows per page (applies on change; the button covers no-JS) and page links. --}}
<div class="table-footer">
 <span>@if($paginator->total()){{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} {{ $noun }}@else 0 {{ $noun }}@endif</span>
 <div class="table-footer-end">
  <form method="GET" class="per-page">
   @foreach(request()->except(['per_page', 'page']) as $name => $value)@if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
   <label for="per-page-{{ $noun }}">Filas por página</label>
   <select id="per-page-{{ $noun }}" name="per_page" data-autosubmit>@foreach(\App\Support\Table::PAGE_SIZES as $size)<option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>@endforeach</select>
   <noscript><button class="btn" type="submit">Aplicar</button></noscript>
  </form>
  {{ $paginator->links('partials.pagination') }}
 </div>
</div>
