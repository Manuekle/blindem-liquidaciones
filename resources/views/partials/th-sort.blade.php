{{-- Sortable column header: first click sorts ascending, the next one descending. aria-sort tells assistive tech. --}}
@php
 $sortActive = request('sort') === $key;
 $sortDir = $sortActive && request('dir') === 'desc' ? 'desc' : 'asc';
 $sortUrl = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $sortActive && $sortDir === 'asc' ? 'desc' : 'asc', 'page' => null]);
@endphp
<th scope="col" class="sortable {{ $class ?? '' }}" @if($sortActive) aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif><a class="sort-link {{ $sortActive ? 'is-sorted is-'.$sortDir : '' }}" href="{{ $sortUrl }}">{{ $label }}<svg class="icon sort-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#chevron-down"/></svg></a></th>
