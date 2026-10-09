/* Global beautiful alerts / confirmations */
(function(){
  if(window.__smetogramAlertsReady)return;
  window.__smetogramAlertsReady=true;
  function ensureHost(){
    let host=document.querySelector('.estimate-toast-host');
    if(!host){
      host=document.createElement('div');
      host.className='estimate-toast-host';
      host.setAttribute('aria-live','polite');
      host.setAttribute('aria-atomic','false');
      document.body.appendChild(host);
    }
    return host;
  }
  function showAlert(message,type='info',duration=4200){
    const host=ensureHost();
    const toast=document.createElement('div');
    toast.className='estimate-toast estimate-toast-'+(type==='error'?'error':type==='success'?'success':'info');
    const icon=type==='error'?'bi-exclamation-triangle-fill':type==='success'?'bi-check-circle-fill':'bi-info-circle-fill';
    toast.innerHTML='<span class="estimate-toast-icon"><i class="bi '+icon+'"></i></span><span class="estimate-toast-text"></span><button type="button" class="estimate-toast-close" aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>';
    toast.querySelector('.estimate-toast-text').textContent=String(message??'');
    host.appendChild(toast);
    requestAnimationFrame(()=>toast.classList.add('is-visible'));
    const close=()=>{toast.classList.remove('is-visible');setTimeout(()=>toast.remove(),220)};
    toast.querySelector('.estimate-toast-close').addEventListener('click',close);
    if(duration>0)setTimeout(close,duration);
    return toast;
  }
  window.smetogramAlert=showAlert;
  window.alert=function(message){showAlert(message,'info');};
  window.smetogramConfirm=function(message,onConfirm){
    document.querySelector('.smetogram-confirm-backdrop')?.remove();
    const backdrop=document.createElement('div');
    backdrop.className='smetogram-confirm-backdrop';
    backdrop.innerHTML='<div class="smetogram-confirm" role="dialog" aria-modal="true" aria-labelledby="smetogramConfirmTitle"><div class="smetogram-confirm-icon"><i class="fa-solid fa-circle-question"></i></div><div class="smetogram-confirm-copy"><strong id="smetogramConfirmTitle">Подтвердите действие</strong><p></p></div><div class="smetogram-confirm-actions"><button type="button" class="outline-button smetogram-confirm-cancel">Отмена</button><button type="button" class="primary-button smetogram-confirm-ok">Продолжить</button></div></div>';
    backdrop.querySelector('p').textContent=String(message??'Вы уверены?');
    document.body.appendChild(backdrop);
    const close=()=>{backdrop.classList.remove('is-visible');setTimeout(()=>backdrop.remove(),180)};
    backdrop.addEventListener('click',e=>{if(e.target===backdrop)close()});
    backdrop.querySelector('.smetogram-confirm-cancel').addEventListener('click',close);
    backdrop.querySelector('.smetogram-confirm-ok').addEventListener('click',()=>{close();if(typeof onConfirm==='function')onConfirm()});
    requestAnimationFrame(()=>backdrop.classList.add('is-visible'));
    setTimeout(()=>backdrop.querySelector('.smetogram-confirm-ok')?.focus(),30);
    const onKey=e=>{if(e.key==='Escape'){close();document.removeEventListener('keydown',onKey)}};
    document.addEventListener('keydown',onKey);
  };
  document.addEventListener('click',e=>{
    const button=e.target.closest('[data-confirm]');
    if(!button)return;
    e.preventDefault();
    e.stopImmediatePropagation();
    window.smetogramConfirm(button.dataset.confirm||'Подтвердите действие.',()=>{
      if(button.tagName==='A' && button.href) window.location.href=button.href;
      else if(button.form) button.form.requestSubmit(button);
    });
  },true);
})();
document.addEventListener('input',e=>{const i=e.target.closest('[data-money]');if(i)i.value=i.value.replace(/[^0-9.,]/g,'').replace(',','.');});
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
         return '<a class="global-search-result" href="'+url+'"><span class="global-result-icon"><i class="bi '+(x.type==="document"?'bi-file-earmark-text':x.type==="estimate"?'bi-list-check':'bi-folder2-open')+'"></i></span><span class="global-result-content"><strong>'+escapeHtml(x.title)+'</strong><span>'+escapeHtml(x.meta||'')+'</span><small>'+escapeHtml(x.group||'Проект')+'</small></span><i class="fa-solid fa-chevron-right"></i></a>';
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
 let notificationsReady=false;
 function syncBadges(unread){
   const has=Number(unread)>0;
   [badge,topBadge].forEach(b=>{if(!b)return;b.hidden=!has;b.textContent=Number(unread)>99?'99+':String(unread)});
 }
 function notificationIcon(type){
   return type==='message'?'fa-comments':type==='document'?'fa-file-lines':type==='payment'?'fa-wallet':type==='acceptance'?'fa-check-circle':type==='schedule'?'fa-calendar-days':type==='team'?'fa-users':'fa-bell';
 }
 function showNotificationToast(n){
   let toast=document.getElementById('notificationToast');
   if(!toast){
     toast=document.createElement('div');
     toast.id='notificationToast';
     toast.className='notification-toast';
     document.body.appendChild(toast);
   }
   toast.innerHTML='<span class="notification-toast-icon"><i class="fa-solid '+notificationIcon(n.type)+'"></i></span><span class="notification-toast-copy"><strong>'+escapeHtml(n.title||'Новое оповещение')+'</strong><small>'+escapeHtml(n.body||'')+'</small></span><button type="button" class="notification-toast-close" aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>';
   toast.querySelector('.notification-toast-close')?.addEventListener('click',()=>toast.classList.remove('show'),{once:true});
   requestAnimationFrame(()=>toast.classList.add('show'));
   clearTimeout(toast._hideTimer);
   toast._hideTimer=setTimeout(()=>toast.classList.remove('show'),6500);
 }
 function loadNotifications(){
   fetch('api.php?action=notifications&since='+lastId,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'}).then(r=>r.json()).then(d=>{
     const items=Array.isArray(d.items)?d.items:[];
     if(items.length){
       items.forEach(n=>{if(Number(n.id)>lastId)lastId=Number(n.id)});
       const html=items.map(n=>'<a class="notification-row '+(!Number(n.isRead)?'unread':'')+'" href="'+String(n.url||'#').replace(/"/g,'%22')+'"><span class="notification-main"><span class="notification-icon"><i class="fa-solid '+notificationIcon(n.type)+'"></i></span><span class="notification-content"><strong>'+escapeHtml(n.title)+'</strong><span>'+escapeHtml(n.body||'')+'</span><small>'+escapeHtml(n.createdAt||'')+'</small></span></span><span class="notification-unread-dot"></span></a>').join('');
       if(list?.querySelector('.notifications-empty'))list.innerHTML='';
       list?.insertAdjacentHTML('afterbegin',html);
       if(notificationsReady && Number(items[0]?.id||0)>0) showNotificationToast(items[0]);
     }
     syncBadges(d.unread||0);
     notificationsReady=true;
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
      window.Fancybox.bind('[data-fancybox^="room-"],[data-fancybox^="acceptance-"]',{
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
    skeleton.innerHTML='<span class="room-photo-skeleton-shimmer"></span><span class="room-photo-skeleton-icon"><i class="fa-solid fa-image"></i></span>';
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
        setTimeout(()=>{
          const card=skeleton?.closest('.room-photo-card');
          const grid=card?.querySelector('.room-photo-grid');
          if(grid && skeleton){
            skeleton.classList.remove('room-photo-thumb-skeleton','has-preview');
            skeleton.classList.add('is-complete');
            skeleton.innerHTML='<span class="room-photo-skeleton-icon"><i class="fa-solid fa-check"></i></span>';
            setTimeout(()=>skeleton.remove(),700);
          }
          progress?.classList.remove('is-uploading');
          buttons?.forEach(b=>{b.classList.remove('is-uploading');b.style.pointerEvents='';});
          input.value='';
          window.smetogramAlert?.('Фотография успешно добавлена.','success',2600);
        },220);
        return;
      }
      const message=data?.message||'Не удалось загрузить фотографию.';
      window.smetogramAlert?.(message,'error',4200);
      removeSkeleton(skeleton);
      progress?.classList.remove('is-uploading');
      buttons?.forEach(b=>{b.classList.remove('is-uploading');b.style.pointerEvents='';});
      input.value='';
    });
    xhr.addEventListener('error',()=>{
      window.smetogramAlert?.('Ошибка соединения при загрузке фотографии.','error',4200);
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

  document.addEventListener('change',e=>{
    const input=e.target.closest('.acceptance-photo-upload input[type="file"]');
    if(!input || !input.files || !input.files.length) return;
    const form=input.form;
    const stageId=form?.querySelector('[name="stage_id"]')?.value;
    if(!form || !stageId) return;
    const xhr=new XMLHttpRequest();
    const buttons=form.closest('.acceptance-photo-actions')?.querySelectorAll('.outline-button');
    buttons?.forEach(b=>{b.classList.add('is-uploading');b.style.pointerEvents='none';});
    xhr.open('POST',form.getAttribute('action')||window.location.href,true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr.addEventListener('load',()=>{
      let data=null; try{data=JSON.parse(xhr.responseText)}catch(err){}
      buttons?.forEach(b=>{b.classList.remove('is-uploading');b.style.pointerEvents='';});
      input.value='';
      if(xhr.status>=200&&xhr.status<300&&data?.ok){
        const photo=data.photo;
        const stageCard=form.closest('.acceptance-stage-card');
        if(photo&&stageCard){
          let grid=stageCard.querySelector('.acceptance-photo-grid');
          stageCard.querySelector('.acceptance-photo-empty')?.remove();
          if(!grid){ grid=document.createElement('div'); grid.className='acceptance-photo-grid'; stageCard.querySelector('.acceptance-photo-actions')?.after(grid); }
          const thumb=document.createElement('div'); thumb.className='acceptance-photo-thumb';
          const link=document.createElement('a'); link.href=photo.path; link.dataset.fancybox='acceptance-'+photo.stageId;
          const img=document.createElement('img'); img.src=photo.path; img.alt=photo.originalName||'Фото приёмки'; img.loading='lazy'; link.appendChild(img);
          const del=document.createElement('form'); del.method='post'; del.className='acceptance-photo-delete';
          del.innerHTML='<input type="hidden" name="csrf" value="'+(document.querySelector("input[name=csrf]")?.value||"")+'"><input type="hidden" name="action" value="delete_acceptance_photo"><input type="hidden" name="photo_id" value="'+photo.id+'"><button type="submit" class="icon-button" title="Удалить"><i class="fa-solid fa-trash-can"></i></button>';
          thumb.append(link,del); grid.prepend(thumb);
        }
        window.smetogramAlert?.('Фото приёмки добавлено.','success',2600);
      }else{
        window.smetogramAlert?.(data?.message||'Не удалось загрузить фотографию.','error',4200);
      }
    });
    xhr.addEventListener('error',()=>{
      buttons?.forEach(b=>{b.classList.remove('is-uploading');b.style.pointerEvents='';});
      input.value='';
      window.smetogramAlert?.('Ошибка соединения при загрузке фотографии.','error',4200);
    });
    xhr.send(new FormData(form));
  });

  document.addEventListener('submit',async e=>{
    const form=e.target.closest('.acceptance-photo-delete');
    if(!form)return;
    e.preventDefault();
    if(form.dataset.deleting==='1')return;
    form.dataset.deleting='1';
    try{
      const res=await fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:new FormData(form),credentials:'same-origin'});
      const data=await res.json().catch(()=>null);
      if(!res.ok||!data?.ok)throw new Error(data?.message||'Не удалось удалить фотографию.');
      form.closest('.acceptance-photo-thumb')?.remove();
      window.smetogramAlert?.('Фото удалено.','success',2200);
    }catch(err){
      window.smetogramAlert?.(err.message||'Не удалось удалить фотографию.','error',4200);
      form.dataset.deleting='';
    }
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
          empty.innerHTML='<i class="fa-solid fa-camera"></i><span>Фотографии комнаты ещё не добавлены</span>';
          grid.replaceWith(empty);
        }
      },220);
    }catch(error){
      window.smetogramAlert?.(error?.message || 'Не удалось удалить фотографию.','error',4200);
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


  /* WhatsApp/Telegram-style chat: AJAX send + polling, no page reload. */
  (function(){
    let chatTimer=null;
    let chatAbort=null;
    let chatRequestBusy=false;

    function esc(value){
      return String(value??'').replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
    }
    function initChat(){
      const root=document.querySelector('[data-chat-root]');
      if(!root || root.dataset.chatReady==='1') return;
      root.dataset.chatReady='1';
      const list=root.querySelector('[data-chat-messages]');
      const form=root.querySelector('[data-chat-form]');
      const input=root.querySelector('[data-chat-input]');
      const send=root.querySelector('[data-chat-send]');
      const status=root.querySelector('[data-chat-status]');
      if(!list||!form||!input||!send) return;

      const projectId=root.dataset.projectId;
      const channel=root.dataset.channel;
      const currentUserId=Number(root.dataset.userId||0);
      let lastId=Number(root.dataset.lastId||0);
      let atBottom=true;

      function scrollBottom(force=false){
        const distance=list.scrollHeight-list.scrollTop-list.clientHeight;
        if(force || distance<100) list.scrollTop=list.scrollHeight;
      }
      function setStatus(text,ok=false){
        if(!status)return;
        status.textContent=text||'';
        status.classList.toggle('is-online',!!ok);
      }
      function appendMessage(m,animate=true){
        if(!m || !m.id || list.querySelector('[data-message-id="'+CSS.escape(String(m.id))+'"]')) return;
        const mine=Number(m.authorId)===currentUserId;
        const name=m.authorName||'Пользователь';
        const initials=name.trim().slice(0,2).toUpperCase()||'П';
        const row=document.createElement('div');
        row.className='chat-message '+(mine?'mine':'');
        row.dataset.messageId=m.id;
        row.innerHTML='<div class="member-avatar">'+esc(initials)+'</div><div class="chat-bubble-wrap"><strong>'+esc(name)+'</strong><p>'+esc(m.body).replace(/\n/g,'<br>')+'</p><small>'+esc(m.createdAt||'')+'</small></div>';
        list.appendChild(row);
        lastId=Math.max(lastId,Number(m.id));
        if(animate) row.classList.add('chat-message-new');
      }
      async function poll(){
        if(chatRequestBusy)return;
        chatRequestBusy=true;
        try{
          if(chatAbort)chatAbort.abort();
          chatAbort=new AbortController();
          const url='workspace.php?view=chat&id='+encodeURIComponent(projectId)+'&channel='+encodeURIComponent(channel)+'&chat_poll=1&after='+encodeURIComponent(lastId);
          const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin',signal:chatAbort.signal,cache:'no-store'});
          const data=await res.json();
          if(!res.ok||!data.ok)throw new Error(data.message||'Не удалось обновить чат.');
          let added=0;
          (data.messages||[]).forEach(m=>{appendMessage(m,true);added++;});
          if(added)scrollBottom(false);
          setStatus('В сети',true);
        }catch(e){
          if(e.name!=='AbortError')setStatus('Нет связи');
        }finally{chatRequestBusy=false;}
      }
      form.addEventListener('submit',async e=>{
        e.preventDefault();
        const body=input.value.trim();
        if(!body||send.disabled)return;
        send.disabled=true;
        input.disabled=true;
        setStatus('Отправка…');
        try{
          const fd=new FormData(form);
          fd.set('body',body);
          const res=await fetch('workspace.php?view=chat&id='+encodeURIComponent(projectId)+'&channel='+encodeURIComponent(channel),{
            method:'POST',body:fd,credentials:'same-origin',
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
          });
          const data=await res.json();
          if(!res.ok||!data.ok)throw new Error(data.message||'Не удалось отправить сообщение.');
          input.value='';
          if(data.chatMessage)appendMessage(data.chatMessage,true);
          scrollBottom(true);
          setStatus('В сети',true);
          input.focus();
        }catch(e){
          window.smetogramAlert?.(e.message||'Не удалось отправить сообщение.','error',4200);
          setStatus('Нет связи');
        }finally{
          input.disabled=false;send.disabled=false;input.focus();
        }
      });
      input.addEventListener('keydown',e=>{
        if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){
          e.preventDefault();
          form.requestSubmit();
        }
      });
      input.addEventListener('input',()=>{
        input.style.height='auto';
        input.style.height=Math.min(input.scrollHeight,130)+'px';
      });
      list.addEventListener('scroll',()=>{
        const distance=list.scrollHeight-list.scrollTop-list.clientHeight;
        atBottom=distance<100;
      });
      scrollBottom(true);
      setStatus('В сети',true);
      poll();
      chatTimer=setInterval(poll,2500);
      root.addEventListener('chat:destroy',()=>{if(chatTimer)clearInterval(chatTimer);if(chatAbort)chatAbort.abort();});
    }
    document.addEventListener('workspace:loaded',initChat);
    initChat();
  })();

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
        window.scrollTo({top:0,behavior:'auto'});
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
/* Global image lightbox: every real page image can be opened in Fancybox. */
(function(){
  if(window.__smetogramGlobalFancyboxReady)return;
  window.__smetogramGlobalFancyboxReady=true;

  function markImages(root){
    (root||document).querySelectorAll?.('img').forEach(img=>{
      if(img.dataset.fancyboxIgnore==='1') return;
      img.classList.add('smetogram-fancybox-image');
    });
  }

  function openImage(img){
    if(!window.Fancybox || !img) return;
    const src=img.currentSrc || img.src;
    if(!src || src.startsWith('data:')) return;
    window.Fancybox.show([{
      src:src,
      type:'image',
      caption:img.getAttribute('data-caption') || img.alt || ''
    }]);
  }

  function init(){
    markImages(document);
    document.addEventListener('click',e=>{
      const img=e.target.closest?.('img');
      if(!img || img.dataset.fancyboxIgnore==='1') return;
      const linked=img.closest('a[data-fancybox]');
      if(linked) return; // Room/acceptance galleries already use the configured Fancybox gallery.
      e.preventDefault();
      e.stopPropagation();
      openImage(img);
    },true);

    new MutationObserver(mutations=>{
      for(const mutation of mutations){
        for(const node of mutation.addedNodes){
          if(node.nodeType===1) markImages(node);
        }
      }
    }).observe(document.body,{childList:true,subtree:true});
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',init,{once:true});
  }else{
    init();
  }
})();

/* Convert Bootstrap alerts into the Smetogram toast style. */
(function(){
  function convert(root){
    (root||document).querySelectorAll?.('.alert').forEach(el=>{
      if(el.dataset.smetogramAlert==='1') return;
      const cls=el.className||'';
      let type=cls.includes('alert-danger')||cls.includes('alert-warning')?'error':cls.includes('alert-success')?'success':'info';
      const message=(el.textContent||'').trim();
      if(!message) return;
      el.dataset.smetogramAlert='1';
      el.classList.add('smetogram-alert-hidden');
      window.smetogramAlert?.(message,type,4200);
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',()=>convert(document),{once:true}); else convert(document);
  new MutationObserver(mutations=>{
    for(const m of mutations){
      for(const node of m.addedNodes){
        if(node.nodeType===1) convert(node);
      }
    }
  }).observe(document.body,{childList:true,subtree:true});
})();

/* Project cards open on click; inner controls keep their own actions. */
(function(){
  if(window.__smetogramProjectCardClickReady)return;
  window.__smetogramProjectCardClickReady=true;
  document.addEventListener('click',e=>{
    const card=e.target.closest('.project-card[data-project-id]');
    if(!card)return;
    if(e.defaultPrevented)return;
    if(e.target.closest('a,button,input,select,textarea,form,[data-bs-toggle],[data-stage-manage]'))return;
    if(e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;
    const link=card.querySelector('.project-open-link[href]');
    if(!link)return;
    window.location.href=link.href;
  });
})();

/* Project card stage manager — AJAX add/edit/delete. */
(function(){
 if(window.__smetogramCardStagesReady)return;
 window.__smetogramCardStagesReady=true;
 const $=(s,r=document)=>r.querySelector(s);
 let projectId=0;
 const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
 const date=v=>{const m=String(v??'').match(/^\d{4}-\d{2}-\d{2}/);return m?m[0]:''};
 const labels={planned:'Запланировано',in_progress:'В работе',done:'Завершено',blocked:'Заблокировано'};
 const card=()=>document.querySelector('.project-card[data-project-id="'+projectId+'"]');
 function reset(){
  $('[data-stage-form]').reset(); $('[data-stage-project]').value=projectId; $('[data-stage-id]').value='';
  $('[data-stage-status]').value='planned'; $('[data-stage-form-title]').textContent='Новый этап';
  $('[data-stage-submit]').innerHTML='<i class="fa-solid fa-plus"></i> Добавить этап'; $('[data-stage-cancel]').hidden=true;
 }
 function render(items){
  const list=$('[data-stage-list]');
  if(!items.length){list.innerHTML='<div class="stage-manager-empty"><i class="fa-solid fa-list-check"></i><strong>Этапов пока нет</strong><span>Добавьте первый этап ниже.</span></div>';return;}
  list.innerHTML=items.map(x=>{
   const amount=Number(x.paymentMilestone||0)>0?Number(x.paymentMilestone).toLocaleString('ru-RU')+' ₽':'Без суммы';
   const dates=[date(x.startsAt),date(x.endsAt)].filter(Boolean);
   const cls='stage-status-'+String(x.status||'planned').replace('_','-');
   const total=Number(x.paymentMilestone||0);
   const paid=Math.min(total,Math.max(0,Number(x.paidAmount||0)));
   const remaining=Math.max(0,total-paid);
   const paymentInfo=total>0
    ? '<div class="stage-payment-box '+(remaining<=0?'is-paid':'')+'"><div class="stage-payment-summary"><span><i class="fa-solid fa-wallet"></i> Оплачено <strong>'+paid.toLocaleString('ru-RU')+' ₽</strong> из '+total.toLocaleString('ru-RU')+' ₽</span><strong class="stage-payment-remaining">'+(remaining>0?'Остаток '+remaining.toLocaleString('ru-RU')+' ₽':'Оплачено полностью')+'</strong></div>'+(remaining>0?'<div class="stage-payment-actions"><input class="stage-payment-input" data-stage-payment-input="'+x.id+'" inputmode="decimal" placeholder="Аванс / платёж" aria-label="Сумма оплаты"><button type="button" class="stage-payment" data-stage-payment="'+x.id+'"><i class="fa-solid fa-circle-plus"></i><span>Внести оплату</span></button></div>':'<div class="stage-payment-complete"><i class="fa-solid fa-circle-check"></i> Все платежи по этапу внесены</div>')+'</div>'
    : '';
   return '<div class="stage-manager-item"><div class="stage-manager-item-main"><div class="stage-manager-item-title"><strong>'+esc(x.title)+'</strong><span class="stage-status '+cls+'">'+esc(labels[x.status]||x.status)+'</span></div><div class="stage-manager-item-meta"><span><i class="fa-solid fa-calendar-days"></i> '+esc(dates.length?dates.join(' — '):'Даты не указаны')+'</span><span><i class="fa-solid fa-wallet"></i> Общая сумма '+esc(amount)+'</span></div>'+paymentInfo+'</div><div class="stage-manager-item-actions"><button type="button" class="stage-edit" data-stage-edit="'+x.id+'"><i class="fa-solid fa-pen"></i><span>Изменить</span></button><button type="button" class="stage-delete" data-stage-delete="'+x.id+'"><i class="fa-solid fa-trash-can"></i></button></div></div>';
  }).join('');
 }
 async function load(){
  const r=await fetch('dashboard.php?stages_for='+encodeURIComponent(projectId),{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
  const d=await r.json(); if(!r.ok||!d.ok)throw new Error(d.message||'Не удалось загрузить этапы.');
  render(d.items||[]); return d.items||[];
 }
 function updateCard(items){
  const c=card();if(!c)return;
  const count=items.length,done=items.filter(x=>x.status==='done').length,active=items.find(x=>x.status==='in_progress'),next=items.find(x=>x.status==='planned');
  const title=active?.title||next?.title||'Этапы ещё не добавлены';
  c.querySelector('.project-stage-head strong').textContent=count?done+' / '+count:'—';
  c.querySelector('.project-stage-title').textContent=title;
  c.querySelector('.project-stage-track span').style.width=(count?Math.round(done/count*100):0)+'%';
 }
 function edit(item){
  $('[data-stage-id]').value=item.id; $('[data-stage-project]').value=projectId; $('[data-stage-title]').value=item.title||'';
  $('[data-stage-start]').value=date(item.startsAt); $('[data-stage-end]').value=date(item.endsAt);
  $('[data-stage-status]').value=item.status||'planned'; $('[data-stage-total]').value=Number(item.paymentMilestone||0)||'';
  $('[data-stage-form-title]').textContent='Редактирование этапа'; $('[data-stage-submit]').innerHTML='<i class="fa-solid fa-check"></i> Сохранить изменения'; $('[data-stage-cancel]').hidden=false;
  $('[data-stage-title]').focus({preventScroll:true});
 }
 async function send(form){
  const r=await fetch('dashboard.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form),credentials:'same-origin'});
  const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'Не удалось сохранить этап.');
  const items=await load();updateCard(items);reset();window.smetogramAlert(d.message,'success');
 }
 document.addEventListener('click',async e=>{
  const open=e.target.closest('[data-stage-manage]');
  if(open){
   e.preventDefault();e.stopPropagation();projectId=Number(open.dataset.projectId||0);
   const modalEl=$('#projectStageManager');$('#stageManagerTitle').textContent=open.dataset.projectName||'Этапы проекта';reset();
   const list=$('[data-stage-list]');list.innerHTML='<div class="stage-manager-loading">Загружаем этапы…</div>';
   bootstrap.Modal.getOrCreateInstance(modalEl).show();
   try{await load();}catch(err){window.smetogramAlert(err.message,'error');}
   return;
  }
  const paymentBtn=e.target.closest('[data-stage-payment]');
  if(paymentBtn){
   e.preventDefault();
   paymentBtn.disabled=true;
   try{
    const input=document.querySelector('[data-stage-payment-input="'+paymentBtn.dataset.stagePayment+'"]');
    const raw=String(input?.value||'').replace(/\s/g,'').replace(',','.');
    const amount=Number(raw);
    if(!Number.isFinite(amount)||amount<=0)throw new Error('Укажите сумму оплаты.');
    const fd=new FormData();
    fd.append('csrf',$('[data-stage-form] input[name="csrf"]').value);
    fd.append('stage_action','payment');
    fd.append('project_id',projectId);
    fd.append('stage_id',paymentBtn.dataset.stagePayment);
    fd.append('payment_amount',String(amount));
    const r=await fetch('dashboard.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd,credentials:'same-origin'});
    const d=await r.json();
    if(!r.ok||!d.ok)throw new Error(d.message||'Не удалось внести оплату.');
    const items=await load();updateCard(items);
    if(input)input.value='';
    window.smetogramAlert(d.message,'success');
   }catch(err){
    window.smetogramAlert(err.message,'error');
   }finally{
    paymentBtn.disabled=false;
   }
   return;
  }
  const editBtn=e.target.closest('[data-stage-edit]');
  if(editBtn){try{const items=await load();const item=items.find(x=>String(x.id)===String(editBtn.dataset.stageEdit));if(item)edit(item);}catch(err){window.smetogramAlert(err.message,'error');}return;}
  const del=e.target.closest('[data-stage-delete]');
  if(del){e.preventDefault();window.smetogramConfirm('Удалить этот этап проекта?',async()=>{
   try{const fd=new FormData();const csrfInput=document.querySelector('[data-stage-form] input[name="csrf"]');fd.append('csrf',csrfInput?csrfInput.value:'');fd.append('stage_action','delete');fd.append('project_id',projectId);fd.append('stage_id',del.dataset.stageDelete);const r=await fetch('dashboard.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd,credentials:'same-origin'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'Не удалось удалить этап.');const items=await load();updateCard(items);window.smetogramAlert(d.message,'success');}catch(err){window.smetogramAlert(err.message,'error');}
  });return;}
  if(e.target.closest('[data-stage-cancel]'))reset();
 });
 document.addEventListener('submit',async e=>{
  const form=e.target.closest('[data-stage-form]');if(!form)return;e.preventDefault();const b=$('[data-stage-submit]');b.disabled=true;
  const stageIdInput=form.querySelector('[data-stage-id]'); const action=stageIdInput&&stageIdInput.value?'update':'add';form.querySelector('[name="stage_action"]').value=action;
  try{await send(form);}catch(err){window.smetogramAlert(err.message,'error');}finally{b.disabled=false;}
 });
})();

/* Estimate -> stage payments: edit total and add cumulative advances without reload. */
(function(){
  if(window.__smetogramEstimatePaymentsReady)return;
  window.__smetogramEstimatePaymentsReady=true;
  const money=(n)=>Number(n||0).toLocaleString('ru-RU',{maximumFractionDigits:0})+' ₽';
  const num=(v)=>{
    let s=String(v??'').replace(/\u00a0/g,' ').trim().replace(/\s+/g,'').replace(/₽/g,'').replace(',','.');
    if(!s)return 0;
    s=s.replace(/[^0-9.-]/g,'');
    const n=Number(s);
    return Number.isFinite(n)?n:NaN;
  };
  async function stageRequest(stageId,action,extra={}){
    const card=document.querySelector('[data-payment-stage-card="'+CSS.escape(String(stageId))+'"]');
    const csrfInput=document.querySelector('input[name="csrf"]'); const csrf=csrfInput ? csrfInput.value : '';
    const fd=new FormData();fd.append('csrf',csrf||'');fd.append('stage_action',action);fd.append('project_id',new URLSearchParams(location.search).get('id')||'0');fd.append('stage_id',stageId);
    Object.entries(extra).forEach(([k,v])=>fd.append(k,String(v)));
    const res=await fetch('dashboard.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:fd,credentials:'same-origin'});
    const data=await res.json().catch(()=>null);
    if(!res.ok||!data?.ok)throw new Error(data?.message||'Не удалось сохранить оплату.');
    if(card){
      const total=Number(data.amount ?? (card.querySelector('[data-stage-amount]') ? card.querySelector('[data-stage-amount]').value : 0));
      const paid=Number(data.paidAmount??card.querySelector('[data-stage-paid]')?.textContent.replace(/\D/g,'')??0);
      const remaining=Math.max(0,total-paid),percent=total>0?Math.min(100,Math.round(paid/total*100)):0;
      const amountInput=card.querySelector('[data-stage-amount]');if(amountInput&&action==='amount')amountInput.value=money(total).replace(' ₽','');
      const paidEl=card.querySelector('[data-stage-paid]');if(paidEl)paidEl.textContent=money(paid);
      const remEl=card.querySelector('[data-stage-remaining]');if(remEl)remEl.textContent=money(remaining);
      const bar=card.querySelector('[data-stage-progress]');if(bar)bar.style.width=percent+'%';
      const payInput=card.querySelector('[data-stage-payment-input]');if(payInput&&action==='payment')payInput.value='';
      const payBtn=card.querySelector('[data-stage-payment]');if(payBtn){payBtn.disabled=remaining<=0;payBtn.innerHTML=remaining<=0?'<i class="fa-solid fa-circle-check"></i> Этап оплачен':'<i class="fa-solid fa-circle-plus"></i> Внести оплату';}
      const status=card.querySelector('.payment-stage-card-head em');if(status)status.textContent=remaining<=0?'Оплачено':(paid>0?'Аванс':'Не оплачено');
      const metrics=[...document.querySelectorAll('.payment-metrics .metric-card strong')];
      if(metrics.length>=3){
        let totalAll=0,paidAll=0;
        document.querySelectorAll('[data-payment-stage-card]').forEach(x=>{
          const ai=x.querySelector('[data-stage-amount]'); const pi=x.querySelector('[data-stage-paid]'); totalAll+=num(ai ? ai.value : 0); paidAll+=num(pi ? pi.textContent : 0);
        });
        metrics[0].textContent=money(totalAll);metrics[1].textContent=money(paidAll);metrics[2].textContent=money(Math.max(0,totalAll-paidAll));
      }
    }
    window.smetogramAlert?.(data.message||'Сохранено.','success',3200);
  }
  document.addEventListener('click',async e=>{
    const amountBtn=e.target.closest('[data-stage-save-amount]');
    const payBtn=e.target.closest('[data-stage-payment]');
    if(!amountBtn&&!payBtn)return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const btn=amountBtn||payBtn,stageId=btn.dataset.stageSaveAmount||btn.dataset.stagePayment;
    const card=btn.closest('[data-payment-stage-card]');if(!card)return;
    btn.disabled=true;
    try{
      if(amountBtn){
        const input=card.querySelector('[data-stage-amount]'); const amount=num(input ? input.value : '');
        if(!Number.isFinite(amount)||amount<0)throw new Error('Укажите корректную сумму этапа.');
        await stageRequest(stageId,'amount',{stage_amount:amount});
      }else{
        const input=card.querySelector('[data-stage-payment-input]'); const amount=num(input ? input.value : '');
        if(!Number.isFinite(amount)||amount<=0)throw new Error('Укажите сумму аванса / платежа.');
        await stageRequest(stageId,'payment',{payment_amount:amount});
      }
    }catch(err){window.smetogramAlert?.(err.message||'Не удалось сохранить.','error',4200);}finally{btn.disabled=false;}
  },true);
})();

/* Global phone + email input masks. */
(function(){
  if(window.__smetogramContactMasksReady)return;
  window.__smetogramContactMasksReady=true;

  const phoneSelector=[
    'input[type="tel"]',
    'input[name="phone"]',
    'input[name="invitedPhone"]',
    'input[name="clientPhone"]',
    'input[id*="phone" i]',
    'input[placeholder*="999" i]',
    'input[placeholder*="телефон" i]'
  ].join(',');

  const emailSelector=[
    'input[type="email"]',
    'input[name="email"]',
    'input[name="clientEmail"]',
    'input[autocomplete="email"]',
    'input[id*="email" i]'
  ].join(',');

  function setCaret(input,pos){
    try{input.setSelectionRange(pos,pos);}catch(_){}
  }

  function applyPhoneMask(input){
    if(input.dataset.smetogramPhoneMask==='1')return;
    input.dataset.smetogramPhoneMask='1';
    input.setAttribute('inputmode','tel');
    input.setAttribute('autocomplete','tel');

    const normalizeDigits=(raw)=>{
      let digits=String(raw||'').replace(/\D/g,'');
      if(!digits)return '';
      const rawValue=String(raw||'');
      if(/^\s*\+7/.test(rawValue)){
        digits=digits.slice(1);
      }else if(digits[0]==='8'||digits[0]==='7'){
        digits=digits.slice(1);
      }
      return ('7'+digits).slice(0,11);
    };

    const format=(raw)=>{
      const digits=normalizeDigits(raw);
      if(!digits)return '';
      const subscriber=digits[0]==='7'?digits.slice(1):digits;
      let out='+7';
      if(subscriber.length)out+=' ('+subscriber.slice(0,3);
      if(subscriber.length>=3)out+=')';
      if(subscriber.length>3)out+=' '+subscriber.slice(3,6);
      if(subscriber.length>=6)out+='-'+subscriber.slice(6,8);
      if(subscriber.length>=8)out+='-'+subscriber.slice(8,10);
      return out;
    };

    const caretAfterDigits=(value,count)=>{
      if(count<=0)return Math.min(3,value.length);
      let seen=0;
      for(let i=3;i<value.length;i++){
        if(/\d/.test(value[i])){
          seen++;
          if(seen>=count)return i+1;
        }
      }
      return value.length;
    };

    input.value=format(input.value);
    input.addEventListener('focus',()=>{
      if(!input.value.trim())input.value='+7 ';
      if(input.value==='+7')input.value='+7 ';
    });
    input.addEventListener('input',()=>{
      const old=String(input.value||'');
      const pos=input.selectionStart??old.length;
      const digitsBefore=old.slice(0,pos).replace(/\D/g,'').length;
      const hasCountryPrefix=/^\s*\+?7/.test(old);
      const subscriberBefore=Math.max(0,digitsBefore-(hasCountryPrefix?1:0));
      const formatted=format(old);
      input.value=formatted;
      setCaret(input,caretAfterDigits(formatted,subscriberBefore));
    });
    input.addEventListener('blur',()=>{
      const digits=String(input.value||'').replace(/\D/g,'');
      if(digits.length<=1)input.value='';
    });
  }

  function applyEmailMask(input){
    if(input.dataset.smetogramEmailMask==='1')return;
    input.dataset.smetogramEmailMask='1';
    input.setAttribute('inputmode','email');
    input.setAttribute('autocomplete','email');
    input.setAttribute('spellcheck','false');
    input.setAttribute('autocapitalize','none');

    input.addEventListener('input',()=>{
      const value=String(input.value||'')
        .toLowerCase()
        .replace(/\s+/g,'')
        .replace(/[^a-z0-9@._+%-]/g,'');
      if(input.value!==value)input.value=value;
    });
    input.addEventListener('blur',()=>{
      input.value=String(input.value||'').trim().toLowerCase();
    });
  }

  function scan(root=document){
    root.querySelectorAll?.(phoneSelector).forEach(applyPhoneMask);
    root.querySelectorAll?.(emailSelector).forEach(applyEmailMask);
  }

  scan();
  document.addEventListener('DOMContentLoaded',()=>scan());

  new MutationObserver(mutations=>{
    for(const mutation of mutations){
      mutation.addedNodes.forEach(node=>{
        if(node.nodeType===1){
          if(node.matches?.(phoneSelector))applyPhoneMask(node);
          if(node.matches?.(emailSelector))applyEmailMask(node);
          scan(node);
        }
      });
    }
  }).observe(document.body,{childList:true,subtree:true});
})();
