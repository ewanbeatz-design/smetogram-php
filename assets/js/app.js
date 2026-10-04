document.addEventListener('input',e=>{const i=e.target.closest('[data-money]');if(i)i.value=i.value.replace(/[^0-9.,]/g,'').replace(',','.');});document.addEventListener('click',e=>{const b=e.target.closest('[data-confirm]');if(b&&!confirm(b.dataset.confirm))e.preventDefault();});
(function(){
 const $=s=>document.querySelector(s);
 const backdrop=$('#globalSearchBackdrop'), toggle=$('#globalSearchToggle'), close=$('#globalSearchClose');
 const input=$('#globalSearchInput'), results=$('#searchResults'), hint=$('#globalSearchHint');
 let timer=null;

 function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
 function openSearch(){
   if(!backdrop) return;
   backdrop.hidden=false;
   document.body.classList.add('search-open');
   setTimeout(()=>input?.focus(),20);
 }
 function closeSearch(){
   if(!backdrop) return;
   backdrop.hidden=true;
   document.body.classList.remove('search-open');
 }
 toggle?.addEventListener('click',openSearch);
 close?.addEventListener('click',closeSearch);
 backdrop?.addEventListener('click',e=>{if(e.target===backdrop)closeSearch()});
 document.addEventListener('keydown',e=>{
   if(e.key==='Escape'&&!backdrop?.hidden) closeSearch();
   if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openSearch();}
 });
 input?.addEventListener('input',function(){
   clearTimeout(timer);
   const q=this.value.trim();
   results.innerHTML='';
   hint.textContent=q.length<2?'Начните вводить название проекта, город или имя заказчика.':'Ищем...';
   if(q.length<2)return;
   timer=setTimeout(async()=>{
     try{
       const r=await fetch('api.php?action=search&q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest'}});
       const d=await r.json();
       const items=Array.isArray(d.items)?d.items:[];
       if(!items.length){
         hint.textContent='По запросу ничего не найдено.';
         results.innerHTML='<div class="global-search-empty"><strong>Ничего не найдено</strong><span>Попробуйте название проекта, город или имя заказчика.</span></div>';
         return;
       }
       hint.textContent='Результаты поиска';
       results.innerHTML=items.map(x=>{
         const url=String(x.url||'#').replace(/"/g,'%22');
         return '<a class="global-search-result" href="'+url+'"><span class="global-result-icon"><i class="bi '+(x.type==="document"?'bi-file-earmark-text':x.type==="estimate"?'bi-list-check':'bi-folder2-open')+'"></i></span><span class="global-result-content"><strong>'+escapeHtml(x.title)+'</strong><span>'+escapeHtml(x.meta||'')+'</span><small>'+escapeHtml(x.group||'Проект')+'</small></span><i class="bi bi-chevron-right"></i></a>';
       }).join('');
     }catch(e){
       hint.textContent='Не удалось выполнить поиск.';
       results.innerHTML='<div class="global-search-empty"><strong>Ошибка поиска</strong><span>Попробуйте ещё раз.</span></div>';
     }
   },180);
 });

 const bell=$('.notification-toggle'),menu=$('#notificationMenu'),list=$('#notificationList'),badge=$('#notificationBadge');
 let lastId=0;
 function loadNotifications(){
   fetch('api.php?action=notifications&since='+lastId,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>{
     if(d.items&&d.items.length){
       d.items.forEach(n=>{if(Number(n.id)>lastId)lastId=Number(n.id)});
       const html=d.items.map(n=>'<a class="notification-row '+(!Number(n.isRead)?'unread':'')+'" href="'+String(n.url||'#').replace(/"/g,'%22')+'"><span class="notification-main"><span class="notification-icon"><i class="bi '+(n.type==='message'?'bi-chat':n.type==='document'?'bi-file-earmark-text':n.type==='payment'?'bi-wallet2':'bi-bell')+'"></i></span><span class="notification-content"><strong>'+escapeHtml(n.title)+'</strong><span>'+escapeHtml(n.body||'')+'</span><small>'+escapeHtml(n.createdAt||'')+'</small></span></span><span class="notification-unread-dot"></span></a>').join('');
       if(list.querySelector('.notifications-empty'))list.innerHTML='';
       list.insertAdjacentHTML('afterbegin',html);
     }
     if(Number(d.unread)>0){badge.hidden=false;badge.textContent=Number(d.unread)>99?'99+':d.unread}else badge.hidden=true;
   }).catch(()=>{});
 }
 bell?.addEventListener('click',e=>{e.stopPropagation();menu?.classList.toggle('show')});
 document.addEventListener('click',e=>{if(!e.target.closest('#notifications'))menu?.classList.remove('show')});
 const read=$('#readNotifications');
 read?.addEventListener('click',()=>{
   const csrf=document.querySelector('input[name=csrf]')?.value||'';
   fetch('api.php?action=read_notifications',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:'csrf='+encodeURIComponent(csrf)}).then(()=>{
     badge.hidden=true;badge.textContent='0';document.querySelectorAll('.notification-row').forEach(x=>x.classList.remove('unread'));
   });
 });
 if(bell){loadNotifications();setInterval(loadNotifications,10000)}
})();