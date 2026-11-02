{{-- The service worker only serves /offline.html when a page cannot be reached; it never caches app pages. --}}
<script>
if ('serviceWorker' in navigator && isSecureContext) addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
</script>
