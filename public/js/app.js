document.addEventListener('DOMContentLoaded',()=>{
 const btn=document.querySelector('#mobile-menu'),sidebar=document.querySelector('#sidebar');
 btn?.addEventListener('click',()=>sidebar.classList.toggle('open'));
 document.addEventListener('keydown',e=>{if(e.key==='Escape')sidebar?.classList.remove('open');});
 const filter=document.querySelector('#contracts-filters');
 if(filter){
   filter.querySelectorAll('select').forEach(el=>el.addEventListener('change',()=>filter.requestSubmit()));
   let debounce;filter.querySelectorAll('input').forEach(el=>el.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(()=>filter.requestSubmit(),650);}));
 }
 const forms=document.querySelectorAll('form');forms.forEach(form=>{
  if(form.dataset.submitted)return;
  form.addEventListener('submit',()=>{const button=form.querySelector('button[type="submit"].primary,button:not([type]).primary');if(button){button.disabled=true;button.dataset.original=button.textContent;button.textContent='Guardando…';}});
 });
});
