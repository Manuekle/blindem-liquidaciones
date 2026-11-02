{{-- Runs before first paint: cross-document View Transitions are detected once, and the fallback entrance is armed only
     when the browser cannot cross-fade between pages. A page reloaded in place (filters, search, sorting, pagination,
     an action that returns to the same list) arrives from its own path: it swaps without any transition, so it reads
     as the content updating instead of the whole page reloading. --}}
<script>
(() => {
 const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
 let crossDoc = false;
 try {
  crossDoc = 'onpagereveal' in window && new CSSStyleSheet().insertRule('@view-transition { navigation: auto; }') === 0;
 } catch (error) {}
 let inPlace = false;
 try {
  const from = new URL(window.navigation?.activation?.from?.url || document.referrer);
  inPlace = from.origin === location.origin && from.pathname === location.pathname;
 } catch (error) {}
 window.pageTransitions = { crossDoc, reduce, inPlace };
 if (inPlace) {
  addEventListener('pagereveal', ({ viewTransition }) => {
   if (!viewTransition) return;
   // A skipped transition rejects `ready`; nothing waits on it.
   viewTransition.ready.catch(() => {});
   viewTransition.skipTransition();
  });
  return;
 }
 if (reduce || crossDoc) return;
 document.documentElement.classList.add('page-enter');
 // A cross-document transition may still run (flag-enabled browser): it owns the entrance, so drop the fallback.
 addEventListener('pagereveal', () => document.documentElement.classList.remove('page-enter'));
})();
</script>
