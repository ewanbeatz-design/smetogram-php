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
 const menu=$('#notificationMenu'),list=$('#notificationList'),badge=$('#notificationBadge'),topBadge=$('#topNotificationBadge');
 let lastId=0;
 function syncBadges(unread){
   const has=Number(unread)>0;
   [badge,topBadge].forEach(b=>{if(!b)return;b.hidden=!has;b.textContent=Number(unread)>99?'99+':String(unread)});
 }
 function loadNotifications(){
   fetch('api.php?action=notifications&since='+lastId,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>{
     if(d.items&&d.items.length){
       d.items.forEach(n=>{if(Number(n.id)>lastId)lastId=Number(n.id)});
       const html=d.items.map(n=>'<a class="notification-row '+(!Number(n.isRead)?'unread':'')+'" href="'+String(n.url||'#').replace(/"/g,'%22')+'"><span class="notification-main"><span class="notification-icon"><i class="bi '+(n.type==='message'?'bi-chat':n.type==='document'?'bi-file-earmark-text':n.type==='payment'?'bi-wallet2':n.type==='acceptance'?'bi-check2-square':n.type==='schedule'?'bi-calendar3':'bi-bell')+'"></i></span><span class="notification-content"><strong>'+escapeHtml(n.title)+'</strong><span>'+escapeHtml(n.body||'')+'</span><small>'+escapeHtml(n.createdAt||'')+'</small></span></span><span class="notification-unread-dot"></span></a>').join('');
       if(list?.querySelector('.notifications-empty'))list.innerHTML='';
       list?.insertAdjacentHTML('afterbegin',html);
     }
     syncBadges(d.unread||0);
   }).catch(()=>{});
 }
 toggles.forEach(t=>t.addEventListener('click',e=>{
   e.preventDefault();e.stopPropagation();
   document.querySelectorAll('.notifications-dropdown').forEach(x=>{if(x!==menu)x.classList.remove('show')});
   menu?.classList.toggle('show');
 }));
 document.addEventListener('click',e=>{
   if(!e.target.closest('.notification-toggle')&&!e.target.closest('.notifications-dropdown'))menu?.classList.remove('show');
 });
 const read=$('#readNotifications');
 read?.addEventListener('click',()=>{
   const csrf=document.querySelector('input[name=csrf]')?.value||'';
   fetch('api.php?action=read_notifications',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:'csrf='+encodeURIComponent(csrf)}).then(()=>{
     syncBadges(0);document.querySelectorAll('.notification-row').forEach(x=>x.classList.remove('unread'));
   });
 });
 if(toggles.length){loadNotifications();setInterval(loadNotifications,10000)}
})();
(function(){
  const sidebar=document.getElementById('appSidebar');
  if(!sidebar) return;
  sidebar.querySelectorAll('.nav-item[href]').forEach(link=>{
    link.addEventListener('click',()=>{
      if(window.matchMedia('(max-width: 760px)').matches){
        sidebar.classList.remove('sidebar-open');
      }
    });
  });
  document.querySelector('.sidebar-backdrop')?.addEventListener('click',()=>{
    sidebar.classList.remove('sidebar-open');
  });
  window.addEventListener('resize',()=>{
    if(!window.matchMedia('(max-width: 760px)').matches){
      sidebar.classList.remove('sidebar-open');
    }
  });
})();

