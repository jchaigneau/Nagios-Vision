let DATA=null, timer=null, activeTab='';
const $=s=>document.querySelector(s);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
function cls(s){return String(s||'UNKNOWN').toLowerCase()}
function badge(s){return `<span class="pill ${cls(s)}">${esc(s)}</span>`}
function dot(s){return `<i class="dot ${cls(s)}"></i>`}
function gauge(value,label){
  if(value===null||value===undefined||Number.isNaN(Number(value))) return '';
  const v=Math.max(0,Math.min(100,Number(value))), offset=314-(314*v/100);
  const g=v>=90?'bad':v>=80?'warn':'good';
  return `<div><div class="gauge ${g}"><svg viewBox="0 0 120 120"><circle class="track" cx="60" cy="60" r="50"></circle><circle class="value" cx="60" cy="60" r="50" style="stroke-dashoffset:${offset}"></circle></svg><div class="center"><div class="num">${v.toFixed(1)}%</div></div></div><div class="gauge-label">${esc(label)}</div></div>`;
}
function storageBars(items){
  if(!items?.length) return '';
  return items.map(x=>{let v=Math.max(0,Math.min(100,Number(x.value)||0));let c=v>=90?'bad':v>=80?'warn':'';return `<div class="storage-row"><div class="storage-meta"><span>${esc(x.name)}</span><b>${v.toFixed(1)}%</b></div><div class="bar"><i class="${c}" style="width:${v}%"></i></div></div>`}).join('');
}
function hostCard(h,group){
  const sv=h.services||[];
  const serviceRows=sv.map(s=>`<div class="svc"><span class="svc-name">${esc(s.service)}</span><span>${dot(s.state)}${badge(s.state)}</span><span class="svc-output" title="${esc(s.output)}">${esc(s.output)}</span></div>`).join('');
  return `<article class="host-card" data-name="${esc(h.name)}" data-group="${esc(group)}" data-state="${esc(h.state)}">
    <div class="host-title"><div class="host-name"><strong>${esc(h.alias)}</strong><small>${esc(h.name)} · ${esc(h.address)}</small></div>${badge(h.state)}</div>
    <div class="host-body"><div class="metrics-row">${gauge(h.metrics?.cpu,'CPU')}${gauge(h.metrics?.ram,'RAM')}<div class="storage"><div class="storage-title">STORAGE / DATASTORES</div>${storageBars(h.metrics?.storage)}</div></div>
    <div class="service-list">${serviceRows||'<div class="no-data">Aucun service</div>'}</div></div></article>`;
}
function renderSummary(s){const h=s.hosts,sv=s.services;$('#summary').innerHTML=`<div class="stat"><b>${h.total}</b><span>HÔTES</span></div><div class="stat ok"><b>${h.up}</b><span>UP</span></div><div class="stat critical"><b>${h.down}</b><span>DOWN</span></div><div class="stat"><b>${sv.total}</b><span>SERVICES</span></div><div class="stat ok"><b>${sv.ok}</b><span>OK</span></div><div class="stat warning"><b>${sv.warning}</b><span>WARNING</span></div><div class="stat critical"><b>${sv.critical}</b><span>CRITICAL</span></div>`}
function renderTabs(){
  const tabs=Object.values(DATA.groups||{});
  if(!activeTab || !DATA.groups[activeTab]) activeTab=tabs[0]?.id||'';
  $('#tabs').innerHTML=tabs.map(g=>`<button class="tab ${g.id===activeTab?'active':''}" data-tab="${esc(g.id)}">${esc(g.icon)} ${esc(g.label)} <span>${g.hosts.length}</span></button>`).join('');
  $('#tabs').querySelectorAll('.tab').forEach(b=>b.addEventListener('click',()=>{activeTab=b.dataset.tab;renderTabs();renderHosts();}));
}
function renderFilters(){
  const g=$('#group'),current=g.value;
  const tabs=Object.values(DATA.groups||{});
  g.innerHTML='<option value="ALL">Tous les onglets</option>'+tabs.filter(x=>x.hosts.length).map(x=>`<option value="${esc(x.id)}">${esc(x.icon)} ${esc(x.label)} (${x.hosts.length})</option>`).join('');
  if([...g.options].some(o=>o.value===current))g.value=current;
}
function renderHosts(){
  const q=$('#search').value.trim().toLowerCase(), group=$('#group').value, state=$('#state').value;
  const selected=group==='ALL' ? activeTab : group;
  let arr=[];
  for(const [gn,data] of Object.entries(DATA.groups||{})){
    if(selected && gn!==selected) continue;
    for(const h of (data.hosts||[])){
      if(q&&!(`${h.name} ${h.alias} ${h.address}`.toLowerCase().includes(q)))continue;
      if(state!=='ALL'&&h.state!==state&&!h.services.some(s=>s.state===state))continue;
      arr.push([gn,h]);
    }
  }
  $('#hosts').innerHTML=arr.length?arr.map(([g,h])=>hostCard(h,g)).join(''):`<div class="empty">Aucun hôte ne correspond aux filtres.</div>`;
}
function renderSwitches(){const rows=DATA.switch_checks||[];$('#switches').innerHTML=rows.length?rows.map(x=>`<tr><td>${esc(x.alias)}<br><small>${esc(x.host)}</small></td><td>${esc(x.service)}</td><td>${dot(x.state)}${badge(x.state)}</td><td title="${esc(x.output)}">${esc(x.output)}</td></tr>`).join(''):`<tr><td colspan="4">Aucun switch détecté.</td></tr>`}
function render(){renderSummary(DATA.summary);renderTabs();renderFilters();renderHosts();$('#updated').textContent=new Date(DATA.generated).toLocaleTimeString('fr-FR')}
async function load(){try{const r=await fetch('api.php?t='+Date.now(),{cache:'no-store'});const d=await r.json();if(!r.ok||d.error)throw new Error(d.error||'API indisponible');DATA=d;render()}catch(e){$('#hosts').innerHTML=`<div class="error">API Nagios inaccessible : ${esc(e.message)}</div>`}}
function restartTimer(){clearInterval(timer);timer=setInterval(load,Number($('#refresh').value)*1000)}
$('#search').addEventListener('input',renderHosts);$('#group').addEventListener('change',renderHosts);$('#state').addEventListener('change',renderHosts);$('#reload').addEventListener('click',load);$('#refresh').addEventListener('change',restartTimer);load();restartTimer();
