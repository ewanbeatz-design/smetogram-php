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
    toast.innerHTML='<span class="estimate-toast-icon"><i class="bi '+icon+'"></i></span><span class="estimate-toast-text"></span><button type="button" class="estimate-toast-close" aria-label="Закрыть"><i class="bi bi-x-lg"></i></button>';
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
    backdrop.innerHTML='<div class="smetogram-confirm" role="dialog" aria-modal="true" aria-labelledby="smetogramConfirmTitle"><div class="smetogram-confirm-icon"><i class="bi bi-question-lg"></i></div><div class="smetogram-confirm-copy"><strong id="smetogramConfirmTitle">Подтвердите действие</strong><p></p></div><div class="smetogram-confirm-actions"><button type="button" class="outline-button smetogram-confirm-cancel">Отмена</button><button type="button" class="primary-button smetogram-confirm-ok">Продолжить</button></div></div>';
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
        setTimeout(()=>{
          const card=skeleton?.closest('.room-photo-card');
          const grid=card?.querySelector('.room-photo-grid');
          if(grid && skeleton){
            skeleton.classList.remove('room-photo-thumb-skeleton','has-preview');
            skeleton.classList.add('is-complete');
            skeleton.innerHTML='<span class="room-photo-skeleton-icon"><i class="bi bi-check-lg"></i></span>';
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
  $('[data-stage-submit]').innerHTML='<i class="bi bi-plus-lg"></i> Добавить этап'; $('[data-stage-cancel]').hidden=true;
 }
 function render(items){
  const list=$('[data-stage-list]');
  if(!items.length){list.innerHTML='<div class="stage-manager-empty"><i class="bi bi-list-check"></i><strong>Этапов пока нет</strong><span>Добавьте первый этап ниже.</span></div>';return;}
  list.innerHTML=items.map(x=>{
   const amount=Number(x.paymentMilestone||0)>0?Number(x.paymentMilestone).toLocaleString('ru-RU')+' ₽':'Без суммы';
   const dates=[date(x.startsAt),date(x.endsAt)].filter(Boolean);
   const cls='stage-status-'+String(x.status||'planned').replace('_','-');
   return '<div class="stage-manager-item"><div class="stage-manager-item-main"><div class="stage-manager-item-title"><strong>'+esc(x.title)+'</strong><span class="stage-status '+cls+'">'+esc(labels[x.status]||x.status)+'</span></div><div class="stage-manager-item-meta"><span><i class="bi bi-calendar3"></i> '+esc(dates.length?dates.join(' — '):'Даты не указаны')+'</span><span><i class="bi bi-wallet2"></i> '+esc(amount)+'</span></div></div><div class="stage-manager-item-actions"><button type="button" class="stage-edit" data-stage-edit="'+x.id+'"><i class="bi bi-pencil"></i><span>Изменить</span></button><button type="button" class="stage-delete" data-stage-delete="'+x.id+'"><i class="bi bi-trash3"></i></button></div></div>';
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
  $('[data-stage-status]').value=item.status||'planned'; $('[data-stage-payment]').value=Number(item.paymentMilestone||0)||'';
  $('[data-stage-form-title]').textContent='Редактирование этапа'; $('[data-stage-submit]').innerHTML='<i class="bi bi-check2"></i> Сохранить изменения'; $('[data-stage-cancel]').hidden=false;
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
  const editBtn=e.target.closest('[data-stage-edit]');
  if(editBtn){try{const items=await load();const item=items.find(x=>String(x.id)===String(editBtn.dataset.stageEdit));if(item)edit(item);}catch(err){window.smetogramAlert(err.message,'error');}return;}
  const del=e.target.closest('[data-stage-delete]');
  if(del){e.preventDefault();window.smetogramConfirm('Удалить этот этап проекта?',async()=>{
   try{const fd=new FormData();fd.append('csrf',$('[data-stage-form] input[name="csrf"]').value);fd.append('stage_action','delete');fd.append('project_id',projectId);fd.append('stage_id',del.dataset.stageDelete);const r=await fetch('dashboard.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd,credentials:'same-origin'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'Не удалось удалить этап.');const items=await load();updateCard(items);window.smetogramAlert(d.message,'success');}catch(err){window.smetogramAlert(err.message,'error');}
  });return;}
  if(e.target.closest('[data-stage-cancel]'))reset();
 });
 document.addEventListener('submit',async e=>{
  const form=e.target.closest('[data-stage-form]');if(!form)return;e.preventDefault();const b=$('[data-stage-submit]');b.disabled=true;
  const action=form.querySelector('[data-stage-id]').value?'update':'add';form.querySelector('[name="stage_action"]').value=action;
  try{await send(form);}catch(err){window.smetogramAlert(err.message,'error');}finally{b.disabled=false;}
 });
})();
