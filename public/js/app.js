const labels={pending:'Pendiente',approved:'Aprobada',rejected:'Rechazada',cancelled:'Cancelada'};
const flash=document.querySelector('#flash');

function xsrfToken(){
  const row=document.cookie.split('; ').find(item=>item.startsWith('XSRF-TOKEN='));
  return row?decodeURIComponent(row.split('=').slice(1).join('=')):'';
}

async function api(path,options={}){
  const response=await fetch(path,{credentials:'same-origin',...options,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest',...(options.body?{'X-XSRF-TOKEN':xsrfToken()}:{}),...(options.headers||{})}});
  if(response.status===401){window.location.assign('/login');throw new Error('Tu sesión terminó. Ingresa de nuevo.');}
  const payload=await response.json().catch(()=>({}));
  if(!response.ok){const message=payload.message||Object.values(payload.errors||{}).flat()[0]||'No fue posible completar la operación.';throw new Error(message);}
  return payload;
}

function showMessage(message,type='success'){
  flash.innerHTML=`<div class="notice ${type}" role="status"></div>`;
  flash.firstElementChild.textContent=message;
  window.setTimeout(()=>{flash.replaceChildren();},5500);
}

function formatDate(value){
  return new Intl.DateTimeFormat('es-CO',{dateStyle:'medium',timeStyle:'short'}).format(new Date(value));
}

async function loadSites(){
  const select=document.querySelector('#site_id');
  try{
    const {data}=await api('/api/v1/sites');
    select.replaceChildren(new Option(data.length?'Selecciona un sitio':'No tienes sitios activos', ''));
    for(const site of data) select.add(new Option(site.name,site.id));
  }catch(error){showMessage(error.message,'error');}
}

async function loadRequests(){
  const list=document.querySelector('#request-list');
  list.innerHTML='<div class="loading-state">Cargando solicitudes…</div>';
  try{
    const result=await api('/api/v1/access-requests');
    const requests=result.data.data||[];
    if(!requests.length){list.innerHTML='<div class="empty-state">Aún no hay solicitudes para mostrar.</div>';return;}
    list.replaceChildren(...requests.map(item=>{
      const row=document.createElement('article');row.className='request-row';
      const info=document.createElement('div');
      const title=document.createElement('p');title.className='request-title';title.textContent=item.site?.name||'Sitio';
      const meta=document.createElement('p');meta.className='request-meta';meta.textContent=`${formatDate(item.requested_from)} · ${item.people?.length||0} persona(s)`;
      const purpose=document.createElement('p');purpose.className='request-purpose';purpose.textContent=item.purpose;
      info.append(title,meta);const badge=document.createElement('span');badge.className=`badge ${item.status}`;badge.textContent=labels[item.status]||item.status;
      row.append(info,badge,purpose);return row;
    }));
  }catch(error){list.innerHTML='';showMessage(error.message,'error');}
}

async function submitRequest(event){
  event.preventDefault();
  const form=event.currentTarget;const button=form.querySelector('button[type="submit"]');button.disabled=true;button.textContent='Enviando…';
  try{
    await api('/sanctum/csrf-cookie');
    const body=new FormData();
    body.append('site_id',form.site_id.value);body.append('purpose',form.purpose.value.trim());
    body.append('requested_from',new Date(form.requested_from.value).toISOString());body.append('requested_until',new Date(form.requested_until.value).toISOString());
    body.append('people[0][full_name]',form.person_name.value.trim());body.append('people[0][document_type]',form.document_type.value.trim());
    body.append('people[0][document_number]',form.document_number.value.trim());body.append('people[0][company]',form.company.value.trim());
    for(const file of form.attachments.files)body.append('attachments[]',file);
    await api('/api/v1/access-requests',{method:'POST',body});
    form.reset();showMessage('Solicitud enviada a aprobación.');await loadRequests();
  }catch(error){showMessage(error.message,'error');}
  finally{button.disabled=false;button.innerHTML='Enviar para aprobación <span aria-hidden="true">→</span>';}
}

document.querySelector('#request-form')?.addEventListener('submit',submitRequest);
document.querySelector('#refresh')?.addEventListener('click',loadRequests);
if(document.querySelector('#request-list')){loadSites();loadRequests();}
