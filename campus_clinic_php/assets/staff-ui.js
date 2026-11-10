(() => {
 if(!document.body.classList.contains('staff-app'))return;
 document.querySelectorAll('.content table').forEach(table=>{if(table.closest('.table-scroll,dialog,.report-preview-document'))return;const wrap=document.createElement('div');wrap.className='table-scroll';wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label',(table.closest('section')?.querySelector('h2')?.textContent||'Records')+' table');table.before(wrap);wrap.append(table);});
 function openTarget(){const target=document.getElementById(location.hash.slice(1));if(target?.matches('details')){target.open=true;target.scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});target.querySelector('input:not([type=hidden]),select,textarea')?.focus({preventScroll:true});}}
 window.addEventListener('hashchange',openTarget);openTarget();
 const notes=document.querySelector('[data-unsaved-notes]');
 const fields=notes?[...notes.querySelectorAll('textarea,select')]:[];const initial=fields.map(el=>el.dataset.savedValue ?? el.value);let leaving=false;
 const dirty=()=>fields.some((el,i)=>el.value!==initial[i]);
 function noteStatus(){const status=document.getElementById('notes-save-status');if(status)status.textContent=dirty()?'Unsaved changes. Save findings before recording medicine or leaving this page.':'No unsaved changes.';}
 notes?.addEventListener('input',noteStatus);notes?.addEventListener('change',noteStatus);noteStatus();
 window.addEventListener('beforeunload',event=>{if(dirty()&&!leaving){event.preventDefault();event.returnValue='';}});
 document.addEventListener('submit',event=>{
  if(event.submitter?.value==='complete' && event.submitter?.name==='action' && !confirm('Complete this consultation? Current findings will be saved and this visit will become read-only.')){event.preventDefault();return;}
  if(dirty() && event.target!==notes && !confirm('Your findings have unsaved changes. Continue without saving them? Choose Cancel to save findings first.')){event.preventDefault();return;}
  if(!event.defaultPrevented)leaving=true;
 });
})();
