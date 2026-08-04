window.IIM=window.IIM||{};
window.IIM.toast=(message,type='info',title=null,options={})=>{
 if(!message)return;
 const types={success:{icon:'fa-circle-check',title:'Success'},error:{icon:'fa-circle-xmark',title:'Error'},warning:{icon:'fa-triangle-exclamation',title:'Attention'},info:{icon:'fa-circle-info',title:'Information'}};
 const config=types[type]||types.info;
 const node=document.createElement('div');node.className='IIM-toast-content';
 const icon=document.createElement('span');icon.className='IIM-toast-icon';icon.innerHTML=`<i class="fa-solid ${config.icon}"></i>`;
 const copy=document.createElement('div');copy.className='IIM-toast-copy';
 const heading=document.createElement('strong');heading.textContent=title||config.title;
 const text=document.createElement('span');text.textContent=message;
 copy.append(heading,text);node.append(icon,copy);
 if(typeof Toastify==='undefined'){console.warn(`${title||config.title}: ${message}`);return}
 Toastify({node,duration:options.duration??4000,gravity:options.gravity??'top',position:options.position??'right',close:options.close??true,stopOnFocus:true,className:`IIM-toast IIM-toast-${type}`,offset:{x:14,y:76},...options}).showToast();
};
window.showToast=window.IIM.toast;

document.addEventListener('DOMContentLoaded',()=>{
 const root=document.documentElement,body=document.body,toggle=document.getElementById('themeToggle');
 const syncIcon=()=>{if(toggle)toggle.innerHTML=root.dataset.theme==='dark'?'<i class="fa-regular fa-sun"></i>':'<i class="fa-regular fa-moon"></i>'};
 syncIcon();
 toggle?.addEventListener('click',()=>{root.dataset.theme=root.dataset.theme==='dark'?'light':'dark';localStorage.setItem('IIM-theme',root.dataset.theme);syncIcon();window.dispatchEvent(new CustomEvent('themechange'))});
 document.getElementById('sidebarToggle')?.addEventListener('click',()=>{body.classList.toggle(innerWidth<992?'sidebar-open':'sidebar-collapsed');localStorage.setItem('IIM-sidebar',body.classList.contains('sidebar-collapsed')?'collapsed':'open')});
 document.getElementById('sidebarBackdrop')?.addEventListener('click',()=>body.classList.remove('sidebar-open'));
 if(innerWidth>=992&&localStorage.getItem('IIM-sidebar')==='collapsed')body.classList.add('sidebar-collapsed');
 document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
 document.querySelectorAll('form[data-demo]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();window.IIM.toast('Your changes have been saved successfully.','success')}));
 (window.IIMFlashNotifications||[]).forEach(notification=>window.IIM.toast(notification.message,notification.type,notification.title));

 const confirmModalElement=document.getElementById('confirmationModal');
 if(confirmModalElement){
  const confirmModal=bootstrap.Modal.getOrCreateInstance(confirmModalElement);
  const confirmTitle=document.getElementById('confirmationModalTitle');
  const confirmMessage=document.getElementById('confirmationModalMessage');
  const confirmButton=document.getElementById('confirmationModalConfirm');
  let pendingAction=null;
  const openConfirmation=(element,action)=>{
   pendingAction=action;
   confirmTitle.textContent=element.dataset.confirmTitle||'Confirm action';
   confirmMessage.textContent=element.dataset.confirmMessage||'Are you sure you want to continue?';
   confirmButton.textContent=element.dataset.confirmLabel||'Confirm';
   confirmButton.className=`btn ${element.dataset.confirmClass||'btn-danger'}`;
   confirmModal.show();
  };
  document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{
   event.preventDefault();
   openConfirmation(form,()=>form.submit());
  }));
  document.querySelectorAll('a[data-confirm]').forEach(link=>link.addEventListener('click',event=>{
   event.preventDefault();
   openConfirmation(link,()=>window.location.assign(link.href));
  }));
  confirmButton.addEventListener('click',()=>{
   const action=pendingAction;
   pendingAction=null;
   confirmModal.hide();
   if(action)action();
  });
  confirmModalElement.addEventListener('hidden.bs.modal',()=>{pendingAction=null});
 }

 const recentWrap=document.getElementById('recentlyOpened'),recentLinks=document.getElementById('recentlyOpenedLinks');
 const recentKey='IIM-recent-pages';
 const readRecent=()=>{try{return JSON.parse(localStorage.getItem(recentKey))||[]}catch{return[]}};
 const renderRecent=()=>{
  if(!recentWrap||!recentLinks)return;
  const pages=readRecent().filter(page=>page&&page.url&&page.label).slice(0,3);
  recentLinks.replaceChildren();
  pages.forEach(page=>{
   const link=document.createElement('a');
   link.href=page.url;link.className='nav-link recent-link';link.title=page.label;
   if(location.pathname===new URL(page.url,location.origin).pathname)link.classList.add('active');
   const icon=document.createElement('i');icon.className=`fa-solid ${page.icon||'fa-clock-rotate-left'}`;
   const label=document.createElement('span');label.textContent=page.label;
   link.append(icon,label);recentLinks.append(link);
  });
  recentWrap.hidden=pages.length===0;
 };
 const rememberPage=link=>{
  const label=link.querySelector('span')?.textContent.trim();
  if(!label||label==='Dashboard'||link.classList.contains('recent-link'))return;
  const url=new URL(link.href,location.origin);
  if(url.origin!==location.origin)return;
  const icon=[...(link.querySelector('i')?.classList||[])].find(name=>name.startsWith('fa-')&&!['fa-solid','fa-regular','fa-brands'].includes(name))||'fa-clock-rotate-left';
  const pages=readRecent().filter(page=>new URL(page.url,location.origin).pathname!==url.pathname);
  localStorage.setItem(recentKey,JSON.stringify([{label,url:url.pathname,icon},...pages].slice(0,3)));
 };
 document.querySelectorAll('.sidebar-nav a.nav-link:not(.recent-link)').forEach(link=>link.addEventListener('click',()=>rememberPage(link)));
 const currentLink=document.querySelector('.sidebar-nav a.nav-link.active:not(.recent-link)');
 if(currentLink)rememberPage(currentLink);
 renderRecent();
});
