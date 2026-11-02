{{-- Runs before first paint so the saved or system theme never flashes light. --}}
<script>
(() => {
 let saved = null;
 try { saved = localStorage.getItem('theme'); } catch (error) {}
 const dark = saved ? saved === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
 document.documentElement.dataset.theme = dark ? 'dark' : 'light';
})();
</script>
