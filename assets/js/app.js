document.addEventListener('input',e=>{const i=e.target.closest('[data-money]');if(i)i.value=i.value.replace(/[^0-9.,]/g,'').replace(',','.');});document.addEventListener('click',e=>{const b=e.target.closest('[data-confirm]');if(b&&!confirm(b.dataset.confirm))e.preventDefault();});
(function(){
 const $=s=>document.querySelector(s), input=$('#globalSearchInput'), results=$('#searchResults');
 if(input&&results){
  let timer;
  input.addEventListener('input',function(){
   clearTimeout(timer); const q=this.value.trim(); if(q.length<2){results.classList.remove('show');results.innerHTML='';return;}
   timer=setTimeout(async()=>{try{const r=await fetch('api.php?action=search&q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest'}});const d=await r.json();results.innerHTML=d.items.length?d.items.map(x=>'<a href="'+x.url+'"><i class="bi bi-folder2-open"></i><span><b>'+escapeHtml(x.title)+'</b><small>'+escapeHtml(x.meta||'')+'</small></span></a>').join(''):'<div class="notification-empty">Ничего не найдено</div>';results.classList.add('show')}catch(e){}},180);
  });
 }
 const bell=$('.notification-toggle'),menu=$('#notificationMenu'),list=$('#notificationList'),badge=$('#notificationBadge');
 let lastId=0;
 function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
 function loadNotifications(){
  fetch('api.php?action=notifications&since='+lastId,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>{
   if(d.items&&d.items.length){d.items.forEach(n=>{if(n.id>lastId)lastId=n.id});const html=d.items.map(n=>'<a class="notification-item '+(!Number(n.isRead)?'unread':'')+'" href="'+(n.url||'#')+'"><span class="notification-dot"></span><span><b>'+escapeHtml(n.title)+'</b><small>'+escapeHtml(n.body||'')+' · '+escapeHtml(n.createdAt)+'</small></span></a>').join('');if(list.querySelector('.notification-empty'))list.innerHTML='';list.insertAdjacentHTML('afterbegin',html)}
   if(d.unread>0){badge.hidden=false;badge.textContent=d.unread>99?'99+':d.unread}else badge.hidden=true;
  }).catch(()=>{});
 }
 if(bell&&menu){bell.addEventListener('click',e=>{e.stopPropagation();menu.classList.toggle('show')});document.addEventListener('click',e=>{if(!e.target.closest('#notifications'))menu.classList.remove('show')})}
 const read=$('#readNotifications'); if(read)read.addEventListener('click',()=>{fetch('api.php?action=read_notifications',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:'csrf='+encodeURIComponent(document.querySelector('input[name=csrf]')?.value||'')}).then(()=>{badge.hidden=true;badge.textContent='0';document.querySelectorAll('.notification-item').forEach(x=>x.classList.remove('unread'))})});
 if(bell){loadNotifications();setInterval(loadNotifications,10000)}
})();