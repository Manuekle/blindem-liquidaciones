{{-- Back link. Without history it goes to the parent page ($href / $label); js/nav.js points it at the page the person
     actually came from and names it ("Volver a Contrato #12"), returning through the browser history when it can. --}}
<a class="back-link" href="{{ $href }}" data-back><span class="back-icon"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#arrow-left"/></svg></span><span class="back-text"><small>Volver a</small> <b>{{ $label }}</b></span></a>