(function(){
  function initRoomFancybox(){
    if(window.Fancybox){
      window.Fancybox.bind('[data-fancybox^="room-"]',{
        Thumbs:{autoStart:false},
        Toolbar:{
          display:{
            left:['infobar'],
            middle:[],
            right:['slideshow','thumbs','close']
          }
        },
        Carousel:{infinite:true}
      });
    }
  }

  function addRoomUploadSkeleton(form,input){
    const card=form.closest('.room-photo-card');
    if(!card) return null;
    let grid=card.querySelector('.room-photo-grid');
    if(!grid){
      const empty=card.querySelector('.room-photo-empty');
      grid=document.createElement('div');
      grid.className='room-photo-grid';
      empty?.replaceWith(grid);
    }
    const skeleton=document.createElement('div');
    skeleton.className='room-photo-thumb room-photo-thumb-skeleton';
    skeleton.innerHTML='<span class="room-photo-skeleton-shimmer"></span><span class="room-photo-skeleton-icon"><i class="bi bi-image"></i></span>';
    grid.prepend(skeleton);

    if(input.files?.[0]){
      const objectUrl=URL.createObjectURL(input.files[0]);
      skeleton.style.backgroundImage='url("'+objectUrl.replace(/"/g,'&quot;')+'")';
      skeleton.classList.add('has-preview');
      skeleton.dataset.objectUrl=objectUrl;
    }
    return skeleton;
  }

  function removeSkeleton(skeleton){
    if(!skeleton) return;
    if(skeleton.dataset.objectUrl) URL.revokeObjectURL(skeleton.dataset.objectUrl);
    skeleton.remove();
  }

  function uploadRoomPhoto(form, input){
    if(!form || !input || !input.files || !input.files.length) return;
    const progress=form.querySelector('.room-photo-progress');
    const bar=progress?.querySelector('.room-photo-progress-track span');
    const percent=progress?.querySelector('strong');
    const buttons=form.closest('.room-photo-actions')?.querySelectorAll('.outline-button');
    const skeleton=addRoomUploadSkeleton(form,input);

    if(progress) progress.classList.add('is-uploading');
    buttons?.forEach(b=>{b.classList.add('is-uploading');b.style.pointerEvents='none';});
    if(percent) percent.textContent='0%';
    if(bar) bar.style.width='0%';

    const xhr=new XMLHttpRequest();
    xhr.open('POST',form.getAttribute('action')||window.location.href,true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr.upload.addEventListener('progress',e=>{
      if(!e.lengthComputable) return;
      const value=Math.max(0,Math.min(100,Math.round(e.loaded/e.total*100)));
      if(bar) bar.style.width=value+'%';
      if(percent) percent.textContent=value+'%';
    });
    xhr.addEventListener('load',()=>{
      let data=null;
      try{data=JSON.parse(xhr.responseText)}catch(e){}
      if(xhr.status>=200 && xhr.status<300 && data?.ok){
        if(bar) bar.style.width='100%';
        if(percent) percent.textContent='100%';
        skeleton?.classList.add('is-complete');
        setTimeout(()=>window.location.reload(),220);
        return;
      }
      const message=data?.message||'Не удалось загрузить фотографию.';
      alert(message);
      removeSkeleton(skeleton);
      progress?.classList.remove('is-uploading');
      buttons?.forEach(b=>{b.classList.remove('is-uploading');b.style.pointerEvents='';});
      input.value='';
    });
    xhr.addEventListener('error',()=>{
      alert('Ошибка соединения при загрузке фотографии.');
      removeSkeleton(skeleton);
      progress?.classList.remove('is-uploading');
      buttons?.forEach(b=>{b.classList.remove('is-uploading');b.style.pointerEvents='';});
      input.value='';
    });
    const formData=new FormData(form);
    xhr.send(formData);
  }

  document.addEventListener('change',e=>{
    const input=e.target.closest('.room-photo-upload input[type="file"]');
    if(input) uploadRoomPhoto(input.form,input);
  });

  async function deleteRoomPhoto(form){
    const card=form.closest('.room-photo-thumb');
    if(!card || form.dataset.deleting==='1') return;
    form.dataset.deleting='1';
    const button=form.querySelector('button[type="submit"]');
    if(button){
      button.disabled=true;
      button.classList.add('is-deleting');
    }
    card.classList.add('is-deleting');

    try{
      const response=await fetch(window.location.href,{
        method:'POST',
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'Accept':'application/json'
        },
        body:new FormData(form),
        credentials:'same-origin'
      });
      const data=await response.json().catch(()=>null);
      if(!response.ok || !data?.ok){
        throw new Error(data?.message || 'Не удалось удалить фотографию.');
      }

      card.style.height=card.offsetHeight+'px';
      requestAnimationFrame(()=>{
        card.style.height='0px';
        card.style.margin='0';
        card.style.opacity='0';
        card.style.transform='scale(.94)';
      });
      setTimeout(()=>{
        const grid=card.parentElement;
        card.remove();
        if(grid && !grid.querySelector('.room-photo-thumb')){
          const empty=document.createElement('div');
          empty.className='room-photo-empty';
          empty.innerHTML='<i class="bi bi-camera"></i><span>Фотографии комнаты ещё не добавлены</span>';
          grid.replaceWith(empty);
        }
      },220);
    }catch(error){
      alert(error?.message || 'Не удалось удалить фотографию.');
      form.dataset.deleting='';
      if(button){
        button.disabled=false;
        button.classList.remove('is-deleting');
      }
      card.classList.remove('is-deleting');
    }
  }

  document.addEventListener('submit',e=>{
    const form=e.target.closest('.room-photo-delete-form');
    if(!form) return;
    e.preventDefault();
    deleteRoomPhoto(form);
  });


  // Workspace tabs: load project sections without a full page reload.
  (function(){
    let busy=false;
    async function loadWorkspace(url,push=true){
      if(busy)return;
      busy=true;
      document.documentElement.classList.add('workspace-loading');
      try{
        const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},credentials:'same-origin'});
        const html=await res.text();
        if(!res.ok)throw new Error('Не удалось загрузить раздел.');
        const doc=new DOMParser().parseFromString(html,'text/html');
        const next=doc.querySelector('.page-wrap');
        const current=document.querySelector('.page-wrap');
        if(!next||!current)throw new Error('Раздел вернул некорректную страницу.');
        current.replaceWith(next);
        if(push)history.pushState({workspace:true},'',url);
        window.scrollTo({top:0,behavior:'instant'});
        document.dispatchEvent(new CustomEvent('workspace:loaded'));
      }catch(error){
        // If AJAX navigation fails, fall back to the normal link behavior.
        window.location.href=url;
        return;
      }finally{
        busy=false;
        document.documentElement.classList.remove('workspace-loading');
      }
    }
    document.addEventListener('click',e=>{
      const link=e.target.closest('.workspace-tabs a[href]');
      if(!link||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||link.target==='_blank')return;
      e.preventDefault();
      loadWorkspace(link.href,true);
    });
    window.addEventListener('popstate',()=>{
      if(location.pathname.endsWith('/workspace.php')||location.pathname.endsWith('workspace.php'))loadWorkspace(location.href,false);
    });
    document.addEventListener('workspace:loaded',()=>{
      initRoomFancybox();
    });
  })();
  initRoomFancybox();
})();