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

 const toggles=document.querySelectorAll('.notification-toggle');
 const menu=$('#notificationMenu'),list=$('#notificationList'),badge=$('#notificationBadge'),topBadge=$('#topNotificationBadge'),status=$('#notificationStatus');
 let lastId=0;
 const dismissedKey='smetogram.dismissedNotifications';
 function dismissedIds(){
   try{return new Set(JSON.parse(localStorage.getItem(dismissedKey)||'[]').map(Number))}catch(e){return new Set()}
 }
 function saveDismissed(set){
   try{localStorage.setItem(dismissedKey,JSON.stringify([...set].slice(-200)))}catch(e){}
 }
 function syncBadges(unread){
   const has=Number(unread)>0;
   [badge,topBadge].forEach(b=>{if(!b)return;b.hidden=!has;b.textContent=Number(unread)>99?'99+':String(unread)});
   if(status)status.textContent=has?'Есть непрочитанные':'Всё прочитано';
 }
 function notificationIcon(type){
   return type==='message'?'bi-chat':type==='document'?'bi-file-earmark-text':type==='payment'?'bi-wallet2':type==='acceptance'?'bi-check2-square':type==='schedule'?'bi-calendar3':type==='team'?'bi-people': 'bi-folder-kanban';
 }
 function notificationTime(value){
   if(!value)return 'Только что';
   const d=new Date(String(value).replace(' ','T'));
   if(Number.isNaN(d.getTime()))return String(value);
   const diff=Math.max(0,Date.now()-d.getTime());
   if(diff<60000)return 'Только что';
   if(diff<3600000)return Math.floor(diff/60000)+' мин назад';
   if(diff<86400000)return Math.floor(diff/3600000)+' ч назад';
   if(diff<604800000)return Math.floor(diff/86400000)+' дн назад';
   return d.toLocaleDateString('ru-RU',{day:'2-digit',month:'2-digit',year:'numeric'});
 }
 function renderNotification(n){
   const id=Number(n.id||0), icon=notificationIcon(n.type);
   return '<div class="notification-row '+(!Number(n.isRead)?'unread':'')+'" data-notification-id="'+id+'">'+
     '<button type="button" class="notification-main" data-notification-url="'+String(n.url||'#').replace(/"/g,'%22')+'">'+
       '<div class="notification-icon notification-type-'+escapeHtml(n.type||'project')+'"><i class="bi '+icon+'"></i></div>'+
       '<div class="notification-content"><strong>'+escapeHtml(n.title||'Новое событие')+'</strong><span>'+escapeHtml(n.body||'')+'</span><small>'+escapeHtml(notificationTime(n.createdAt))+'</small></div>'+
     '</button>'+
     '<div class="notification-actions"><button type="button" class="notification-dismiss" data-dismiss-notification="'+id+'" aria-label="Удалить уведомление" title="Удалить"><i class="bi bi-x-lg"></i></button></div>'+
   '</div>';
 }
 function loadNotifications(){
   fetch('api.php?action=notifications&since='+lastId,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>{
     const dismissed=dismissedIds();
     if(d.items&&d.items.length){
       d.items.forEach(n=>{if(Number(n.id)>lastId)lastId=Number(n.id)});
       const fresh=d.items.filter(n=>!dismissed.has(Number(n.id)));
       if(fresh.length){
         const html=fresh.map(renderNotification).join('');
         if(list?.querySelector('.notifications-empty'))list.innerHTML='';
         list?.insertAdjacentHTML('afterbegin',html);
       }
     }
     syncBadges(d.unread||0);
   }).catch(()=>{});
 }
 toggles.forEach(t=>t.addEventListener('click',e=>{
   e.preventDefault();e.stopPropagation();
   menu?.classList.toggle('show');
 }));
 list?.addEventListener('click',e=>{
   const dismiss=e.target.closest('[data-dismiss-notification]');
   if(dismiss){
     e.preventDefault();e.stopPropagation();
     const id=Number(dismiss.dataset.dismissNotification||0);
     const set=dismissedIds();set.add(id);saveDismissed(set);
     dismiss.closest('.notification-row')?.remove();
     if(!list.querySelector('.notification-row')) list.innerHTML='<div class="notifications-empty"><strong>Пока нет уведомлений</strong><span>Здесь появятся события по вашим проектам.</span></div>';
     return;
   }
   const main=e.target.closest('[data-notification-url]');
   if(main){
     const url=main.dataset.notificationUrl||'#';
     if(url&&url!=='#') window.location.href=url;
   }
 });
 document.addEventListener('click',e=>{
   if(!e.target.closest('.notification-toggle')&&!e.target.closest('.notifications-dropdown'))menu?.classList.remove('show');
 });
 const read=$('#readNotifications');
 read?.addEventListener('click',()=>{});
 if(toggles.length){loadNotifications();setInterval(loadNotifications,10000)}
 })();