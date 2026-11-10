(() => {
 const period=document.getElementById('history-period');
 if(period){const updatePeriod=()=>{const custom=period.value==='custom';document.querySelectorAll('.custom-period-field').forEach(label=>{label.hidden=!custom;const input=label.querySelector('input');input.disabled=!custom;input.required=custom;});const help=document.getElementById('history-period-help');if(help)help.textContent=custom?'Both dates are included.':'Choose Custom range to enter your own dates.';};period.addEventListener('change',updatePeriod);updatePeriod();}

 const type=document.getElementById('movement-type'),medicine=document.getElementById('movement-medicine');if(!type||!medicine)return;
 const qty=document.getElementById('movement-quantity'),actual=document.getElementById('actual-count'),expected=document.getElementById('expected-balance'),notes=document.getElementById('movement-notes'),preview=document.getElementById('count-preview');
 const reference=document.getElementById('movement-reference');
 const hints={
 'Stock In':['Select the medicine, enter the quantity received and its expiry date. Different expiry dates are kept separate automatically.','Save received stock'],
 'Stock Out':['Select the batch and quantity actually issued. If recorded in a consultation already, do not enter it again here.','Record medicine issued'],
 'Disposal':['Select the batch, enter the quantity being removed and explain why (for example, expired or damaged).','Record disposal'],
 'Physical Count':['Count the actual stock and enter that total, not the difference. Explain any discrepancy; cite a previous movement in your reason if correcting it.','Save count adjustment']
 };
 function refresh(){
  const expiryField=document.getElementById('receive-expiry'),receiving=type.value==='Stock In';if(expiryField){document.getElementById('receive-expiry-label').hidden=!receiving;expiryField.disabled=!receiving;expiryField.required=receiving;}
  const count=type.value==='Physical Count',reason=count||type.value==='Disposal',balance=Number(medicine.selectedOptions[0]?.dataset.balance||0);
  document.getElementById('movement-help').textContent=hints[type.value][0];document.getElementById('save-movement').textContent=hints[type.value][1];
  const caption=document.getElementById('quantity-caption');if(caption)caption.textContent=type.value==='Stock Out'?'Quantity to issue':type.value==='Disposal'?'Quantity to dispose of':'Quantity received';
  expected.value=balance;qty.disabled=count;qty.required=!count;actual.disabled=!count;actual.required=count;
  document.getElementById('quantity-label').hidden=count;document.getElementById('count-label').hidden=!count;
  notes.required=reason;notes.disabled=!reason;document.getElementById('reason-label').hidden=!reason;
  document.getElementById('reason-caption').textContent=count?'Reason for count difference (required)':'Reason for disposal (required)';
  notes.placeholder=count?'Explain why the physical count differs from the system balance.':'For example: expired batch or damaged packaging.';
  reference.disabled=reason;document.getElementById('reference-label').hidden=reason;
  document.getElementById('reference-caption').textContent=type.value==='Stock In'?'Delivery reference (optional)':'Issuance reference (optional)';
  reference.placeholder=type.value==='Stock In'?'Delivery note or receipt number':'Issuance reference, if available';
  preview.hidden=!count;preview.textContent=count?'System balance: '+balance+(actual.value!==''?'. Adjustment: '+(Number(actual.value)-balance):'. Enter the physical count to see the difference.') : '';
 }
 const search=document.getElementById('movement-search'),status=document.getElementById('movement-search-status'),list=document.getElementById('movement-matches');
 if(search && list){
  const all=[...medicine.options].filter(o=>o.value!==''),label=o=>type.value==='Stock In'?(o.dataset.medicineLabel||o.textContent.trim()):o.textContent.trim().replace(/\s+/g,' ');
  const eligible=option=>type.value!=='Stock Out'||option.dataset.issuable==='1';
  let visible=[],active=-1;
  document.getElementById('movement-search-label').hidden=false;document.getElementById('movement-native-label').hidden=true;medicine.required=false;search.required=true;
  if(medicine.value){search.value=label(medicine.selectedOptions[0]);}else search.setCustomValidity('Choose a medicine batch from the suggestions.');
  function close(){list.hidden=true;search.setAttribute('aria-expanded','false');search.removeAttribute('aria-activedescendant');active=-1;}
  function highlight(){[...list.children].forEach((item,i)=>{item.setAttribute('aria-selected',String(i===active));item.classList.toggle('is-active',i===active);});if(active>=0)search.setAttribute('aria-activedescendant','medicine-match-'+active);else search.removeAttribute('aria-activedescendant');}
  function choose(index){const option=visible[index];if(!option||!eligible(option))return;medicine.value=option.value;search.value=label(option);search.setCustomValidity('');if(type.value==='Stock In'){const dates=new Map();all.filter(o=>(o.dataset.group||o.value)===(option.dataset.group||option.value)).forEach(o=>{const date=o.dataset.expiry||'Not recorded';dates.set(date,(dates.get(date)||0)+Number(o.dataset.balance||0));});status.textContent='Current stock by expiry: '+[...dates].sort((a,b)=>a[0].localeCompare(b[0])).map(([date,quantity])=>date+': '+quantity+' '+(option.dataset.unit||'units')).join('; ')+'.';}else status.textContent='Selected: '+label(option);const selectionStatus=status.textContent;close();medicine.dispatchEvent(new Event('change',{bubbles:true}));search.focus();close();status.textContent=selectionStatus;}
  function render(){
   const selected=medicine.selectedOptions[0],query=(selected && search.value===label(selected)?'':search.value.trim()).toLocaleLowerCase();
   let matches=all.filter(o=>label(o).toLocaleLowerCase().includes(query));
   if(type.value==='Stock In'){const seen=new Set();matches=matches.filter(o=>{const key=o.dataset.group||o.value;if(seen.has(key))return false;seen.add(key);return true;});}
   if(type.value==='Stock Out')matches.sort((a,b)=>Number(!eligible(a))-Number(!eligible(b)));
   visible=matches.slice(0,50);list.replaceChildren();active=visible.findIndex(eligible);
   visible.forEach((option,i)=>{const item=document.createElement('div');item.id='medicine-match-'+i;item.className='medicine-match';item.setAttribute('role','option');item.textContent=label(option);if(!eligible(option)){item.setAttribute('aria-disabled','true');item.classList.add('is-unavailable');const badge=document.createElement('span');badge.className=option.dataset.expired==='1'?'medicine-availability is-expired':'medicine-availability is-empty';badge.textContent=option.dataset.expired==='1'?'Expired':'Out of stock';item.appendChild(badge);}item.addEventListener('pointerdown',event=>event.preventDefault());item.addEventListener('click',()=>choose(i));list.appendChild(item);});
   list.hidden=visible.length===0;search.setAttribute('aria-expanded',String(visible.length>0));highlight();
   status.textContent=matches.length?(matches.length>50?'Showing 50 of '+matches.length+' matches. Type more to narrow the list.':(type.value==='Stock Out'?matches.filter(eligible).length+' available of '+matches.length+' matching batch(es). Unavailable batches cannot be selected.':matches.length+' matching batch(es). Choose one below.')):(type.value==='Stock Out'?'No available, non-expired batches match. Try another medicine or record a delivery.':'No matching medicines. Check the spelling or clear the field.');
  }
  function applyEligibility(){
   all.forEach(option=>{option.disabled=!eligible(option);});
   const selected=medicine.selectedOptions[0];
   if(selected?.value&&!eligible(selected)){medicine.value='';search.value='';search.setCustomValidity('Select a non-expired batch with available stock.');refresh();}
   if(medicine.value && eligible(medicine.selectedOptions[0]))search.value=label(medicine.selectedOptions[0]);
   render();close();
  }
  type.addEventListener('change',applyEligibility);applyEligibility();
  search.addEventListener('input',()=>{medicine.value='';search.setCustomValidity('Choose a medicine batch from the suggestions.');refresh();render();});
  search.addEventListener('focus',render);search.addEventListener('click',render);
  search.addEventListener('keydown',event=>{if(event.key==='Escape'){close();return;}if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(list.hidden){render();return;}const step=event.key==='ArrowDown'?1:-1;let next=active<0?(step>0?0:visible.length-1):active+step;while(next>=0&&next<visible.length&&!eligible(visible[next]))next+=step;if(next>=0&&next<visible.length)active=next;highlight();list.children[active]?.scrollIntoView?.({block:'nearest'});}else if(event.key==='Enter'&&!list.hidden){event.preventDefault();choose(active);}else if(event.key==='Tab')close();});
  document.addEventListener('pointerdown',event=>{if(!event.target.closest('.medicine-picker'))close();});search.addEventListener('blur',close);
 }
 type.addEventListener('change',refresh);medicine.addEventListener('change',refresh);actual.addEventListener('input',refresh);refresh();
})();
