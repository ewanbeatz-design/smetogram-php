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

  let aiEstimatePayload=null;

  function openAiEstimateModal(button){
    const modalEl=document.getElementById('aiEstimateModal');
    if(!modalEl || !window.bootstrap)return;
    aiEstimatePayload={
      projectId:button.dataset.projectId,
      roomId:button.dataset.roomId,
      roomName:button.dataset.roomName||'Комната',
      photoIds:(button.dataset.photoIds||'').split(',').map(Number).filter(Boolean)
    };
    document.getElementById('aiEstimateTitle').textContent='Расчёт: '+aiEstimatePayload.roomName;
    document.getElementById('aiEstimateSummary').textContent='Анализируем фотографии помещения…';
    document.getElementById('aiEstimateLoading').classList.remove('d-none');
    document.getElementById('aiEstimateResult').classList.add('d-none');
    document.getElementById('aiEstimateItems').innerHTML='';
    document.getElementById('aiEstimateFooter').innerHTML='<button type="button" class="outline-button" data-bs-dismiss="modal">Закрыть</button>';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    runAiEstimate();
  }

  async function runAiEstimate(){
    if(!aiEstimatePayload)return;
    const csrf=document.querySelector('input[name="csrf"]')?.value||'';
    const fd=new FormData();
    fd.append('csrf',csrf);fd.append('project_id',aiEstimatePayload.projectId);fd.append('room_id',aiEstimatePayload.roomId);
    aiEstimatePayload.photoIds.forEach(id=>fd.append('photo_ids[]',id));
    try{
      fd.append('action','room_estimate'); const res=await fetch('ai.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:fd,credentials:'same-origin'});
      const data=await res.json().catch(()=>null);
      if(!res.ok||!data?.ok)throw new Error(data?.error||'Не удалось выполнить AI-анализ.');
      aiEstimatePayload.items=data.items||[];
      document.getElementById('aiEstimateLoading').classList.add('d-none');
      document.getElementById('aiEstimateResult').classList.remove('d-none');
      document.getElementById('aiEstimateSummary').textContent=data.summary||('Найдено позиций: '+aiEstimatePayload.items.length);
      const list=document.getElementById('aiEstimateItems');
      if(!aiEstimatePayload.items.length){
        list.innerHTML='<div class="ai-estimate-empty"><i class="bi bi-search"></i><span>Уверенно определить работы по этим фото не удалось.</span></div>';
        return;
      }
      list.innerHTML=aiEstimatePayload.items.map((item,i)=>'<label class="ai-estimate-item"><input type="checkbox" checked data-ai-index="'+i+'"><span class="ai-estimate-item-main"><strong>'+escapeHtml(item.name)+'</strong><small>'+escapeHtml(item.reason||'Определено по фото')+'</small></span><span class="ai-estimate-item-values"><b>'+formatEstimateNumber(item.quantity)+'</b> '+escapeHtml(item.unit)+'<em>'+formatEstimateMoney(item.price)+'</em></span></label>').join('');
      document.getElementById('aiEstimateFooter').innerHTML='<button type="button" class="outline-button" data-bs-dismiss="modal">Отмена</button><button type="button" class="primary-button" id="aiEstimateApply"><i class="bi bi-check2"></i> Добавить выбранное в смету</button>';
      document.getElementById('aiEstimateApply').addEventListener('click',applyAiEstimate);
    }catch(error){
      document.getElementById('aiEstimateLoading').classList.add('d-none');
      document.getElementById('aiEstimateSummary').textContent=error?.message||'Ошибка AI.';
    }
  }

  function escapeHtml(value){
    return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  }
  function formatEstimateNumber(value){return Number(value||0).toLocaleString('ru-RU',{maximumFractionDigits:3});}
  function formatEstimateMoney(value){const n=Number(value||0);return n>0?n.toLocaleString('ru-RU',{maximumFractionDigits:0})+' ₽':'Цена уточняется';}

  async function applyAiEstimate(){
    const selected=[...document.querySelectorAll('#aiEstimateItems [data-ai-index]:checked')].map(el=>aiEstimatePayload.items[Number(el.dataset.aiIndex)]).filter(Boolean);
    if(!selected.length){alert('Выберите хотя бы одну позицию.');return;}
    const csrf=document.querySelector('input[name="csrf"]')?.value||'';
    const fd=new FormData();fd.append('csrf',csrf);fd.append('project_id',aiEstimatePayload.projectId);fd.append('op','ai_batch');fd.append('items_json',JSON.stringify(selected));
    const button=document.getElementById('aiEstimateApply');if(button){button.disabled=true;button.classList.add('is-loading');button.innerHTML='<i class="bi bi-arrow-repeat"></i> Добавляем…';}
    try{
      const res=await fetch('api.php?action=estimate_action',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
      const data=await res.json().catch(()=>null);if(!res.ok||!data?.ok)throw new Error(data?.error||'Не удалось добавить позиции.');
      window.location.href='project.php?id='+encodeURIComponent(aiEstimatePayload.projectId);
    }catch(error){alert(error?.message||'Не удалось добавить позиции.');if(button){button.disabled=false;button.classList.remove('is-loading');button.innerHTML='<i class="bi bi-check2"></i> Добавить выбранное в смету';}}
  }

  document.addEventListener('click',e=>{
    const button=e.target.closest('.room-ai-estimate');
    if(button){e.preventDefault();openAiEstimateModal(button);}
  });

  initRoomFancybox();
})();