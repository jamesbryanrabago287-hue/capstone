(() => {
 const period=document.getElementById('expiry-warning-period'),label=document.getElementById('expiry-custom-label'),input=document.getElementById('expiry-custom-days');
 if(!period||!label||!input)return;
 const update=()=>{const custom=period.value==='custom';label.hidden=!custom;label.style.display=custom?'':'none';input.disabled=!custom;input.required=custom;};
 period.addEventListener('change',update);update();
})();
