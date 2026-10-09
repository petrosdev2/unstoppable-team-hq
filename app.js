"use strict";
const ICONS={
 dashboard:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
 kiosk:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
 attendance:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8 15l2 2 4-4"/></svg>',
 members:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M16.5 14.5c2.6.2 4.4 2 5 4.5"/></svg>',
 finance:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="6" width="19" height="13" rx="2"/><circle cx="12" cy="12.5" r="2.5"/><path d="M6 9.5v.01M18 15.5v.01"/></svg>',
 leaders:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="8" r="3.5"/><path d="M3.5 20c.8-3.5 3.4-5.5 6.5-5.5 1.6 0 3 .5 4.1 1.4"/><path d="M18 14v6M15 17h6"/></svg>',
 prospects:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5 1.3 0 2.5.3 3.5 1"/><path d="M17 13v8M13 17h8"/></svg>',
 trainings:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l10-5 10 5-10 5z"/><path d="M6 10.5V16c2 2 10 2 12 0v-5.5"/></svg>',
 performance:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 20h18"/><rect x="5" y="11" width="3" height="7" rx="1"/><rect x="10.5" y="7" width="3" height="11" rx="1"/><rect x="16" y="4" width="3" height="14" rx="1"/></svg>',
 settings:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>'
};
/* ===== Unstoppable Team HQ — front end (talks to api.php) ===== */
const STAGES=["New Member","Pro","Tools Owner"];
const TX=[{k:"earning",l:"Earning",d:1},{k:"other_in",l:"Other income",d:1},{k:"charge",l:"Team charge",d:-1},{k:"withdrawal",l:"Withdrawal",d:-1},{k:"neolife",l:"NeoLife purchase",d:-1},{k:"upkeep",l:"Upkeep",d:-1},{k:"other_out",l:"Other expense",d:-1}];
const CUR=["NGN","USD","EUR"];

const S={me:null,loaded:false,offices:[],ranks:[],members:[],byId:{},todayStr:null,tz:"Africa/Lagos",today:{},
 view:"dashboard",office:"all",q:"",kq:"",memFilter:{level:"",status:"active"},
 att:{mode:"day",date:null,month:null,cache:{}},recent:null,recentAt:0,fin:{month:null,entries:null},users:null,error:null};

/* ---------- helpers ---------- */
const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const pad=n=>String(n).padStart(2,"0");
const ymd=d=>`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
const hm=s=>s?String(s).slice(11,16):"";
const dayOf=s=>new Date(s+"T12:00:00");
const isWeekend=s=>{const w=dayOf(s).getDay();return w===0||w===6};
const addDays=(s,n)=>{const d=dayOf(s);d.setDate(d.getDate()+n);return ymd(d)};
const fmtDate=s=>s?dayOf(s).toLocaleDateString(undefined,{day:"numeric",month:"short",year:"numeric"}):"";
const initials=n=>String(n||"?").trim().split(/\s+/).slice(0,2).map(w=>w[0]||"").join("").toUpperCase();
const age=dob=>{if(!dob)return"";const b=dayOf(dob),n=dayOf(S.todayStr);let a=n.getFullYear()-b.getFullYear();if(n.getMonth()<b.getMonth()||(n.getMonth()===b.getMonth()&&n.getDate()<b.getDate()))a--;return a};
const money=(n,c)=>{try{return new Intl.NumberFormat(undefined,{style:"currency",currency:c,maximumFractionDigits:2}).format(n)}catch(e){return c+" "+Number(n).toFixed(2)}};
const isAdmin=()=>S.me&&S.me.role==="admin";
function toast(msg){const t=document.getElementById("toast");t.innerHTML=`<div class="toast" role="status">${esc(msg)}</div>`;clearTimeout(toast.t);toast.t=setTimeout(()=>t.innerHTML="",3000)}
function avatar(m,cls=""){return m&&m.photo?`<img class="av ${cls}" src="${esc(m.photo)}" alt="">`:`<span class="av ${cls}" aria-hidden="true">${esc(initials(m&&m.fullName))}</span>`}
const ranks=()=>S.ranks.length?S.ranks:[];
function rankGroup(r){const R=ranks(),i=R.indexOf(r);if(i<0||!r)return"Not ranked";const iS=R.indexOf("Sapphire Director"),i3=R.indexOf("3 Ruby Director"),i4=R.indexOf("4 Ruby Director"),iD=R.indexOf("Director");
 if(i4>=0&&i>=i4)return"President Team";if(iS>=0&&i>=iS&&(i3<0||i<=i3))return"World Team";if(iD>=0&&i>=iD)return"Director";return"Manager levels"}
function stageTag(s){return s==="Tools Owner"?`<span class="tag gold">${esc(s)}</span>`:s==="Pro"?`<span class="tag green">${esc(s)}</span>`:`<span class="tag">${esc(s||"New Member")}</span>`}
function rankTag(r){if(!r)return'<span class="muted small">—</span>';const g=rankGroup(r);return`<span class="tag ${g==="President Team"||g==="World Team"?"gold":g==="Director"?"blue":""}">${esc(r)}</span>`}
const officeById=id=>S.offices.find(o=>o.id===+id);
function scopeIds(){if(S.office!=="all"&&officeById(S.office))return[+S.office];return S.offices.map(o=>o.id)}
function singleOffice(){if(S.office!=="all"&&officeById(S.office))return +S.office;return S.offices[0]?S.offices[0].id:null}
const visibleMembers=()=>{const ids=scopeIds();return S.members.filter(m=>ids.includes(m.officeId))};
const sponsorName=m=>m.sponsorId&&S.byId[m.sponsorId]?S.byId[m.sponsorId].fullName:(m.sponsorName||"");
const sponsorPhone=m=>m.sponsorId&&S.byId[m.sponsorId]?S.byId[m.sponsorId].phone:(m.sponsorPhone||"");
const uplineName=m=>m.uplineId&&S.byId[m.uplineId]?S.byId[m.uplineId].fullName:(m.uplineName||"");

/* ---------- API ---------- */
async function api(action,data={}){
 let r,j;
 try{r=await fetch("api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-Requested-With":"uthq"},body:JSON.stringify(Object.assign({action},data))})}
 catch(e){throw new Error("No connection. Check your internet and try again.")}
 try{j=await r.json()}catch(e){throw new Error("The server didn't answer properly. Check the setup.")}
 if(r.status===401&&action!=="login"&&action!=="password_change"){S.me=null;closeModal();render()}
 if(!r.ok||j.error)throw new Error(j.error||"Something went wrong.");
 return j;
}
/* attendance rows -> {date:{officeId:{session, records:{memberId:rec}}}} */
function toDays(res){const out={};const get=(d,o)=>{out[d]=out[d]||{};out[d][o]=out[d][o]||{session:false,records:{}};return out[d][o]};
 res.sessions.forEach(s=>get(s.date,s.officeId).session=true);res.rows.forEach(r=>get(r.date,r.officeId).records[r.memberId]=r);return out}
const dayDoc=(days,date,oid)=>(days[date]&&days[date][oid])||{session:false,records:{}};
function isSessionDay(date,doc){return !isWeekend(date)||!!(doc&&doc.session)}
function recStatus(rec,office){if(!rec)return"absent";if(rec.excused)return"excused";if(!rec.in)return"absent";return(office&&office.lateAfter&&hm(rec.in)>office.lateAfter)?"late":"present"}
function hours(rec){if(!rec||!rec.in||!rec.out)return null;const h=(new Date(rec.out.replace(" ","T"))-new Date(rec.in.replace(" ","T")))/3.6e6;return h>0?h:null}
const statusTag={present:'<span class="tag green">Present</span>',late:'<span class="tag gold">Late</span>',absent:'<span class="tag red">Absent</span>',excused:'<span class="tag blue">Excused</span>'};

/* ---------- load ---------- */
async function boot(){const rt=new URLSearchParams(location.search).get("reset");if(rt){S.resetToken=rt;S.loaded=true;render();return}try{const j=await api("me");S.me=j.user;if(S.me)await refresh();else{S.loaded=true;render()}}catch(e){S.error=e.message;render()}}
async function refresh(quiet){const j=await api("bootstrap");S.me=j.user;S.offices=j.offices;S.ranks=j.ranks;S.members=j.members;S.byId={};S.members.forEach(m=>S.byId[m.id]=m);
 if(S.todayStr&&S.todayStr!==j.today){S.recent=null}S.todayStr=j.today;S.tz=j.tz;S.settings=j.settings;S.lastFollowups=j.lastFollowups||{};S.today=toDays(j.att)[j.today]||{};
 if(!S.att.date)S.att.date=j.today;if(!S.att.month)S.att.month=j.today.slice(0,7);if(!S.fin.month)S.fin.month=j.today.slice(0,7);
 if(!isAdmin())S.office=S.offices[0]?S.offices[0].id:"all";else if(S.office!=="all"&&!officeById(S.office))S.office="all";
 S.loaded=true;S.error=null;if(!quiet)render()}
function invalidate(){S.att.cache={};S.recent=null}
setInterval(()=>{if(!S.me||document.hidden||document.getElementById("modal").innerHTML)return;
 const ae=document.activeElement,typing=ae&&/^(INPUT|SELECT|TEXTAREA)$/.test(ae.tagName)&&ae.id!=="kq";
 refresh(true).then(()=>{if(!typing&&["dashboard","kiosk","attendance"].includes(S.view))render()}).catch(()=>{})},30000);
setInterval(()=>{const c=document.getElementById("clock");if(c)c.textContent=new Date().toLocaleTimeString("en-GB",{timeZone:S.tz,hour:"2-digit",minute:"2-digit",second:"2-digit"})},1000);

/* ---------- render ---------- */
function render(){const app=document.getElementById("app");const ae=document.activeElement,fid=ae&&ae.id,ss=ae&&ae.selectionStart,se=ae&&ae.selectionEnd;
 app.innerHTML=shell();if(fid){const el=document.getElementById(fid);if(el&&el!==document.activeElement){el.focus();try{if(ss!=null)el.setSelectionRange(ss,se)}catch(e){}}}}
function shell(){
 if(S.error&&!S.loaded)return`<div class="main"><div class="card" style="max-width:520px;margin:12vh auto"><h2>Can't reach the server</h2><p class="muted">${esc(S.error)}</p><button class="btn pri" data-act="retry">Try again</button></div></div>`;
 if(!S.loaded)return`<div class="empty" style="padding-top:30vh">Loading…</div>`;
 if(S.resetToken)return resetView();
 if(!S.me)return loginView();
 if(!S.offices.length&&!isAdmin())return`<div class="main"><div class="card" style="max-width:520px;margin:12vh auto"><h2>No office assigned</h2><p class="muted">Your account isn't linked to an office yet. Ask the team admin to assign you.</p><button class="btn" data-act="logout">Log out</button></div></div>`;
 const nav=[["dashboard","Dashboard"],["kiosk","Check-in"],["attendance","Attendance"],["members","Members"],["prospects","Prospects"],["trainings","Trainings"],["performance","Business"]];
 if(isAdmin())nav.push(["leaders","Team"],["finance","Finance"],["settings","Settings"]);
 const links=nav.map(([k,l])=>`<a data-act="nav" data-v="${k}" class="${S.view===k?"on":""}">${ICONS[k]}<span>${l}</span></a>`).join("");
 const views={dashboard:dashView,kiosk:kioskView,attendance:attView,members:membersView,prospects:prospectsView,trainings:trainingsView,performance:perfView,leaders:teamView,finance:finView,settings:settingsView};
 const adminOnly=["leaders","finance","settings"];
 const v=(views[S.view]&&(!adminOnly.includes(S.view)||isAdmin()))?views[S.view]:dashView;
 return`<div class="layout"><aside class="side"><div class="brand">Unstoppable Team<small>${isAdmin()?"Admin · all offices":esc((S.offices[0]||{}).name||"Team leader")}</small></div><nav class="nav">${links}</nav>
 <div class="who"><b style="color:var(--ink)">${esc(S.me.name)}</b><br><a data-act="account" style="cursor:pointer;color:var(--green);font-weight:600">Change password</a> · <a data-act="logout" style="cursor:pointer;color:var(--green);font-weight:600">Log out</a></div></aside>
 <main class="main">${!S.offices.length?`<div class="card"><h2>Add your first office</h2><p class="muted">Go to Settings and add an office to get started.</p></div>`:v()}</main><nav class="bottom">${links}<a data-act="account">${ICONS.settings.replace("<svg","<svg style='opacity:.6'")}<span>Account</span></a></nav></div>`;
}
function loginView(){return`<div class="main"><div class="card" style="max-width:420px;margin:10vh auto"><div class="brand" style="padding:0 0 6px">Unstoppable Team<small>Team HQ</small></div><h1 style="margin-top:10px">Log in</h1>
 <form data-form="login" style="margin-top:16px"><div class="grid"><label class="f">Email<input id="lgEmail" type="email" name="email" required autocomplete="username"></label><label class="f">Password<input type="password" name="password" required autocomplete="current-password"></label></div>
 <button class="btn pri" style="width:100%;margin-top:18px;padding:12px">Log in</button></form><p class="small" style="margin-top:14px"><a data-act="forgot" style="cursor:pointer;color:var(--green);font-weight:600">Forgot your password?</a></p></div></div>`}
function officePicker(allowAll=true){if(S.offices.length<=1)return S.offices[0]?`<span class="tag green">${esc(S.offices[0].name)}</span>`:"";
 return`<select id="officeSel" data-act="office" aria-label="Office">${allowAll?`<option value="all" ${S.office==="all"?"selected":""}>All offices</option>`:""}${S.offices.map(x=>`<option value="${x.id}" ${+S.office===x.id?"selected":""}>${esc(x.name)}</option>`).join("")}</select>`}
function officePickerSingle(oid,id){return S.offices.length>1?`<select id="${id}" data-act="office" aria-label="Office">${S.offices.map(x=>`<option value="${x.id}" ${x.id===oid?"selected":""}>${esc(x.name)}</option>`).join("")}</select>`:`<span class="tag green">${esc((officeById(oid)||{}).name||"")}</span>`}

/* ---------- dashboard ---------- */
function dashView(){
 const ids=scopeIds(),os=S.offices.filter(o=>ids.includes(o.id)),ms=visibleMembers().filter(m=>m.status!=="inactive");
 if(!S.recent||Date.now()-S.recentAt>120000)loadRecent();
 const cards=os.map(o=>{const doc=S.today[o.id]||{records:{}},recs=doc.records,om=ms.filter(m=>m.officeId===o.id);
  let inNow=0,came=0,late=0;om.forEach(m=>{const r=recs[m.id];const st=recStatus(r,o);if(st==="present"||st==="late"){came++;if(!r.out)inNow++}if(st==="late")late++});
  const session=isSessionDay(S.todayStr,doc);
  return`<div class="card"><div class="row" style="justify-content:space-between"><h2>${esc(o.name)}</h2><span class="tag">${om.length} members</span></div>
  ${session?`<div class="grid" style="grid-template-columns:repeat(3,1fr);margin-top:16px"><div><div class="stat">${inNow}</div><div class="statlbl">In now</div></div><div><div class="stat">${came}</div><div class="statlbl">Came today</div></div><div><div class="stat">${late}</div><div class="statlbl">Late</div></div></div>
  <div class="bar" style="margin-top:14px"><i style="width:${om.length?Math.round(came/om.length*100):0}%"></i></div><div class="small muted" style="margin-top:6px">${om.length-came} not signed in yet</div>`:`<p class="muted">Weekend — no session opened today.</p>`}</div>`}).join("");
 const byStage=STAGES.map(s=>[s,ms.filter(m=>(m.stage||"New Member")===s).length]);
 const byRank=[["Not yet ranked",ms.filter(m=>!m.rank).length],...ranks().map(r=>[r,ms.filter(m=>m.rank===r).length])];
 const maxS=Math.max(1,...byStage.map(x=>x[1]),...byRank.map(x=>x[1]));
 const lvlRow=(s,n,col,val)=>`<li><a data-act="lvl" data-v="${esc(val)}" style="flex:0 0 150px;cursor:pointer">${esc(s)}</a><span class="bar" style="flex:1;align-self:center"><i style="width:${n/maxS*100}%;background:${col}"></i></span><b style="width:32px;text-align:right">${n}</b></li>`;
 const month=S.todayStr.slice(0,7);
 const joiners=ms.filter(m=>(m.joinedDate||"").startsWith(month));
 const promos=[];ms.forEach(m=>{(m.rankHistory||[]).forEach(h=>{if((h.date||"").startsWith(month)&&h.from!=null)promos.push({m,t:h.rank})});(m.stageHistory||[]).forEach(h=>{if((h.date||"").startsWith(month)&&h.from!=null)promos.push({m,t:h.stage})})});
 const bdays=upcomingBirthdays(ms),alerts=absenceAlerts(ms);
 return`<div class="top"><div><h1>Dashboard</h1><p>${dayOf(S.todayStr).toLocaleDateString(undefined,{weekday:"long",day:"numeric",month:"long",year:"numeric"})}</p></div><div class="row">${officePicker()}</div></div>
 <div class="grid g2">${cards}</div>
 <div class="grid g2" style="margin-top:16px">
  <div class="card" style="grid-column:1/-1"><h2>All stages &amp; ranks</h2><p class="small muted">Tap any line to see those members.</p><div class="grid g2" style="margin-top:6px;gap:0 28px"><ul class="list"><li><b class="small muted">Team stage</b></li>${byStage.map(([s,n])=>lvlRow(s,n,s==="Tools Owner"?"var(--gold)":"var(--green)","s:"+s)).join("")}</ul><ul class="list"><li><b class="small muted">NeoLife rank</b></li>${byRank.map(([s,n],i)=>lvlRow(s,n,i===0?"var(--muted)":"var(--blue)","r:"+(i===0?"":s))).join("")}</ul></div></div>
  <div class="card"><h2>Needs a follow-up</h2><p class="small muted">Absent the last 3 working days</p>${alerts===null?'<p class="muted">Checking recent attendance…</p>':alerts.length?`<ul class="list">${alerts.map(m=>{const f=S.lastFollowups[m.id];return`<li><a data-act="profile" data-id="${m.id}" style="cursor:pointer" class="name-cell">${avatar(m)}<span>${esc(m.fullName)}<span class="small muted" style="display:block">${f?`Followed up ${fmtDate(f.date)} by ${esc(f.by)}`:"Not followed up yet"}</span></span></a><button class="btn sm" data-act="logFu" data-id="${m.id}">Log follow-up</button></li>`}).join("")}</ul>`:'<p class="muted">Everyone has shown up recently.</p>'}</div>
  <div class="card"><h2>This month</h2><ul class="list" style="margin-top:6px">
   ${joiners.map(m=>`<li><span>${esc(m.fullName)}</span><span class="tag green">Joined ${fmtDate(m.joinedDate)}</span></li>`).join("")}
   ${promos.map(p=>`<li><span>${esc(p.m.fullName)}</span><span class="tag gold">${esc(p.t)}</span></li>`).join("")}
   ${bdays.map(m=>`<li><span>${esc(m.fullName)}</span><span class="tag blue">Birthday ${esc(m._bd)}</span></li>`).join("")}
   ${!joiners.length&&!promos.length&&!bdays.length?'<li class="muted">No new joiners, promotions or birthdays yet.</li>':""}</ul></div>
  ${(()=>{const P=loadProspects();if(!P)return"";const ps=P.filter(p=>ids.includes(p.officeId)),nm=ps.filter(p=>(p.createdAt||"").startsWith(month)).length,open=ps.filter(p=>!["joined","not_interested"].includes(p.status)).length,j=ps.filter(p=>p.status==="joined").length;
   return`<div class="card"><div class="row" style="justify-content:space-between"><h2>Prospects</h2><a data-act="nav" data-v="prospects" style="cursor:pointer;color:var(--green);font-weight:600" class="small">Open</a></div><div class="grid" style="grid-template-columns:repeat(3,1fr);margin-top:14px"><div><div class="stat">${nm}</div><div class="statlbl">New this month</div></div><div><div class="stat">${open}</div><div class="statlbl">Still open</div></div><div><div class="stat">${j}</div><div class="statlbl">Joined</div></div></div></div>`})()}</div>`;
}
function upcomingBirthdays(ms){const out=[];for(let i=0;i<7;i++){const d=addDays(S.todayStr,i),md=d.slice(5);ms.forEach(m=>{if(m.dob&&m.dob.slice(5)===md)out.push(Object.assign({},m,{_bd:i===0?"today":dayOf(d).toLocaleDateString(undefined,{weekday:"short"})}))})}return out}
async function loadRecent(){if(loadRecent.busy)return;loadRecent.busy=true;try{const r=await api("att_range",{from:addDays(S.todayStr,-14),to:addDays(S.todayStr,-1)});S.recent=toDays(r);S.recentAt=Date.now();if(S.view==="dashboard")render()}catch(e){}finally{loadRecent.busy=false}}
function absenceAlerts(ms){if(!S.recent)return null;const out=[];
 ms.forEach(m=>{const days=[];for(let i=1;i<=14&&days.length<3;i++){const s=addDays(S.todayStr,-i);if(isSessionDay(s,dayDoc(S.recent,s,m.officeId)))days.push(s)}
  if(days.length<3||(m.joinedDate&&m.joinedDate>days[2]))return;
  if(days.every(s=>{const r=dayDoc(S.recent,s,m.officeId).records[m.id];return !r||(!r.in&&!r.excused)}))out.push(m)});return out}

/* ---------- kiosk ---------- */
function kioskView(){
 const oid=singleOffice(),o=officeById(oid);if(!o)return'<div class="card empty">No office available.</div>';
 const doc=S.today[oid]||{records:{}},recs=doc.records,session=isSessionDay(S.todayStr,doc);
 const ms=S.members.filter(m=>m.officeId===oid&&m.status!=="inactive");
 const q=S.kq.trim().toLowerCase();const shown=q?ms.filter(m=>(m.fullName+" "+(m.code||"")).toLowerCase().includes(q)):ms;
 let inNow=0,came=0;ms.forEach(m=>{const r=recs[m.id];if(r&&r.in){came++;if(!r.out)inNow++}});
 const head=`<div class="kiosk-head"><div><div class="clock" id="clock">--:--:--</div><div class="date">${dayOf(S.todayStr).toLocaleDateString(undefined,{weekday:"long",day:"numeric",month:"long"})}</div></div><div><div class="office">${esc(o.name)}</div><div class="counts">${inNow} in now · ${came} came today · ${ms.length} members</div></div></div>`;
 const top=`<div class="top"><div><h1>Check-in</h1><p>Members tap their name, enter their PIN, and sign in or out. Times come from the server clock.</p></div>${officePickerSingle(oid,"kOffice")}</div>`;
 if(!session)return`${top}${head}<div class="card empty"><h2>No session today</h2><p>It's the weekend. Open a session if the office is meeting today.</p><button class="btn pri" data-act="openSession" data-o="${oid}" data-d="${S.todayStr}">Open weekend session</button></div>`;
 return`${top}${head}<input id="kq" class="bigsearch" data-act="kq" placeholder="Type your name or member code" value="${esc(S.kq)}" autocomplete="off">
 <div class="tiles">${shown.map(m=>{const r=recs[m.id];const cls=r&&r.in&&!r.out?"in":r&&r.out?"out":"";
  const sub=r&&r.out?`Out at ${hm(r.out)}`:r&&r.in?`In since ${hm(r.in)}`:r&&r.excused?"Excused":"Not in yet";
  return`<button class="tile ${cls}" data-act="kioskTap" data-id="${m.id}">${avatar(m)}<div><b>${esc(m.fullName)}</b><span>${esc(sub)}</span></div></button>`}).join("")||'<div class="empty">No matching members.</div>'}</div>`;
}
function kioskModal(m){const r=(S.today[m.officeId]||{records:{}}).records[m.id];const action=!r||!r.in?"in":!r.out?"out":"done";
 return`<div class="scrim" data-act="closeBg"><div class="modal narrow" role="dialog" aria-modal="true"><div class="mhead"><div class="name-cell">${avatar(m)}<div><h2>${esc(m.fullName)}</h2><div class="small muted">${esc(m.code||"")}</div></div></div><button class="x" data-act="close" aria-label="Close">×</button></div>
 ${action==="done"?`<p>Signed in at <b>${hm(r.in)}</b> and out at <b>${hm(r.out)}</b>. See you next time.</p><button class="btn" data-act="close">Close</button>`:
 `<form data-form="kiosk" data-id="${m.id}" data-a="${action}">${S.settings.kioskPhoto?`<div style="text-align:center;margin-bottom:12px"><video id="cam" autoplay playsinline muted style="width:100%;max-width:280px;border-radius:12px;background:#000;aspect-ratio:4/3;object-fit:cover"></video><div id="camMsg" class="small muted">Starting camera…</div></div>`:""}${m.hasPin?`<label class="f">Enter your PIN<input id="pinIn" class="pin" name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" required></label>`:`<p class="small muted">No PIN set for this member. A leader can add one on their profile.</p>`}
 ${action==="out"?`<p class="muted">In since ${hm(r.in)}</p>`:""}<button class="btn pri" style="width:100%;margin-top:14px;padding:14px;font-size:1.05rem">${action==="in"?"Sign in now":"Sign out now"}</button></form>`}</div></div>`}

/* ---------- attendance ---------- */
function attView(){
 const oid=singleOffice(),o=officeById(oid);if(!o)return'<div class="card empty">No office available.</div>';
 const seg=`<div class="seg"><button data-act="attMode" data-m="day" class="${S.att.mode==="day"?"on":""}">Day</button><button data-act="attMode" data-m="month" class="${S.att.mode==="month"?"on":""}">Month</button></div>`;
 const ctl=S.att.mode==="day"?`<input type="date" id="attDate" data-act="attDate" value="${esc(S.att.date)}" max="${S.todayStr}">`:`<input type="month" id="attMonth" data-act="attMonth" value="${esc(S.att.month)}">`;
 return`<div class="top"><div><h1>Attendance</h1><p>Sign-in and sign-out times for ${esc(o.name)}.</p></div><div class="row">${officePickerSingle(oid,"aOffice")}${seg}${ctl}${S.att.mode==="month"?'<button class="btn" data-act="exportAtt">Export CSV</button>':""}</div></div>${S.att.mode==="day"?attDay(o):attMonth(o)}`;
}
function cached(key,from,to,oid){if(S.att.cache[key])return S.att.cache[key];S.att.cache[key]="loading";api("att_range",{officeId:oid,from,to}).then(r=>{S.att.cache[key]=toDays(r);render()}).catch(e=>{delete S.att.cache[key];toast(e.message)});return"loading"}
function attDay(o){
 let doc;if(S.att.date===S.todayStr)doc=S.today[o.id]||{session:false,records:{}};else{const c=cached(`${o.id}:${S.att.date}`,S.att.date,S.att.date,o.id);if(c==="loading")return'<div class="card empty">Loading…</div>';doc=dayDoc(c,S.att.date,o.id)}
 if(!isSessionDay(S.att.date,doc))return`<div class="card empty"><p>No session on this weekend day.</p><button class="btn" data-act="openSession" data-o="${o.id}" data-d="${esc(S.att.date)}">Open a session for this day</button></div>`;
 const recs=doc.records;
 const ms=S.members.filter(m=>m.officeId===o.id&&(m.status!=="inactive"||recs[m.id])&&(!m.joinedDate||m.joinedDate<=S.att.date));
 const c={present:0,late:0,absent:0,excused:0};ms.forEach(m=>c[recStatus(recs[m.id],o)]++);
 return`<div class="row" style="margin-bottom:12px">${Object.entries(c).map(([k,v])=>`${statusTag[k]} <b>${v}</b>`).join("&nbsp;&nbsp;")}<span class="muted small">Late after ${esc(o.lateAfter)}</span></div>
 <div class="tbl-wrap"><table><thead><tr><th>Member</th><th>Status</th><th>Signed in</th><th>Signed out</th><th>Hours</th><th>Note</th><th>Last edited by</th><th></th></tr></thead><tbody>
 ${ms.map(m=>{const r=recs[m.id],st=recStatus(r,o),h=hours(r),noOut=r&&r.in&&!r.out&&S.att.date<S.todayStr;
  return`<tr><td><div class="name-cell">${avatar(m)}<div>${esc(m.fullName)}<div class="small muted">${esc(m.code||"")}</div></div></div></td><td>${statusTag[st]}${noOut?' <span class="tag red">No sign-out</span>':""}</td><td>${hm(r&&r.in)||"—"}</td><td>${hm(r&&r.out)||"—"}</td><td>${h?h.toFixed(1):"—"}</td><td class="small muted">${esc((r&&r.note)||"")}</td><td class="small muted">${r&&r.byName?`${esc(r.byName)} · ${esc(hm(r.updatedAt))}`:"—"}</td><td><div class="row" style="flex-wrap:nowrap">${r&&(r.hasPhotoIn||r.hasPhotoOut)?`<button class="btn sm" data-act="photos" data-id="${m.id}" title="See check-in photos">📷</button>`:""}<button class="btn sm" data-act="editRec" data-id="${m.id}">Edit</button></div></td></tr>`}).join("")||'<tr><td colspan="8" class="empty">No members in this office yet.</td></tr>'}
 </tbody></table></div>`;
}
function monthSummary(o,days,month){
 const [y,mo]=month.split("-").map(Number),last=new Date(y,mo,0).getDate(),sessions=[];
 for(let i=1;i<=last;i++){const s=`${month}-${pad(i)}`;if(s>S.todayStr)break;if(isSessionDay(s,dayDoc(days,s,o.id)))sessions.push(s)}
 const rows=S.members.filter(m=>m.officeId===o.id).map(m=>{let p=0,l=0,e=0,a=0,hs=0,hn=0,due=0;sessions.forEach(s=>{if(m.joinedDate&&m.joinedDate>s)return;due++;const r=dayDoc(days,s,o.id).records[m.id];const st=recStatus(r,o);
  if(st==="present")p++;else if(st==="late"){p++;l++}else if(st==="excused")e++;else a++;const h=hours(r);if(h){hs+=h;hn++}});
  return{m,p,l,e,a,due,avg:hn?hs/hn:null,pct:due-e>0?Math.round(p/(due-e)*100):null}}).filter(r=>r.due>0||r.m.status!=="inactive");
 return{sessions,rows}}
function monthDays(oid,month){const last=new Date(+month.slice(0,4),+month.slice(5),0).getDate();return cached(`${oid}:${month}`,`${month}-01`,`${month}-${pad(last)}`,oid)}
function attMonth(o){const days=monthDays(o.id,S.att.month);if(days==="loading")return'<div class="card empty">Loading…</div>';
 const {sessions,rows}=monthSummary(o,days,S.att.month);rows.sort((a,b)=>(b.pct??-1)-(a.pct??-1));
 return`<p class="muted">${sessions.length} session days so far this month. Percentage counts present and late days, and leaves out excused days.</p>
 <div class="tbl-wrap"><table><thead><tr><th>Member</th><th>Attendance</th><th>Present</th><th>Late</th><th>Absent</th><th>Excused</th><th>Avg hours</th></tr></thead><tbody>
 ${rows.map(r=>`<tr class="click" data-act="profile" data-id="${r.m.id}"><td><div class="name-cell">${avatar(r.m)}${esc(r.m.fullName)}</div></td><td><div class="row" style="flex-wrap:nowrap"><span class="bar" style="width:80px"><i style="width:${r.pct||0}%;background:${(r.pct??0)<60?"var(--red)":(r.pct??0)<80?"var(--gold)":"var(--green)"}"></i></span><b>${r.pct==null?"—":r.pct+"%"}</b></div></td><td>${r.p}</td><td>${r.l}</td><td>${r.a}</td><td>${r.e}</td><td>${r.avg?r.avg.toFixed(1):"—"}</td></tr>`).join("")||'<tr><td colspan="7" class="empty">No members yet.</td></tr>'}
 </tbody></table></div>`}
function recModal(m,date){const doc=date===S.todayStr?(S.today[m.officeId]||{records:{}}):dayDoc(S.att.cache[`${m.officeId}:${date}`]||{},date,m.officeId);const r=doc.records[m.id]||{};
 return`<div class="scrim" data-act="closeBg"><div class="modal narrow" role="dialog" aria-modal="true"><div class="mhead"><div><h2>${esc(m.fullName)}</h2><div class="small muted">${fmtDate(date)}</div></div><button class="x" data-act="close" aria-label="Close">×</button></div>
 <form data-form="rec" data-id="${m.id}" data-d="${esc(date)}"><div class="grid"><label class="f">Signed in<input type="time" name="in" value="${hm(r.in)}"></label><label class="f">Signed out<input type="time" name="out" value="${hm(r.out)}"></label>
 <label class="chk"><input type="checkbox" name="excused" ${r.excused?"checked":""}> Excused absence</label><label class="f">Note<input name="note" value="${esc(r.note||"")}" placeholder="e.g. sick, exam, travelling"></label></div>
 <p class="small muted">Edits are saved with your name.</p><div class="row"><button class="btn pri">Save</button><button type="button" class="btn danger" data-act="clearRec" data-id="${m.id}" data-d="${esc(date)}">Clear record</button></div></form></div></div>`}

/* ---------- members ---------- */
function membersView(){const f=S.memFilter,q=S.q.trim().toLowerCase();
 let ms=visibleMembers().filter(m=>(!f.level||(f.level.startsWith("s:")?(m.stage||"New Member")===f.level.slice(2):f.level==="r:"?!m.rank:m.rank===f.level.slice(2)))&&(!f.status||(m.status||"active")===f.status));
 if(q)ms=ms.filter(m=>[m.fullName,m.code,m.phone,m.email].join(" ").toLowerCase().includes(q));
 return`<div class="top"><div><h1>Members</h1><p>${ms.length} shown</p></div><div class="row">${officePicker()}<button class="btn" data-act="exportMembers">Export CSV</button><button class="btn pri" data-act="newMember">Add member</button></div></div>
 <div class="row" style="margin-bottom:12px"><input id="mq" data-act="mq" placeholder="Search name, code, phone" value="${esc(S.q)}" style="flex:1;min-width:200px">
 <select data-act="mf" data-k="level" aria-label="Stage or rank"><option value="">All stages &amp; ranks</option><optgroup label="Team stage">${STAGES.map(s=>`<option value="s:${esc(s)}" ${f.level==="s:"+s?"selected":""}>${esc(s)}</option>`).join("")}</optgroup><optgroup label="NeoLife rank"><option value="r:" ${f.level==="r:"?"selected":""}>Not yet ranked</option>${ranks().map(s=>`<option value="r:${esc(s)}" ${f.level==="r:"+s?"selected":""}>${esc(s)}</option>`).join("")}</optgroup></select>
 <select data-act="mf" data-k="status" aria-label="Status"><option value="active" ${f.status==="active"?"selected":""}>Active</option><option value="inactive" ${f.status==="inactive"?"selected":""}>Inactive</option><option value="" ${!f.status?"selected":""}>Everyone</option></select></div>
 <div class="tbl-wrap"><table><thead><tr><th>Member</th><th>Office</th><th>Stage</th><th>NeoLife rank</th><th>Phone</th><th>Sponsor</th><th>Joined</th></tr></thead><tbody>
 ${ms.map(m=>`<tr class="click" data-act="profile" data-id="${m.id}"><td><div class="name-cell">${avatar(m)}<div>${esc(m.fullName)}<div class="small muted">${esc(m.code||"")}</div></div></div></td><td>${esc((officeById(m.officeId)||{}).name||"")}</td><td>${stageTag(m.stage)}</td><td>${rankTag(m.rank)}</td><td>${esc(m.phone||"")}</td><td>${esc(sponsorName(m))}</td><td>${fmtDate(m.joinedDate)}</td></tr>`).join("")||`<tr><td colspan="7" class="empty">No members yet. Add your first member to get started.</td></tr>`}
 </tbody></table></div>`}
function memberForm(m){m=m||{};const isNew=!m.id;const defOff=m.officeId||singleOffice();
 const others=S.members.filter(x=>x.id!==m.id);
 const tools=m.tools||[];
 return`<div class="scrim"><div class="modal" role="dialog" aria-modal="true"><div class="mhead"><h2>${isNew?"Add member":"Edit "+esc(m.fullName)}</h2><button class="x" data-act="close" aria-label="Close">×</button></div>
 <form data-form="member" data-id="${m.id||""}">
 <div class="row" style="gap:16px"><span id="photoPrev">${avatar(m,"lg")}</span><label class="btn sm">Upload passport photo<input type="file" accept="image/*" id="photoIn" data-act="photo" hidden></label><input type="hidden" name="photo" id="photoVal" value="${esc(m.photo||"")}"></div>
 <fieldset><legend>Personal details</legend><div class="fg">
  <label class="f full">Full name<input name="fullName" required value="${esc(m.fullName||"")}"></label>
  <label class="f">Gender<select name="gender"><option value="">—</option>${["Male","Female"].map(g=>`<option ${m.gender===g?"selected":""}>${g}</option>`).join("")}</select></label>
  <label class="f">Date of birth<input type="date" name="dob" value="${esc(m.dob||"")}"></label>
  <label class="f">Phone number<input name="phone" type="tel" value="${esc(m.phone||"")}"></label>
  <label class="f">Email<input name="email" type="email" value="${esc(m.email||"")}"></label>
  <label class="f full">Home address<input name="address" value="${esc(m.address||"")}"></label></div></fieldset>
 <fieldset><legend>Parent or guardian</legend><div class="fg">
  <label class="f">Name<input name="gName" value="${esc(m.gName||"")}"></label><label class="f">Relationship<input name="gRel" value="${esc(m.gRel||"")}" placeholder="Father, Mother, Uncle…"></label>
  <label class="f">Phone<input name="gPhone" type="tel" value="${esc(m.gPhone||"")}"></label><label class="f">Email<input name="gEmail" type="email" value="${esc(m.gEmail||"")}"></label></div></fieldset>
 <fieldset><legend>Business</legend><div class="fg">
  <label class="f">Office<select name="officeId" required>${S.offices.map(o=>`<option value="${o.id}" ${+defOff===o.id?"selected":""}>${esc(o.name)}</option>`).join("")}</select></label>
  <label class="f">Date joined the team<input type="date" name="joinedDate" value="${esc(m.joinedDate||S.todayStr)}"></label>
  <label class="f">NeoLife distributor ID<input name="neolifeId" value="${esc(m.neolifeId||"")}"></label>
  <label class="f">Sponsor name<input name="sponsorName" list="memberNames" value="${esc(sponsorName(m))}" autocomplete="off"></label>
  <label class="f">Sponsor phone<input name="sponsorPhone" type="tel" value="${esc(sponsorPhone(m)||"")}"></label>
  <label class="f">Upline<input name="uplineName" list="memberNames" value="${esc(uplineName(m))}" autocomplete="off"></label>
  <datalist id="memberNames">${others.map(x=>`<option value="${esc(x.fullName)}">`).join("")}</datalist></div></fieldset>
 <fieldset><legend>Status</legend><div class="fg">
  <label class="f">Team stage<select name="stage">${STAGES.map(s=>`<option ${(m.stage||"New Member")===s?"selected":""}>${s}</option>`).join("")}</select></label>
  <label class="f">NeoLife rank<select name="rank"><option value="">Not yet ranked</option>${ranks().map(r=>`<option ${m.rank===r?"selected":""}>${esc(r)}</option>`).join("")}</select></label>
  <div class="f"><span class="small muted" style="font-weight:600">Tools bought (for Tools Owner)</span><div class="row"><label class="chk"><input type="checkbox" name="toolPhone" ${tools.includes("Phone")?"checked":""}> Phone</label><label class="chk"><input type="checkbox" name="toolLaptop" ${tools.includes("Laptop")?"checked":""}> Laptop</label></div></div>
  <label class="f">Date tools bought<input type="date" name="toolsDate" value="${esc(m.toolsDate||"")}"></label>
  <label class="f">Membership<select name="status"><option value="active" ${(m.status||"active")==="active"?"selected":""}>Active</option><option value="inactive" ${m.status==="inactive"?"selected":""}>Inactive</option></select></label>
  <label class="f">${m.hasPin?"New check-in PIN (leave blank to keep)":"Check-in PIN (4–6 digits)"}<input name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password"></label></div></fieldset>
 <fieldset><legend>Background</legend><div class="fg">
  <label class="f full">Job or occupation before joining<input name="occupation" value="${esc(m.occupation||"")}"></label>
  <label class="f full">One strong reason for joining<textarea name="why">${esc(m.why||"")}</textarea></label>
  <label class="f">Joined NeoLife before?<select name="prevJoined"><option value="no" ${m.prevJoined!=="yes"?"selected":""}>No</option><option value="yes" ${m.prevJoined==="yes"?"selected":""}>Yes</option></select></label>
  <label class="f">If yes, roughly when<input name="prevWhen" value="${esc(m.prevWhen||"")}" placeholder="e.g. 2023"></label>
  <label class="f full">If yes, why did they quit?<textarea name="whyQuit">${esc(m.whyQuit||"")}</textarea></label>
  <label class="f full">Leader notes<textarea name="notes">${esc(m.notes||"")}</textarea></label></div></fieldset>
 <fieldset><label class="chk"><input type="checkbox" name="consent" required ${m.consent?"checked":""}> The member (and a parent or guardian for anyone under 18) agreed to these details being kept by the team.</label></fieldset>
 <div class="row" style="margin-top:18px"><button class="btn pri">${isNew?"Add member":"Save changes"}</button><button type="button" class="btn" data-act="close">Cancel</button></div></form></div></div>`}
function profileModal(m){const o=officeById(m.officeId)||{};const kids=S.members.filter(x=>x.id!==m.id&&(+x.sponsorId===m.id||(x.sponsorName||"").trim().toLowerCase()===m.fullName.trim().toLowerCase()));
 const dl=rows=>{const h=rows.filter(r=>r[1]!==""&&r[1]!=null).map(([k,v])=>`<dt>${esc(k)}</dt><dd>${v}</dd>`).join("");return h?`<dl class="dl">${h}</dl>`:'<p class="muted small">Not added.</p>'};
 const hist=[...(m.rankHistory||[]).map(h=>({d:h.date,t:h.rank})),...(m.stageHistory||[]).map(h=>({d:h.date,t:h.stage}))].sort((x,y)=>(y.d||"").localeCompare(x.d||""));
 return`<div class="scrim" data-act="closeBg"><div class="modal" role="dialog" aria-modal="true"><div class="mhead"><div class="row" style="gap:16px">${avatar(m,"lg")}<div><h1>${esc(m.fullName)}</h1><div class="muted">${esc(m.code||"")} · ${esc(o.name||"")}</div><div class="row" style="margin-top:6px">${stageTag(m.stage)}${m.rank?rankTag(m.rank):""}${m.status==="inactive"?'<span class="tag red">Inactive</span>':""}</div></div></div><button class="x" data-act="close" aria-label="Close">×</button></div>
 <div class="row"><button class="btn pri" data-act="editMember" data-id="${m.id}">Edit</button><button class="btn" data-act="logFu" data-id="${m.id}">Log follow-up</button>${isAdmin()?`<button class="btn danger" data-act="delMember" data-id="${m.id}">Delete</button>`:""}</div>
 <h3 class="section-t">Attendance history</h3><p class="small muted" style="margin:0 0 8px">Tap a month to see each day.</p><div id="profHist" class="muted small">Loading…</div>
 <h3 class="section-t">Follow-ups</h3><div id="profFu" class="muted small">Loading…</div>
 <h3 class="section-t">Business numbers</h3><div id="profPerf" class="muted small">Loading…</div>
 <h3 class="section-t">Trainings</h3><div id="profTr" class="muted small">Loading…</div>
 ${m.why?`<h3 class="section-t">Why they joined</h3><p style="margin:0">${esc(m.why)}</p>`:""}
 <h3 class="section-t">Personal</h3>${dl([["Gender",esc(m.gender||"")],["Date of birth",m.dob?`${fmtDate(m.dob)} (${age(m.dob)} years)`:""],["Phone",m.phone?`<a href="tel:${esc(m.phone)}">${esc(m.phone)}</a>`:""],["Email",esc(m.email||"")],["Address",esc(m.address||"")]])}
 <h3 class="section-t">Parent or guardian</h3>${dl([["Name",esc(m.gName||"")],["Relationship",esc(m.gRel||"")],["Phone",esc(m.gPhone||"")],["Email",esc(m.gEmail||"")]])}
 <h3 class="section-t">Business</h3>${dl([["Joined team",fmtDate(m.joinedDate)],["NeoLife ID",esc(m.neolifeId||"")],["Sponsor",esc(sponsorName(m))],["Sponsor phone",esc(sponsorPhone(m)||"")],["Upline",esc(uplineName(m))],["Tools owned",(m.tools||[]).length?esc(m.tools.join(" & "))+(m.toolsDate?` (since ${fmtDate(m.toolsDate)})`:""):""],["People they sponsored",kids.map(k=>esc(k.fullName)).join(", ")],["Check-in PIN",m.hasPin?"Set":"Not set"]])}
 <h3 class="section-t">Background</h3>${dl([["Occupation before",esc(m.occupation||"")],["Joined NeoLife before",m.prevJoined==="yes"?"Yes"+(m.prevWhen?` (${esc(m.prevWhen)})`:""):"No"],["Why they quit",esc(m.whyQuit||"")],["Leader notes",esc(m.notes||"")]])}
 ${hist.length?`<h3 class="section-t">Progress history</h3><ul class="list">${hist.map(h=>`<li><span>${esc(h.t)}</span><span class="muted small">${fmtDate(h.d)}</span></li>`).join("")}</ul>`:""}</div></div>`}
async function fillProfileAtt(m){const o=officeById(m.officeId);if(!o)return;const month=S.todayStr.slice(0,7);
 try{const r=await api("att_range",{officeId:o.id,from:month+"-01",to:S.todayStr});const {rows}=monthSummary(o,toDays(r),month);const x=rows.find(y=>y.m.id===m.id);const el=document.getElementById("profAtt");if(!el)return;
  el.innerHTML=x&&x.due?`<div class="row"><b style="font-size:1.4rem;color:var(--ink)">${x.pct==null?"—":x.pct+"%"}</b><span>${x.p} present · ${x.l} late · ${x.a} absent · ${x.e} excused${x.avg?` · ${x.avg.toFixed(1)} hrs/day`:""}</span></div>`:"No session days yet this month."}catch(e){}}

/* ---------- leaders (admin) ---------- */
function genPw(){const A="abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789",r=crypto.getRandomValues(new Uint32Array(10));return[...r].map(x=>A[x%A.length]).join("")}
function loginCard(name,email,pw){const url=location.origin+location.pathname.replace(/[^/]*$/,"");
 openModal(`<div class="scrim" data-act="closeBg"><div class="modal narrow" role="dialog" aria-modal="true"><div class="mhead"><h2>Login for ${esc(name)}</h2><button class="x" data-act="close" aria-label="Close">×</button></div>
 <p>Send these to ${esc(name)}. They can change the password after logging in.</p><textarea id="loginTxt" readonly style="min-height:110px;font-family:monospace">Unstoppable Team HQ\nLink: ${esc(url)}\nEmail: ${esc(email)}\nPassword: ${esc(pw)}</textarea>
 <div class="row" style="margin-top:12px"><button class="btn pri" data-act="copyLogin">Copy</button><button class="btn" data-act="close">Done</button></div></div></div>`)}

/* ---------- finance (admin) ---------- */
function finTabs(){return`<div class="seg"><button data-act="finTab" data-t="month" class="${S.fin.tab!=="bal"?"on":""}">Month</button><button data-act="finTab" data-t="bal" class="${S.fin.tab==="bal"?"on":""}">All-time balances</button></div>`}
function finView(){const month=S.fin.month;if(S.fin.tab==="bal")return`<div class="top"><div><h1>Finance</h1><p>Only admins can see and change this page.</p></div><div class="row">${finTabs()}</div></div>${balancesView()}`;
 if(!S.fin.entries||S.fin.entries.month!==month){if(!S.fin.loading){S.fin.loading=true;api("finance_list",{month}).then(r=>{S.fin.entries={month,list:r.entries};S.fin.loading=false;render()}).catch(e=>{S.fin.loading=false;toast(e.message)})}return`<div class="top"><h1>Finance</h1></div><div class="card empty">Loading…</div>`}
 const entries=S.fin.entries.list;const tot={},per={};
 entries.forEach(e=>{const t=TX.find(x=>x.k===e.type)||TX[0],c=e.currency;tot[c]=tot[c]||{in:0,out:0,by:{}};if(t.d>0)tot[c].in+=e.amount;else tot[c].out+=e.amount;tot[c].by[t.l]=(tot[c].by[t.l]||0)+e.amount;
  const k=(e.memberId||"_")+"|"+c;per[k]=per[k]||{name:e.memberName,c,in:0,out:0};if(t.d>0)per[k].in+=e.amount;else per[k].out+=e.amount});
 return`<div class="top"><div><h1>Finance</h1><p>Only admins can see and change this page.</p></div><div class="row">${finTabs()}<input type="month" id="finMonth" data-act="finMonth" value="${esc(month)}"><button class="btn" data-act="exportFin">Export CSV</button></div></div>
 <div class="grid g3">${Object.keys(tot).length?Object.entries(tot).map(([c,t])=>`<div class="card"><div class="statlbl">${c} this month</div><div class="stat" style="margin-top:6px">${money(t.in-t.out,c)}</div><div class="small muted" style="margin-top:6px">In ${money(t.in,c)} · Out ${money(t.out,c)}</div><ul class="list small" style="margin-top:8px">${Object.entries(t.by).map(([k,v])=>`<li><span>${esc(k)}</span><span>${money(v,c)}</span></li>`).join("")}</ul></div>`).join(""):'<div class="card muted">No entries this month yet.</div>'}</div>
 <div class="card" style="margin-top:16px"><h2>Add an entry</h2><form data-form="fin" style="margin-top:12px"><div class="fg">
  <label class="f">Date<input type="date" name="date" value="${S.todayStr.slice(0,7)===month?S.todayStr:month+"-01"}" required></label>
  <label class="f">Member<select name="memberId"><option value="">Team (general)</option>${S.members.map(m=>`<option value="${m.id}">${esc(m.fullName)}</option>`).join("")}</select></label>
  <label class="f">Type<select name="type">${TX.map(t=>`<option value="${t.k}">${t.l}</option>`).join("")}</select></label>
  <label class="f">Currency<select name="currency">${CUR.map(c=>`<option>${c}</option>`).join("")}</select></label>
  <label class="f">Amount<input type="number" name="amount" min="0" step="0.01" required></label>
  <label class="f">Note<input name="note" placeholder="e.g. Fiverr order, laptop deposit"></label></div>
  <div class="row" style="margin-top:12px"><button class="btn pri">Add entry</button></div></form></div>
 ${Object.keys(per).length?`<h2 class="section-t">By member</h2><div class="tbl-wrap"><table><thead><tr><th>Member</th><th>Currency</th><th>Money in</th><th>Money out</th><th>Net</th></tr></thead><tbody>${Object.values(per).map(p=>`<tr><td>${esc(p.name)}</td><td>${p.c}</td><td>${money(p.in,p.c)}</td><td>${money(p.out,p.c)}</td><td><b>${money(p.in-p.out,p.c)}</b></td></tr>`).join("")}</tbody></table></div>`:""}
 <h2 class="section-t">Entries</h2><div class="tbl-wrap"><table><thead><tr><th>Date</th><th>Member</th><th>Type</th><th>Amount</th><th>Note</th><th></th></tr></thead><tbody>
 ${entries.map(e=>{const t=TX.find(x=>x.k===e.type)||TX[0];return`<tr><td>${fmtDate(e.date)}</td><td>${esc(e.memberName)}</td><td><span class="tag ${t.d>0?"green":"red"}">${esc(t.l)}</span></td><td>${money(e.amount,e.currency)}</td><td class="small muted">${esc(e.note||"")}</td><td><button class="btn sm danger" data-act="delTx" data-id="${e.id}">Delete</button></td></tr>`}).join("")||'<tr><td colspan="6" class="empty">No entries yet.</td></tr>'}</tbody></table></div>`}

/* ---------- settings (admin) ---------- */
function settingsView(){return`<div class="top"><div><h1>Settings</h1><p>Offices, ranks, trainings, alerts, backups and the activity log.</p></div></div>
 <div class="card"><h2>Offices</h2><form data-form="offices"><div class="tbl-wrap" style="margin-top:12px"><table><thead><tr><th>Office name</th><th>Code</th><th>Late after</th><th>Closes at</th><th></th></tr></thead><tbody>
 ${S.offices.map(o=>`<tr><td><input name="name_${o.id}" value="${esc(o.name)}" required></td><td><input name="code_${o.id}" value="${esc(o.code)}" maxlength="5" style="width:80px"></td><td><input type="time" name="late_${o.id}" value="${esc(o.lateAfter)}"></td><td><input type="time" name="close_${o.id}" value="${esc(o.closeAt)}"></td><td><button type="button" class="btn sm danger" data-act="delOffice" data-id="${o.id}">Delete</button></td></tr>`).join("")||'<tr><td colspan="5" class="empty">No offices yet.</td></tr>'}
 </tbody></table></div><div class="row" style="margin-top:12px"><button class="btn pri">Save offices</button></div></form>
 <form data-form="addOffice" class="row" style="margin-top:16px"><input name="name" placeholder="New office name, e.g. Unstoppable Team Lagos" required style="flex:1;min-width:220px"><button class="btn">Add office</button></form>
 <p class="small muted" style="margin-top:10px">The code goes into member IDs (UT-CODE-001). Change it before adding members.</p></div>
 <div class="card" style="margin-top:16px"><h2>NeoLife rank ladder</h2><p class="muted small">One rank per line, lowest first.</p><form data-form="ranks"><textarea name="ranks" style="min-height:260px;margin-top:10px">${esc(ranks().join("\n"))}</textarea><div class="row" style="margin-top:10px"><button class="btn pri">Save ranks</button></div></form></div>${settingsExtras()}`}


/* ===================== NEW FEATURES ===================== */
const FU_METHODS={call:"Phone call",whatsapp:"WhatsApp",sms:"SMS",visit:"Home visit",in_person:"In person",other:"Other"};
const P_STATUS={new:["New","tag"],invited:["Invited","blue"],attended:["Attended training","gold"],follow_up:["Following up","blue"],joined:["Joined","green"],not_interested:["Not interested","red"]};
const P_SOURCES=["Personal contact","WhatsApp","Social media","Referral","Event","Other"];
const pTag=s=>{const x=P_STATUS[s]||P_STATUS.new;return`<span class="tag ${x[1]==="tag"?"":x[1]}">${x[0]}</span>`};
const num=n=>Number(n||0).toLocaleString(undefined,{maximumFractionDigits:2});
function monthLabel(m){return dayOf(m+"-15").toLocaleDateString(undefined,{month:"short",year:"numeric"})}
function addMonths(m,n){const d=dayOf(m+"-15");d.setMonth(d.getMonth()+n);return ymd(d).slice(0,7)}

/* ---------- login extras ---------- */
function resetView(){return`<div class="main"><div class="card" style="max-width:420px;margin:10vh auto"><div class="brand" style="padding:0 0 6px">Unstoppable Team<small>Team HQ</small></div><h1 style="margin-top:10px">Choose a new password</h1>
 <form data-form="reset" style="margin-top:16px"><div class="grid"><label class="f">New password (8+ characters)<input id="rsPw" type="password" name="new" required minlength="8" autocomplete="new-password"></label><label class="f">Type it again<input type="password" name="again" required minlength="8" autocomplete="new-password"></label></div>
 <button class="btn pri" style="width:100%;margin-top:18px;padding:12px">Save new password</button></form><p class="small" style="margin-top:14px"><a data-act="backToLogin" style="cursor:pointer;color:var(--green);font-weight:600">Back to log in</a></p></div></div>`}
function forgotModal(){openModal(`<div class="scrim" data-act="closeBg"><div class="modal narrow" role="dialog" aria-modal="true"><div class="mhead"><h2>Reset your password</h2><button class="x" data-act="close" aria-label="Close">×</button></div>
 <p class="muted">Enter your email. If it's registered, we'll send a link to choose a new password. The link works for 1 hour.</p>
 <form data-form="forgot"><label class="f">Email<input type="email" name="email" required autocomplete="username"></label><button class="btn pri" style="width:100%;margin-top:14px">Send reset link</button></form>
 <p class="small muted" style="margin-top:12px">No email after a few minutes? Check spam, or ask another admin to reset it on the Team page.</p></div></div>`)}

/* ---------- follow-ups ---------- */
function followupModal(m){openModal(`<div class="scrim" data-act="closeBg"><div class="modal narrow" role="dialog" aria-modal="true"><div class="mhead"><div><h2>Log a follow-up</h2><div class="small muted">${esc(m.fullName)}${m.phone?` · <a href="tel:${esc(m.phone)}">${esc(m.phone)}</a>`:""}</div></div><button class="x" data-act="close" aria-label="Close">×</button></div>
 <form data-form="followup" data-id="${m.id}"><div class="grid"><div class="fg"><label class="f">Date<input type="date" name="date" value="${S.todayStr}" max="${S.todayStr}" required></label>
 <label class="f">How<select name="method">${Object.entries(FU_METHODS).map(([k,v])=>`<option value="${k}">${v}</option>`).join("")}</select></label></div>
 <label class="f">What happened<textarea name="outcome" required placeholder="e.g. Was sick, says he'll be back Monday"></textarea></label>
 <label class="f">Next step (optional)<input name="nextStep" placeholder="e.g. Call again Monday morning"></label></div>
 <div class="row" style="margin-top:14px"><button class="btn pri">Save follow-up</button><button type="button" class="btn" data-act="close">Cancel</button></div></form></div></div>`)}

/* ---------- profile history ---------- */
async function fillProfile(m){const o=officeById(m.officeId);if(!o)return;let h;
 try{h=await api("member_history",{memberId:m.id})}catch(e){const el=document.getElementById("profHist");if(el)el.textContent=e.message;return}
 const byDate={};h.att.forEach(r=>byDate[r.date]=r);const ses=new Set(h.sessions);
 const months=[];let mo=S.todayStr.slice(0,7);const start=(m.joinedDate||S.todayStr).slice(0,7);
 for(let i=0;i<12&&mo>=start;i++){months.push(mo);mo=addMonths(mo,-1)}
 const sum=months.map(mm=>{const last=new Date(+mm.slice(0,4),+mm.slice(5),0).getDate();let due=0,p=0,l=0,e=0;
  for(let d=1;d<=last;d++){const s=`${mm}-${pad(d)}`;if(s>S.todayStr)break;if(m.joinedDate&&s<m.joinedDate)continue;if(isWeekend(s)&&!ses.has(s))continue;due++;const st=recStatus(byDate[s],o);if(st==="present")p++;else if(st==="late"){p++;l++}else if(st==="excused")e++}
  return{mm,due,p,l,e,a:due-p-e,pct:due-e>0?Math.round(p/(due-e)*100):null}});
 const el=document.getElementById("profHist");if(!el)return;
 el.innerHTML=`<div class="tbl-wrap"><table><thead><tr><th>Month</th><th>Attendance</th><th>Present</th><th>Late</th><th>Absent</th><th>Excused</th></tr></thead><tbody>${sum.map(x=>`<tr class="click" data-act="histMonth" data-m="${x.mm}"><td>${monthLabel(x.mm)}</td><td><b>${x.pct==null?"—":x.pct+"%"}</b></td><td>${x.p}</td><td>${x.l}</td><td>${x.a}</td><td>${x.e}</td></tr>`).join("")||'<tr><td colspan="6" class="empty">No history yet.</td></tr>'}</tbody></table></div>
 <div id="histDays" style="margin-top:10px"></div>`;
 el._data={h,o,m,byDate,ses};showHistMonth(S.todayStr.slice(0,7));
 const fu=document.getElementById("profFu");
 if(fu)fu.innerHTML=h.followups.length?`<ul class="list">${h.followups.map(f=>`<li style="align-items:flex-start"><div><b>${fmtDate(f.date)}</b> · ${esc(FU_METHODS[f.method]||f.method)} <span class="muted small">by ${esc(f.by)}</span><div>${esc(f.outcome)}</div>${f.nextStep?`<div class="small muted">Next: ${esc(f.nextStep)}</div>`:""}</div>${isAdmin()||f.byUser===S.me.id?`<button class="btn sm danger" data-act="delFu" data-id="${f.id}" data-m="${m.id}">Delete</button>`:""}</li>`).join("")}</ul>`:'<p class="muted small">No follow-ups logged yet.</p>';
 const pf=document.getElementById("profPerf");
 if(pf)pf.innerHTML=h.performance.length?`<div class="tbl-wrap"><table><thead><tr><th>Month</th><th>PV</th><th>Target PV</th><th>BV</th><th>Sales</th></tr></thead><tbody>${h.performance.map(p=>`<tr><td>${monthLabel(p.month)}</td><td><b>${num(p.pv)}</b>${p.targetPv?` <span class="tag ${p.pv>=p.targetPv?"green":"gold"}">${Math.round(p.pv/p.targetPv*100)}%</span>`:""}</td><td>${p.targetPv?num(p.targetPv):"—"}</td><td>${num(p.bv)}</td><td>${num(p.sales)}</td></tr>`).join("")}</tbody></table></div>`:'<p class="muted small">No business numbers recorded yet.</p>';
 const tr=document.getElementById("profTr");
 if(tr)tr.innerHTML=h.trainings.length?`<p class="small">${h.trainings.length} training${h.trainings.length===1?"":"s"} attended recently. Last: ${esc(h.trainings[0].type)} on ${fmtDate(h.trainings[0].date)}.</p>`:'<p class="muted small">No trainings recorded yet.</p>';
}
function showHistMonth(mm){const el=document.getElementById("profHist");if(!el||!el._data)return;const {o,m,byDate,ses}=el._data;const box=document.getElementById("histDays");
 const last=new Date(+mm.slice(0,4),+mm.slice(5),0).getDate();const rows=[];
 for(let d=last;d>=1;d--){const s=`${mm}-${pad(d)}`;if(s>S.todayStr)continue;if(m.joinedDate&&s<m.joinedDate)continue;if(isWeekend(s)&&!ses.has(s))continue;const r=byDate[s];rows.push({s,r,st:recStatus(r,o)})}
 box.innerHTML=`<p class="small muted" style="margin:0 0 6px">${monthLabel(mm)}, day by day</p><div class="tbl-wrap"><table><thead><tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Hours</th><th>Note</th><th>Last edited by</th></tr></thead><tbody>${rows.map(x=>`<tr><td>${dayOf(x.s).toLocaleDateString(undefined,{weekday:"short",day:"numeric",month:"short"})}</td><td>${statusTag[x.st]}</td><td>${hm(x.r&&x.r.in)||"—"}</td><td>${hm(x.r&&x.r.out)||"—"}</td><td>${hours(x.r)?hours(x.r).toFixed(1):"—"}</td><td class="small muted">${esc((x.r&&x.r.note)||"")}</td><td class="small muted">${x.r&&x.r.byName?esc(x.r.byName):"—"}</td></tr>`).join("")||'<tr><td colspan="7" class="empty">No session days.</td></tr>'}</tbody></table></div>`}

/* ---------- prospects ---------- */
async function loadProspectsNow(){const r=await api("prospects_list");S.prospects=r.prospects;render()}
function loadProspects(force){if(S.prospects&&!force)return S.prospects;if(loadProspects.busy)return null;loadProspects.busy=true;
 api("prospects_list").then(r=>{S.prospects=r.prospects;loadProspects.busy=false;render()}).catch(e=>{loadProspects.busy=false;toast(e.message)});return null}
function prospectsView(){const all=loadProspects();if(!all)return`<div class="top"><h1>Prospects</h1></div><div class="card empty">Loading…</div>`;
 const ids=scopeIds(),f=S.pf||(S.pf={status:"",q:""});let ps=all.filter(p=>ids.includes(p.officeId));
 const counts={};ps.forEach(p=>counts[p.status]=(counts[p.status]||0)+1);
 const month=S.todayStr.slice(0,7),newM=ps.filter(p=>(p.createdAt||"").startsWith(month)).length,joined=counts.joined||0,closed=joined+(counts.not_interested||0);
 const inv={};ps.forEach(p=>{const k=(p.invitedBy||"").trim();if(!k)return;inv[k]=inv[k]||{n:0,j:0};inv[k].n++;if(p.status==="joined")inv[k].j++});
 const top=Object.entries(inv).sort((a,b)=>b[1].j-a[1].j||b[1].n-a[1].n).slice(0,6);
 let shown=ps.filter(p=>(!f.status||p.status===f.status)&&(!f.q||[p.name,p.phone,p.invitedBy].join(" ").toLowerCase().includes(f.q.toLowerCase())));
 return`<div class="top"><div><h1>Prospects</h1><p>People invited to the business, before they join.</p></div><div class="row">${officePicker()}<button class="btn pri" data-act="newProspect">Add prospect</button></div></div>
 <div class="grid g3"><div class="card"><div class="stat">${ps.length}</div><div class="statlbl">Prospects in total</div></div><div class="card"><div class="stat">${newM}</div><div class="statlbl">Added this month</div></div><div class="card"><div class="stat">${joined}</div><div class="statlbl">Joined${closed?` · ${Math.round(joined/Math.max(1,ps.length)*100)}% of all prospects`:""}</div></div></div>
 ${top.length?`<div class="card" style="margin-top:16px"><h2>Top inviters</h2><ul class="list" style="margin-top:8px">${top.map(([k,v])=>`<li><span>${esc(k)}</span><span><b>${v.n}</b> invited · <b>${v.j}</b> joined</span></li>`).join("")}</ul></div>`:""}
 <div class="row" style="margin:16px 0 12px"><input id="pq" data-act="pq" placeholder="Search name, phone, inviter" value="${esc(f.q)}" style="flex:1;min-width:200px">
 <select data-act="pstatus" aria-label="Status"><option value="">All statuses</option>${Object.entries(P_STATUS).map(([k,v])=>`<option value="${k}" ${f.status===k?"selected":""}>${v[0]} (${counts[k]||0})</option>`).join("")}</select></div>
 <div class="tbl-wrap"><table><thead><tr><th>Name</th><th>Phone</th><th>Invited by</th><th>Status</th><th>Trainings</th><th>First contact</th>${S.offices.length>1?"<th>Office</th>":""}</tr></thead><tbody>
 ${shown.map(p=>`<tr class="click" data-act="editProspect" data-id="${p.id}"><td><b>${esc(p.name)}</b></td><td>${esc(p.phone)}</td><td>${esc(p.invitedBy)}</td><td>${pTag(p.status)}</td><td>${p.trainings.count||"—"}</td><td>${fmtDate(p.firstContact)}</td>${S.offices.length>1?`<td>${esc((officeById(p.officeId)||{}).name||"")}</td>`:""}</tr>`).join("")||`<tr><td colspan="7" class="empty">No prospects yet. Add the people your team is inviting.</td></tr>`}
 </tbody></table></div>`}
function prospectModal(p){p=p||{status:"new",officeId:singleOffice(),firstContact:S.todayStr};const isNew=!p.id;
 openModal(`<div class="scrim"><div class="modal" role="dialog" aria-modal="true"><div class="mhead"><h2>${isNew?"Add prospect":esc(p.name)}</h2><button class="x" data-act="close" aria-label="Close">×</button></div>
 <form data-form="prospect" data-id="${p.id||""}"><div class="fg">
  <label class="f">Full name<input name="name" required value="${esc(p.name||"")}"></label><label class="f">Phone<input name="phone" type="tel" value="${esc(p.phone||"")}"></label>
  <label class="f">Office<select name="officeId">${S.offices.map(o=>`<option value="${o.id}" ${p.officeId===o.id?"selected":""}>${esc(o.name)}</option>`).join("")}</select></label>
  <label class="f">Invited by<input name="invitedBy" list="memberNames2" value="${esc(p.invitedBy||"")}" autocomplete="off"><datalist id="memberNames2">${S.members.map(x=>`<option value="${esc(x.fullName)}">`).join("")}</datalist></label>
  <label class="f">How we met them<select name="source"><option value="">—</option>${P_SOURCES.map(s=>`<option ${p.source===s?"selected":""}>${s}</option>`).join("")}</select></label>
  <label class="f">First contact<input type="date" name="firstContact" value="${esc(p.firstContact||"")}"></label>
  <label class="f">Status<select name="status">${Object.entries(P_STATUS).map(([k,v])=>`<option value="${k}" ${p.status===k?"selected":""}>${v[0]}</option>`).join("")}</select></label>
  <label class="f full">Notes<textarea name="notes">${esc(p.notes||"")}</textarea></label></div>
 ${p.id?`<p class="small muted">Trainings attended: ${p.trainings.count}${p.trainings.last?`, last on ${fmtDate(p.trainings.last)}`:""}.${p.memberId&&S.byId[p.memberId]?` Now a member: ${esc(S.byId[p.memberId].fullName)}.`:""}</p>`:""}
 <div class="row" style="margin-top:14px"><button class="btn pri">${isNew?"Add prospect":"Save"}</button>${p.id&&p.status!=="joined"?`<button type="button" class="btn" data-act="convertProspect" data-id="${p.id}">They joined: add as member</button>`:""}${p.id?`<button type="button" class="btn danger" data-act="delProspect" data-id="${p.id}">Delete</button>`:""}<button type="button" class="btn" data-act="close">Cancel</button></div></form></div></div>`)}

/* ---------- trainings & fines ---------- */
function trainingsView(){const oid=singleOffice(),o=officeById(oid);if(!o)return'<div class="card empty">No office available.</div>';
 const mo=S.trMonth||(S.trMonth=S.todayStr.slice(0,7)),key=oid+":"+mo;S.trCache=S.trCache||{};
 if(!S.trCache[key]){if(!S.trLoading){S.trLoading=true;api("trainings_list",{officeId:oid,month:mo}).then(r=>{S.trCache[key]=r;S.trLoading=false;render()}).catch(e=>{S.trLoading=false;toast(e.message)})}return`<div class="top"><h1>Trainings</h1></div><div class="card empty">Loading…</div>`}
 if(!S.prospects)loadProspects();
 const d=S.trCache[key],rule=S.settings.fineRule,ts=d.trainings;
 const ms=S.members.filter(m=>m.officeId===oid&&m.status!=="inactive");
 const fineTs=ts.filter(t=>t.type===rule.type);
 const fines=ms.filter(m=>rule.ranks.includes(m.rank)).map(m=>{const due=fineTs.filter(t=>!m.joinedDate||m.joinedDate<=t.date);const missed=due.filter(t=>!t.members.includes(m.id)).length;return{m,missed,due:due.length}}).filter(x=>x.missed>rule.threshold);
 return`<div class="top"><div><h1>Trainings</h1><p>Record who attended each training. Guests (prospects) can be ticked too.</p></div><div class="row">${officePickerSingle(oid,"tOffice")}<input type="month" id="trMonth" data-act="trMonth" value="${esc(mo)}"><button class="btn pri" data-act="newTraining">Record a training</button></div></div>
 <div class="grid g2">${ts.map(t=>`<div class="card"><div class="row" style="justify-content:space-between"><div><h3>${esc(t.type)}</h3><div class="small muted">${dayOf(t.date).toLocaleDateString(undefined,{weekday:"long",day:"numeric",month:"long"})}${t.title?` · ${esc(t.title)}`:""}</div></div><div class="row"><button class="btn sm" data-act="editTraining" data-id="${t.id}">Edit</button><button class="btn sm danger" data-act="delTraining" data-id="${t.id}">Delete</button></div></div>
  <div class="row" style="margin-top:12px"><span class="tag green">${t.members.length} member${t.members.length===1?"":"s"}</span>${t.prospects.length?`<span class="tag blue">${t.prospects.length} guest${t.prospects.length===1?"":"s"}</span>`:""}</div>
  <div class="small muted" style="margin-top:8px">${t.members.map(id=>S.byId[id]?esc(S.byId[id].fullName):"").filter(Boolean).slice(0,8).join(", ")}${t.members.length>8?"…":""}</div></div>`).join("")||'<div class="card empty">No trainings recorded this month.</div>'}</div>
 <div class="card" style="margin-top:16px"><div class="row" style="justify-content:space-between"><h2>Fines for ${monthLabel(mo)}</h2><span class="small muted">${esc(rule.type)}: ₦${num(rule.amount)} if absent more than ${rule.threshold} time${rule.threshold===1?"":"s"} · ${rule.ranks.length} rank${rule.ranks.length===1?"":"s"} fined${isAdmin()?' · <a data-act="nav" data-v="settings" style="cursor:pointer;color:var(--green);font-weight:600">Change rule</a>':""}</span></div>
 ${fineTs.length?(fines.length?`<div class="tbl-wrap" style="margin-top:12px"><table><thead><tr><th>Member</th><th>Rank</th><th>Missed</th><th>Fine</th><th>Status</th><th></th></tr></thead><tbody>${fines.map(x=>{const paid=d.finesPaid.includes(x.m.id);return`<tr><td>${esc(x.m.fullName)}</td><td>${rankTag(x.m.rank)}</td><td>${x.missed} of ${x.due}</td><td><b>₦${num(rule.amount)}</b></td><td>${paid?'<span class="tag green">Paid</span>':'<span class="tag red">Unpaid</span>'}</td><td><button class="btn sm" data-act="finePaid" data-id="${x.m.id}" data-p="${paid?0:1}">${paid?"Mark unpaid":"Mark paid"}</button></td></tr>`}).join("")}</tbody></table></div><p class="small muted" style="margin-top:8px">Total: ₦${num(fines.length*rule.amount)} · Paid: ₦${num(fines.filter(x=>d.finesPaid.includes(x.m.id)).length*rule.amount)}</p>`:'<p class="muted">Nobody owes a fine this month.</p>'):`<p class="muted">No ${esc(rule.type)} recorded this month yet.</p>`}</div>`}
function trainingModal(t){const oid=singleOffice();t=t||{date:S.todayStr,type:S.settings.trainingTypes[0],title:"",members:[],prospects:[]};
 const ms=S.members.filter(m=>m.officeId===oid&&(m.status!=="inactive"||t.members.includes(m.id)));
 const ps=(S.prospects||[]).filter(p=>p.officeId===oid&&(!["joined","not_interested"].includes(p.status)||t.prospects.includes(p.id)));
 openModal(`<div class="scrim"><div class="modal" role="dialog" aria-modal="true"><div class="mhead"><h2>${t.id?"Edit training":"Record a training"}</h2><button class="x" data-act="close" aria-label="Close">×</button></div>
 <form data-form="training" data-id="${t.id||""}"><div class="fg"><label class="f">Date<input type="date" name="date" value="${esc(t.date)}" max="${S.todayStr}" required></label>
 <label class="f">Training<select name="type">${S.settings.trainingTypes.map(x=>`<option ${t.type===x?"selected":""}>${esc(x)}</option>`).join("")}</select></label>
 <label class="f">Topic (optional)<input name="title" value="${esc(t.title||"")}"></label></div>
 <fieldset><legend>Members present <span class="small muted" id="tmCount">${t.members.length}</span></legend><div class="row" style="margin-bottom:8px"><button type="button" class="btn sm" data-act="tickAll" data-g="tm" data-v="1">Tick all</button><button type="button" class="btn sm" data-act="tickAll" data-g="tm" data-v="0">Clear</button></div>
 <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px">${ms.map(m=>`<label class="chk"><input type="checkbox" name="tm" value="${m.id}" ${t.members.includes(m.id)?"checked":""}> ${esc(m.fullName)}</label>`).join("")||'<p class="muted small">No members in this office.</p>'}</div></fieldset>
 <fieldset><legend>Guests (prospects) present</legend><div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px">${ps.map(p=>`<label class="chk"><input type="checkbox" name="tp" value="${p.id}" ${t.prospects.includes(p.id)?"checked":""}> ${esc(p.name)}</label>`).join("")||'<p class="muted small">No open prospects. Add them on the Prospects page.</p>'}</div></fieldset>
 <div class="row" style="margin-top:16px"><button class="btn pri">Save training</button><button type="button" class="btn" data-act="close">Cancel</button></div></form></div></div>`)}

/* ---------- performance ---------- */
function perfView(){const oid=singleOffice(),o=officeById(oid);if(!o)return'<div class="card empty">No office available.</div>';
 const mo=S.pfMonth||(S.pfMonth=S.todayStr.slice(0,7)),key=oid+":"+mo;S.pfCache=S.pfCache||{};
 if(!S.pfCache[key]){if(!S.pfLoading){S.pfLoading=true;api("perf_list",{officeId:oid,month:mo}).then(r=>{const map={};r.rows.forEach(x=>map[x.memberId]=x);S.pfCache[key]=map;S.pfLoading=false;render()}).catch(e=>{S.pfLoading=false;toast(e.message)})}return`<div class="top"><h1>Business numbers</h1></div><div class="card empty">Loading…</div>`}
 const map=S.pfCache[key],ms=S.members.filter(m=>m.officeId===oid&&(m.status!=="inactive"||map[m.id]));
 const rows=ms.map(m=>({m,p:map[m.id]||{pv:0,bv:0,sales:0,targetPv:0,note:""}}));
 const totPv=rows.reduce((a,x)=>a+x.p.pv,0),totBv=rows.reduce((a,x)=>a+x.p.bv,0),hit=rows.filter(x=>x.p.targetPv>0&&x.p.pv>=x.p.targetPv).length,withT=rows.filter(x=>x.p.targetPv>0).length;
 const top=rows.filter(x=>x.p.pv>0).sort((a,b)=>b.p.pv-a.p.pv).slice(0,5);
 return`<div class="top"><div><h1>Business numbers</h1><p>Monthly PV, BV, sales and targets for ${esc(o.name)}.</p></div><div class="row">${officePickerSingle(oid,"pfOffice")}<input type="month" id="pfMonth" data-act="pfMonth" value="${esc(mo)}"></div></div>
 <div class="grid g3"><div class="card"><div class="stat">${num(totPv)}</div><div class="statlbl">Total PV</div></div><div class="card"><div class="stat">${num(totBv)}</div><div class="statlbl">Total BV</div></div><div class="card"><div class="stat">${hit}<span class="muted" style="font-size:1.1rem"> / ${withT}</span></div><div class="statlbl">Hit their PV target</div></div></div>
 ${top.length?`<div class="card" style="margin-top:16px"><h2>Top performers</h2><ul class="list" style="margin-top:8px">${top.map((x,i)=>`<li><span>${i+1}. ${esc(x.m.fullName)}</span><b>${num(x.p.pv)} PV</b></li>`).join("")}</ul></div>`:""}
 <form data-form="perf" style="margin-top:16px"><div class="tbl-wrap"><table><thead><tr><th>Member</th><th>PV</th><th>Target PV</th><th>Progress</th><th>BV</th><th>Sales (₦)</th><th>Note</th></tr></thead><tbody>
 ${rows.map(x=>{const pct=x.p.targetPv>0?Math.min(100,Math.round(x.p.pv/x.p.targetPv*100)):null;return`<tr><td><div class="name-cell">${avatar(x.m)}<div>${esc(x.m.fullName)}<div class="small muted">${esc(x.m.rank||x.m.stage||"")}</div></div></div></td>
 <td><input type="number" min="0" step="0.01" name="pv_${x.m.id}" value="${x.p.pv||""}" style="width:90px"></td><td><input type="number" min="0" step="0.01" name="tg_${x.m.id}" value="${x.p.targetPv||""}" style="width:90px"></td>
 <td>${pct==null?'<span class="muted small">—</span>':`<div class="row" style="flex-wrap:nowrap"><span class="bar" style="width:70px"><i style="width:${pct}%;background:${pct>=100?"var(--green)":"var(--gold)"}"></i></span><b class="small">${pct}%</b></div>`}</td>
 <td><input type="number" min="0" step="0.01" name="bv_${x.m.id}" value="${x.p.bv||""}" style="width:90px"></td><td><input type="number" min="0" step="0.01" name="sl_${x.m.id}" value="${x.p.sales||""}" style="width:110px"></td><td><input name="nt_${x.m.id}" value="${esc(x.p.note)}" style="width:150px"></td></tr>`}).join("")||'<tr><td colspan="7" class="empty">No members in this office.</td></tr>'}
 </tbody></table></div>${rows.length?'<div class="row" style="margin-top:12px"><button class="btn pri">Save numbers</button></div>':""}</form>`}

/* ---------- photos ---------- */
async function photoModal(mid,date){const m=S.byId[mid];const r=await api("att_photo",{memberId:mid,date});
 openModal(`<div class="scrim" data-act="closeBg"><div class="modal narrow" role="dialog" aria-modal="true"><div class="mhead"><div><h2>${esc(m?m.fullName:"Photos")}</h2><div class="small muted">${fmtDate(date)}</div></div><button class="x" data-act="close" aria-label="Close">×</button></div>
 <div class="grid g2">${r.in?`<figure style="margin:0"><img src="${esc(r.in)}" alt="Sign-in photo" style="width:100%;border-radius:10px"><figcaption class="small muted">Sign in</figcaption></figure>`:""}${r.out?`<figure style="margin:0"><img src="${esc(r.out)}" alt="Sign-out photo" style="width:100%;border-radius:10px"><figcaption class="small muted">Sign out</figcaption></figure>`:""}</div></div></div>`)}
const CAM={stream:null};
async function startCam(){const v=document.getElementById("cam");if(!v)return;try{CAM.stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:"user",width:{ideal:320},height:{ideal:240}},audio:false});v.srcObject=CAM.stream;document.getElementById("camMsg").textContent="Look at the camera"}catch(e){CAM.stream=null;const mm=document.getElementById("camMsg");if(mm)mm.textContent="Camera not available — signing in without a photo."}}
function stopCam(){if(CAM.stream){CAM.stream.getTracks().forEach(t=>t.stop());CAM.stream=null}}
function grabPhoto(){const v=document.getElementById("cam");if(!v||!CAM.stream||!v.videoWidth)return"";const c=document.createElement("canvas");c.width=240;c.height=Math.round(240*v.videoHeight/v.videoWidth);c.getContext("2d").drawImage(v,0,0,c.width,c.height);return c.toDataURL("image/jpeg",.6)}

/* ---------- team (admin) ---------- */
function teamView(){if(!S.users){api("users_list").then(r=>{S.users=r.users;render()}).catch(e=>toast(e.message));return'<div class="card empty">Loading…</div>'}
 const row=u=>{const self=u.id===S.me.id;return`<tr><td><b>${esc(u.name)}</b>${self?' <span class="tag">You</span>':""}${u.active?"":' <span class="tag red">Paused</span>'}</td><td>${esc(u.email)}</td><td>${u.role==="admin"?'<span class="tag gold">Admin</span>':'<span class="tag green">Leader</span>'}</td>
 <td>${u.role==="admin"?'<span class="small muted">All offices</span>':`<select data-act="leaderOffice" data-id="${u.id}" aria-label="Office">${u.officeId?"":'<option value="">— none —</option>'}${S.offices.map(o=>`<option value="${o.id}" ${u.officeId===o.id?"selected":""}>${esc(o.name)}</option>`).join("")}</select>`}</td><td class="small muted">${u.lastLogin?esc(u.lastLogin.slice(0,16)):"Never"}</td>
 <td><div class="row">${self?'<button class="btn sm" data-act="account">Change my password</button>':`<button class="btn sm" data-act="resetPw" data-id="${u.id}">Reset password</button><button class="btn sm" data-act="toggleLeader" data-id="${u.id}">${u.active?"Pause":"Resume"}</button><button class="btn sm danger" data-act="delLeader" data-id="${u.id}">Remove</button>`}</div></td></tr>`};
 const admins=S.users.filter(u=>u.role==="admin").length;
 return`<div class="top"><div><h1>Team</h1><p>Admins see everything. Leaders log in and only see their own office, without Finance, Team or Settings.</p></div></div>
 ${admins<2?'<div class="card" style="border-color:var(--gold);margin-bottom:16px"><b>Tip:</b> add a second admin you trust (or a second email of your own) so you can never be locked out.</div>':""}
 <div class="tbl-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Office</th><th>Last login</th><th></th></tr></thead><tbody>${S.users.map(row).join("")}</tbody></table></div>
 <div class="card" style="margin-top:16px"><h2>Add a person</h2><p class="small muted">You'll get their login to send them.</p>
 <form data-form="leader"><div class="fg" style="margin-top:12px"><label class="f">Full name<input name="name" required autocomplete="off"></label><label class="f">Email<input name="email" type="email" required autocomplete="off"></label>
 <label class="f">Role<select name="role" data-act="roleSel"><option value="leader">Team leader (one office)</option><option value="admin">Admin (everything)</option></select></label>
 <label class="f" id="roleOffice">Office<select name="officeId">${S.offices.map(o=>`<option value="${o.id}">${esc(o.name)}</option>`).join("")}</select></label>
 <label class="f">Password<input name="password" id="newPw" required minlength="8" value="${S.newPw=S.newPw||genPw()}" autocomplete="off"></label></div>
 <div class="row" style="margin-top:12px"><button class="btn pri">Add</button></div></form></div>`}

/* ---------- finance balances ---------- */
function balancesView(){if(!S.fin.bal){if(!S.fin.balLoading){S.fin.balLoading=true;api("finance_balances").then(r=>{S.fin.bal=r.rows;S.fin.balLoading=false;render()}).catch(e=>{S.fin.balLoading=false;toast(e.message)})}return'<div class="card empty">Loading…</div>'}
 const rows=S.fin.bal.slice().sort((a,b)=>a.currency.localeCompare(b.currency)||(b.in-b.out)-(a.in-a.out));
 return`<p class="muted">All-time totals for every member, from every month in the ledger.</p><div class="tbl-wrap"><table><thead><tr><th>Member</th><th>Currency</th><th>Money in</th><th>Money out</th><th>Balance</th><th>Entries</th><th>Last entry</th></tr></thead><tbody>
 ${rows.map(r=>`<tr><td>${esc(r.name)}</td><td>${r.currency}</td><td>${money(r.in,r.currency)}</td><td>${money(r.out,r.currency)}</td><td><b style="color:${r.in-r.out<0?"var(--red)":"var(--ink)"}">${money(r.in-r.out,r.currency)}</b></td><td>${r.count}</td><td>${fmtDate(r.last)}</td></tr>`).join("")||'<tr><td colspan="7" class="empty">No finance entries yet.</td></tr>'}</tbody></table></div>`}

/* ---------- settings extras ---------- */
function settingsExtras(){const st=S.settings,rule=st.fineRule;const base=location.origin+location.pathname.replace(/[^/]*$/,"");const cronUrl=`${base}cron.php?key=${st.cronKey}`;
 return`<div class="card" style="margin-top:16px"><h2>Check-in photos</h2><p class="small muted">Takes a small photo with the check-in device's camera each time someone signs in or out, so nobody can sign in for a friend. Photos show on the Attendance page.</p>
 <label class="chk" style="margin-top:10px"><input type="checkbox" data-act="kioskPhoto" ${st.kioskPhoto?"checked":""}> Take a photo at every sign-in and sign-out</label></div>
 <div class="card" style="margin-top:16px"><h2>Trainings &amp; fines</h2><form data-form="trainingSettings"><div class="fg" style="margin-top:12px">
 <label class="f full">Training types (one per line)<textarea name="types" style="min-height:120px">${esc(st.trainingTypes.join("\n"))}</textarea></label>
 <label class="f">Fine applies to<select name="ftype">${st.trainingTypes.map(t=>`<option ${rule.type===t?"selected":""}>${esc(t)}</option>`).join("")}</select></label>
 <label class="f">Fine amount (₦)<input type="number" min="0" name="amount" value="${rule.amount}"></label>
 <label class="f">Fined if absent more than (times a month)<input type="number" min="0" name="threshold" value="${rule.threshold}"></label></div>
 <p class="small muted" style="margin:14px 0 6px">Ranks that can be fined</p><div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:6px">${ranks().map(r=>`<label class="chk"><input type="checkbox" name="frank" value="${esc(r)}" ${rule.ranks.includes(r)?"checked":""}> ${esc(r)}</label>`).join("")}</div>
 <div class="row" style="margin-top:12px"><button class="btn pri">Save trainings &amp; fines</button></div></form></div>
${(()=>{const a=st.alerts||{};return`<div class="card" style="margin-top:16px"><h2>Alerts by WhatsApp &amp; email</h2>
 <p class="small muted">The app sends these straight from your site: <b>late sign-ins</b>, <b>new members</b>, and a <b>daily report</b> each morning (absent 3 days, birthdays, missed sign-outs).</p>
 <form data-form="alerts"><div class="fg" style="margin-top:12px">
  <label class="f full">Email alerts to (separate with commas)<input name="emails" value="${esc(a.emails||"")}" placeholder="you@gmail.com, leader@gmail.com"></label>
  <label class="f">Send from (an email on your domain)<input name="from" type="email" value="${esc(a.from||"")}" placeholder="alerts@petronixtechnology.com"></label>
  <label class="f full">WhatsApp numbers (one per line, e.g. 08012345678)<textarea name="waNumbers" style="min-height:80px" placeholder="08012345678&#10;08098765432">${esc(a.waNumbers||"")}</textarea></label>
  <label class="f">WhatsApp phone number ID<input name="waPhoneId" value="${esc(a.waPhoneId||"")}" inputmode="numeric" placeholder="from Meta → API Setup"></label>
  <label class="f">WhatsApp access token<input name="waToken" type="password" autocomplete="off" placeholder="${a.hasToken?"Saved — leave blank to keep":"Paste token (starts with EAA…)"}"></label>
  <label class="f">Template name<input name="waTemplate" value="${esc(a.waTemplate||"team_alert")}"></label>
  <label class="f">Template language<input name="waLang" value="${esc(a.waLang||"en")}"></label></div>
 <p class="small muted" style="margin:14px 0 6px">Send these alerts</p>
 <div class="row"><label class="chk"><input type="checkbox" name="onLate" ${a.onLate!==false?"checked":""}> Late sign-ins</label><label class="chk"><input type="checkbox" name="onNewMember" ${a.onNewMember!==false?"checked":""}> New members</label><label class="chk"><input type="checkbox" name="onDigest" ${a.onDigest!==false?"checked":""}> Daily report</label></div>
 <div class="row" style="margin-top:14px"><button class="btn pri">Save alerts</button><button type="button" class="btn" data-act="testHook">Send a test</button>${a.hasToken?'<button type="button" class="btn danger" data-act="clearToken">Remove WhatsApp token</button>':""}</div></form>
 <div id="alertResult" style="margin-top:10px"></div>
 <details style="margin-top:14px"><summary class="small muted" style="cursor:pointer">Advanced: also send to an n8n/Make webhook (optional)</summary>
 <form data-form="webhook" class="row" style="margin-top:10px"><input name="url" type="url" placeholder="https://…/webhook/…" value="${esc(st.webhookUrl||"")}" style="flex:1;min-width:240px"><button class="btn">Save webhook</button></form></details></div>`})()}
 <div class="card" style="margin-top:16px"><h2>Daily report timing</h2><p class="small" style="margin:6px 0 4px">To get the report every morning, in hPanel go to <b>Advanced → Cron Jobs</b>, choose <b>Custom</b>, set it to run once a day (e.g. 7:00), and paste this command:</p>
 <textarea readonly id="cronCmd" style="font-family:monospace;min-height:56px">wget -q -O /dev/null "${esc(cronUrl)}"</textarea>
 <div class="row" style="margin-top:8px"><button class="btn sm" data-act="copyCron">Copy command</button><button class="btn sm" data-act="newCronKey">Make a new key</button></div></div>
 <div class="card" style="margin-top:16px"><h2>Backup</h2><p class="small muted">Downloads everything (offices, members, attendance, follow-ups, prospects, business numbers, trainings, finance, team and activity) as one Excel file. Keep a copy somewhere safe, e.g. once a month.</p><button class="btn pri" data-act="backup" style="margin-top:10px">Download full backup (Excel)</button></div>
 <div class="card" style="margin-top:16px"><div class="row" style="justify-content:space-between"><h2>Activity log</h2><button class="btn sm" data-act="loadAudit">${S.audit?"Refresh":"Show activity"}</button></div><p class="small muted">Every change: attendance edits, members, leaders, finance, settings — who did it and when.</p>
 ${S.audit?`<div class="tbl-wrap" style="margin-top:10px"><table><thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th></tr></thead><tbody>${S.audit.map(r=>`<tr><td class="small">${esc(r.at.slice(0,16))}</td><td>${esc(r.user)}</td><td><span class="tag">${esc(r.action.replace(/_/g," "))}</span></td><td class="small" style="white-space:normal;min-width:240px">${esc(r.detail)}${r.officeId&&officeById(r.officeId)?` <span class="muted">· ${esc(officeById(r.officeId).name)}</span>`:""}</td></tr>`).join("")||'<tr><td colspan="4" class="empty">Nothing yet.</td></tr>'}</tbody></table></div>${S.audit.length&&S.audit.length%100===0?'<button class="btn sm" data-act="moreAudit" style="margin-top:8px">Load older</button>':""}`:""}</div>`}

/* ---------- backup to Excel ---------- */
function loadScript(src){return new Promise((res,rej)=>{if(window.XLSX)return res();const s=document.createElement("script");s.src=src;s.onload=res;s.onerror=()=>rej(new Error("Couldn't load the Excel tool. Check your internet."));document.head.appendChild(s)})}
async function doBackup(){toast("Preparing backup…");const [d]=await Promise.all([api("backup_export"),loadScript("vendor/xlsx.full.min.js")]);
 const offName={};d.offices.forEach(o=>offName[o.id]=o.name);const fix=rows=>rows.map(r=>{const o=Object.assign({},r);if("office_id" in o){o.office=offName[o.office_id]||o.office_id;delete o.office_id}if("officeId" in o){o.office=offName[o.officeId]||o.officeId;delete o.officeId}return o});
 const wb=XLSX.utils.book_new();const sheets=[["Offices",d.offices],["Members",fix(d.members)],["Attendance",fix(d.attendance)],["Follow-ups",fix(d.followups)],["Prospects",fix(d.prospects)],["Business numbers",fix(d.performance)],["Trainings",fix(d.trainings)],["Finance",d.finance],["Team",fix(d.users)],["Activity",fix(d.activity)]];
 sheets.forEach(([n,rows])=>XLSX.utils.book_append_sheet(wb,XLSX.utils.json_to_sheet(rows.length?rows:[{note:"No data yet"}]),n));
 XLSX.writeFile(wb,`unstoppable-team-backup-${S.todayStr}.xlsx`);toast("Backup downloaded")}

/* ---------- modals ---------- */
function openModal(html){document.getElementById("modal").innerHTML=html;const f=document.querySelector("#modal input:not([type=hidden]):not([type=file]):not([readonly]),#modal button");if(f)f.focus()}
function closeModal(){stopCam();S.convertingProspect=null;document.getElementById("modal").innerHTML="";if(askConfirm.res){askConfirm.res(false);askConfirm.res=null}}
function askConfirm(title,msg,yes,typed){return new Promise(res=>{openModal(`<div class="scrim"><div class="modal narrow" role="dialog" aria-modal="true"><h2>${esc(title)}</h2><p>${esc(msg)}</p>${typed?'<label class="f">Type DELETE to confirm<input id="typedConfirm" autocomplete="off"></label>':""}<div class="row" style="margin-top:14px"><button class="btn pri" data-act="askYes" style="background:var(--red);border-color:var(--red)">${esc(yes||"Yes")}</button><button class="btn" data-act="close">Cancel</button></div></div></div>`);askConfirm.res=res;askConfirm.typed=!!typed})}
function accountModal(){openModal(`<div class="scrim" data-act="closeBg"><div class="modal narrow" role="dialog" aria-modal="true"><div class="mhead"><div><h2>${esc(S.me.name)}</h2><div class="small muted">${esc(S.me.email)}</div></div><button class="x" data-act="close" aria-label="Close">×</button></div>
 <form data-form="pw"><div class="grid"><label class="f">Current password<input type="password" name="current" required autocomplete="current-password"></label><label class="f">New password (8+ characters)<input type="password" name="new" required minlength="8" autocomplete="new-password"></label></div>
 <div class="row" style="margin-top:14px"><button class="btn pri">Change password</button><button type="button" class="btn" data-act="logout">Log out</button></div></form></div></div>`)}

/* ---------- CSV ---------- */
function csv(rows){return rows.map(r=>r.map(v=>{v=String(v??"");return/[",\n]/.test(v)?`"${v.replace(/"/g,'""')}"`:v}).join(",")).join("\n")}
function download(name,text){const a=document.createElement("a");a.href=URL.createObjectURL(new Blob(["\ufeff"+text],{type:"text/csv"}));a.download=name;document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},500)}

/* ---------- events ---------- */
async function run(fn){try{await fn()}catch(e){toast(e.message)}}
document.addEventListener("click",ev=>{const el=ev.target.closest("[data-act]");if(!el)return;const a=el.dataset.act;if(a==="closeBg"&&ev.target!==el)return;
 if(["photo","leaderOffice","office","mf","attDate","attMonth","finMonth","kq","mq","pq","pstatus","trMonth","pfMonth","kioskPhoto","roleSel"].includes(a))return;
 run(async()=>{const id=el.dataset.id?+el.dataset.id:null;
 if(a==="nav"){S.view=el.dataset.v;render();window.scrollTo(0,0)}
 else if(a==="retry"){S.error=null;S.loaded=false;render();boot()}
 else if(a==="close"||a==="closeBg")closeModal();
 else if(a==="askYes"){if(askConfirm.typed&&(document.getElementById("typedConfirm").value||"").trim().toUpperCase()!=="DELETE"){toast("Type DELETE to confirm.");return}const r=askConfirm.res;askConfirm.res=null;document.getElementById("modal").innerHTML="";if(r)r(true)}
 else if(a==="logout"){await api("logout");S.me=null;S.users=null;S.fin.entries=null;closeModal();render()}
 else if(a==="account")accountModal();
 else if(a==="forgot")forgotModal();
 else if(a==="backToLogin"){S.resetToken=null;history.replaceState(null,"",location.pathname);render()}
 else if(a==="logFu")followupModal(S.byId[id]);
 else if(a==="delFu"){if(!(await askConfirm("Delete this follow-up?","It will be removed from the record.","Delete")))return;await api("followup_delete",{id});await refresh(true);const m=S.byId[+el.dataset.m];if(m){openModal(profileModal(m));fillProfile(m)}toast("Follow-up deleted")}
 else if(a==="histMonth")showHistMonth(el.dataset.m);
 else if(a==="photos")await photoModal(id,S.att.date);
 else if(a==="newProspect")prospectModal(null);
 else if(a==="editProspect")prospectModal((S.prospects||[]).find(p=>p.id===id));
 else if(a==="delProspect"){const p=S.prospects.find(x=>x.id===id);if(!(await askConfirm(`Delete ${p.name}?`,"This removes the prospect and their training attendance.","Delete")))return;await api("prospect_delete",{id});await loadProspectsNow();closeModal();toast("Prospect deleted")}
 else if(a==="convertProspect"){const p=S.prospects.find(x=>x.id===id);S.convertingProspect=p.id;openModal(memberForm({fullName:p.name,phone:p.phone,officeId:p.officeId,sponsorName:p.invitedBy,joinedDate:S.todayStr}))}
 else if(a==="newTraining"){if(!S.prospects)await loadProspectsNow();trainingModal(null)}
 else if(a==="editTraining"){if(!S.prospects)await loadProspectsNow();const d=S.trCache[singleOffice()+":"+S.trMonth];trainingModal(d.trainings.find(t=>t.id===id))}
 else if(a==="delTraining"){if(!(await askConfirm("Delete this training?","Its attendance list will be removed too.","Delete")))return;await api("training_delete",{id});S.trCache={};render();toast("Training deleted")}
 else if(a==="tickAll"){document.querySelectorAll(`#modal input[name=${el.dataset.g}]`).forEach(c=>c.checked=el.dataset.v==="1")}
 else if(a==="finePaid"){await api("fine_paid",{memberId:id,month:S.trMonth,paid:el.dataset.p==="1"});S.trCache={};render();toast(el.dataset.p==="1"?"Marked as paid":"Marked as unpaid")}
 else if(a==="finTab"){S.fin.tab=el.dataset.t;S.fin.bal=null;render()}
 else if(a==="testHook"){const box=document.getElementById("alertResult");if(box)box.innerHTML='<p class="small muted">Sending…</p>';const r=(await api("alerts_test")).result;
  const em=r.email||{},wa=r.whatsapp||{};const lines=[];
  lines.push(em.skipped?'<span class="tag">Email: not set up</span>':(em.failed&&em.failed.length?`<span class="tag red">Email: ${em.sent} sent, failed for ${esc(em.failed.join(", "))}</span>`:`<span class="tag green">Email: sent to ${em.sent}</span>`));
  lines.push(wa.skipped?'<span class="tag">WhatsApp: not set up</span>':wa.error?`<span class="tag red">WhatsApp: ${esc(wa.error)}</span>`:`<span class="tag ${wa.errors&&wa.errors.length?"gold":"green"}">WhatsApp: sent to ${wa.sent}</span>`);
  if(r.webhook!==null&&r.webhook!==undefined)lines.push(`<span class="tag ${r.webhook?"green":"red"}">Webhook: ${r.webhook?"ok":"failed"}</span>`);
  if(box)box.innerHTML=`<div class="row">${lines.join("")}</div>${wa.errors&&wa.errors.length?`<p class="small" style="color:var(--red);margin-top:8px">${wa.errors.map(esc).join("<br>")}</p>`:""}<p class="small muted" style="margin-top:6px">Email can take a few minutes. Check spam the first time.</p>`}
 else if(a==="clearToken"){if(!(await askConfirm("Remove the WhatsApp token?","WhatsApp alerts will stop until you paste a new one.","Remove")))return;await api("settings_save",{alerts:Object.assign({},S.settings.alerts,{clearToken:true,waToken:""})});await refresh();toast("Token removed")}
 else if(a==="copyCron"){const t=document.getElementById("cronCmd");t.select();try{await navigator.clipboard.writeText(t.value)}catch(e){document.execCommand("copy")}toast("Copied")}
 else if(a==="newCronKey"){if(!(await askConfirm("Make a new key?","The old cron command stops working. You'll need to paste the new one in hPanel.","Make new key")))return;await api("settings_save",{newCronKey:true});await refresh();toast("New key made. Update the cron job.")}
 else if(a==="backup")await doBackup();
 else if(a==="loadAudit"){const r=await api("audit_list");S.audit=r.rows;render()}
 else if(a==="moreAudit"){const r=await api("audit_list",{before:S.audit[S.audit.length-1].id});S.audit=S.audit.concat(r.rows);render()}
 else if(a==="kioskTap"){openModal(kioskModal(S.byId[id]));if(S.settings.kioskPhoto)startCam()}
 else if(a==="openSession"){await api("session_open",{officeId:+el.dataset.o,date:el.dataset.d});invalidate();await refresh();toast("Session opened")}
 else if(a==="attMode"){S.att.mode=el.dataset.m;render()}
 else if(a==="editRec")openModal(recModal(S.byId[id],S.att.date));
 else if(a==="clearRec"){const m=S.byId[id],d=el.dataset.d;if(!(await askConfirm("Clear this record?",`${m.fullName}'s record for ${fmtDate(d)} will be removed.`,"Clear")))return;await api("att_clear",{memberId:id,date:d});invalidate();await refresh();toast("Record cleared")}
 else if(a==="profile"){const m=S.byId[id];if(!m)return;openModal(profileModal(m));fillProfile(m)}
 else if(a==="lvl"){S.memFilter.level=el.dataset.v;S.memFilter.status="active";S.view="members";render();window.scrollTo(0,0)}
 else if(a==="newMember")openModal(memberForm(null));
 else if(a==="editMember")openModal(memberForm(S.byId[id]));
 else if(a==="delMember"){const m=S.byId[id];if(!(await askConfirm(`Delete ${m.fullName}?`,"This permanently removes the member. To keep their record but stop tracking them, mark them inactive instead.","Delete")))return;await api("member_delete",{id});await refresh();toast("Member deleted")}
 else if(a==="delTx"){if(!(await askConfirm("Delete this entry?","It will be removed from the ledger.","Delete")))return;await api("finance_delete",{id});S.fin.entries=null;render();toast("Entry deleted")}
 else if(a==="delOffice"){const o=officeById(id);const n=S.members.filter(m=>m.officeId===id).length;if(!(await askConfirm(`Delete ${o.name}?`,`This permanently removes the office${n?`, its ${n} member record${n===1?"":"s"}`:""} and all its attendance. It can't be undone.`,"Delete office",true)))return;await api("office_delete",{id,confirm:"DELETE"});S.users=null;invalidate();await refresh();toast(`${o.name} deleted`)}
 else if(a==="resetPw"){const u=S.users.find(x=>x.id===id);const pw=genPw();if(!(await askConfirm(`Reset ${u.name}'s password?`,"Their old password stops working straight away. You'll get the new one to send them.","Reset")))return;await api("user_update",{id,password:pw});loginCard(u.name,u.email,pw)}
 else if(a==="toggleLeader"){const u=S.users.find(x=>x.id===id);await api("user_update",{id,active:!u.active});S.users=null;render();toast(u.active?`${u.name} paused`:`${u.name} can log in again`)}
 else if(a==="delLeader"){const u=S.users.find(x=>x.id===id);if(!(await askConfirm(`Remove ${u.name}?`,"They lose access straight away. Their office's members and attendance stay safe.","Remove leader")))return;await api("user_delete",{id});S.users=null;render();toast(`${u.name} removed`)}
 else if(a==="copyLogin"){const t=document.getElementById("loginTxt");t.select();try{await navigator.clipboard.writeText(t.value)}catch(e){document.execCommand("copy")}toast("Copied")}
 else if(a==="exportMembers"){const ms=visibleMembers();download("unstoppable-team-members.csv",csv([["Code","Full name","Gender","Date of birth","Age","Phone","Email","Address","Guardian name","Guardian relationship","Guardian phone","Guardian email","Office","Joined","NeoLife ID","Sponsor","Sponsor phone","Upline","Stage","Rank","Tools","Occupation before","Reason for joining","Joined before","Why quit","Status"],...ms.map(m=>[m.code,m.fullName,m.gender,m.dob,age(m.dob),m.phone,m.email,m.address,m.gName,m.gRel,m.gPhone,m.gEmail,(officeById(m.officeId)||{}).name,m.joinedDate,m.neolifeId,sponsorName(m),sponsorPhone(m),uplineName(m),m.stage,m.rank,(m.tools||[]).join(" & "),m.occupation,m.why,m.prevJoined,m.whyQuit,m.status])]))}
 else if(a==="exportAtt"){const o=officeById(singleOffice()),days=monthDays(o.id,S.att.month);if(days==="loading")return;const {rows}=monthSummary(o,days,S.att.month);const detail=[];
  Object.keys(days).sort().forEach(d=>{Object.values((days[d][o.id]||{records:{}}).records).forEach(r=>{const m=S.byId[r.memberId];detail.push([d,m?m.fullName:"Removed member",hm(r.in),hm(r.out),hours(r)?hours(r).toFixed(2):"",recStatus(r,o),r.note||""])})});
  download(`attendance-${o.code||"office"}-${S.att.month}.csv`,csv([["Member","Attendance %","Present","Late","Absent","Excused","Avg hours"],...rows.map(r=>[r.m.fullName,r.pct,r.p,r.l,r.a,r.e,r.avg?r.avg.toFixed(2):""]),[],["Date","Member","Signed in","Signed out","Hours","Status","Note"],...detail]))}
 else if(a==="exportFin"){download(`finance-${S.fin.month}.csv`,csv([["Date","Member","Type","Currency","Amount","Note"],...S.fin.entries.list.map(e=>[e.date,e.memberName,(TX.find(t=>t.k===e.type)||{}).l,e.currency,e.amount,e.note])]))}
 })});
document.addEventListener("input",ev=>{const el=ev.target,a=el.dataset.act;if(a==="kq"){S.kq=el.value;render()}else if(a==="mq"){S.q=el.value;render()}else if(a==="pq"){S.pf.q=el.value;render()}});
document.addEventListener("change",ev=>{const el=ev.target,a=el.dataset.act;
 if(a==="office"){S.office=el.value==="all"?"all":+el.value;render()}
 else if(a==="mf"){S.memFilter[el.dataset.k]=el.value;render()}
 else if(a==="pstatus"){S.pf.status=el.value;render()}
 else if(a==="trMonth"){S.trMonth=el.value||S.todayStr.slice(0,7);render()}
 else if(a==="pfMonth"){S.pfMonth=el.value||S.todayStr.slice(0,7);render()}
 else if(a==="roleSel"){document.getElementById("roleOffice").style.display=el.value==="admin"?"none":""}
 else if(a==="kioskPhoto"){run(async()=>{await api("settings_save",{kioskPhoto:el.checked});await refresh();toast(el.checked?"Check-in photos on":"Check-in photos off")})}
 else if(a==="attDate"){S.att.date=el.value||S.todayStr;render()}
 else if(a==="attMonth"){S.att.month=el.value||S.todayStr.slice(0,7);render()}
 else if(a==="finMonth"){S.fin.month=el.value||S.todayStr.slice(0,7);render()}
 else if(a==="leaderOffice"&&el.dataset.id){run(async()=>{await api("user_update",{id:+el.dataset.id,officeId:+el.value});S.users=null;render();toast("Office changed")})}
 else if(a==="photo"){const f=el.files&&el.files[0];if(!f)return;shrink(f).then(url=>{document.getElementById("photoVal").value=url;document.getElementById("photoPrev").innerHTML=`<img class="av lg" src="${esc(url)}" alt="">`}).catch(()=>toast("Couldn't read that image."))}
});
function shrink(file){return new Promise((res,rej)=>{const img=new Image();img.onload=()=>{const s=240,c=document.createElement("canvas");c.width=c.height=s;const x=c.getContext("2d"),m=Math.min(img.width,img.height);x.drawImage(img,(img.width-m)/2,(img.height-m)/2,m,m,0,0,s,s);URL.revokeObjectURL(img.src);res(c.toDataURL("image/jpeg",.78))};img.onerror=rej;img.src=URL.createObjectURL(file)})}
document.addEventListener("keydown",ev=>{if(ev.key==="Escape"&&document.getElementById("modal").innerHTML)closeModal()});
document.addEventListener("submit",ev=>{const form=ev.target,kind=form.dataset.form;if(!kind)return;ev.preventDefault();
 const fd=new FormData(form),g=k=>(fd.get(k)||"").toString().trim();const btn=form.querySelector("button:not([type=button])");if(btn)btn.disabled=true;
 run(async()=>{try{
 if(kind==="login"){const j=await api("login",{email:g("email"),password:fd.get("password")});S.me=j.user;S.view="dashboard";S.office="all";await refresh()}
 else if(kind==="forgot"){await api("forgot_password",{email:g("email")});closeModal();toast("If that email is registered, a reset link is on its way.")}
 else if(kind==="reset"){if(fd.get("new")!==fd.get("again")){toast("The two passwords don't match.");return}await api("reset_password",{token:S.resetToken,new:fd.get("new")});S.resetToken=null;history.replaceState(null,"",location.pathname);render();toast("Password changed. Log in with your new password.")}
 else if(kind==="followup"){const id=+form.dataset.id;await api("followup_add",{memberId:id,date:g("date"),method:g("method"),outcome:g("outcome"),nextStep:g("nextStep")});await refresh(true);const m=S.byId[id];openModal(profileModal(m));fillProfile(m);render();toast("Follow-up saved")}
 else if(kind==="prospect"){await api("prospect_save",{prospect:{id:+form.dataset.id||0,name:g("name"),phone:g("phone"),officeId:+g("officeId"),invitedBy:g("invitedBy"),source:g("source"),firstContact:g("firstContact"),status:g("status"),notes:g("notes"),memberId:(S.prospects.find(p=>p.id===+form.dataset.id)||{}).memberId||null}});await loadProspectsNow();closeModal();toast("Prospect saved")}
 else if(kind==="training"){const sel=n=>[...form.querySelectorAll(`input[name=${n}]:checked`)].map(c=>+c.value);await api("training_save",{id:+form.dataset.id||0,officeId:singleOffice(),date:g("date"),type:g("type"),title:g("title"),members:sel("tm"),prospects:sel("tp")});S.trCache={};S.prospects=null;closeModal();render();toast("Training saved")}
 else if(kind==="perf"){const oid=singleOffice(),rows=S.members.filter(m=>m.officeId===oid&&fd.has("pv_"+m.id)).map(m=>({memberId:m.id,pv:+g("pv_"+m.id)||0,targetPv:+g("tg_"+m.id)||0,bv:+g("bv_"+m.id)||0,sales:+g("sl_"+m.id)||0,note:g("nt_"+m.id)}));await api("perf_save",{officeId:oid,month:S.pfMonth,rows});S.pfCache={};render();toast("Numbers saved")}
 else if(kind==="trainingSettings"){const types=g("types").split("\n").map(x=>x.trim()).filter(Boolean);await api("settings_save",{trainingTypes:types,fineRule:{amount:+g("amount")||0,type:g("ftype"),threshold:+g("threshold")||0,ranks:fd.getAll("frank")}});await refresh();S.trCache={};toast("Saved")}
 else if(kind==="alerts"){await api("settings_save",{alerts:{emails:g("emails"),from:g("from"),waNumbers:fd.get("waNumbers")||"",waPhoneId:g("waPhoneId"),waToken:g("waToken"),waTemplate:g("waTemplate"),waLang:g("waLang"),onLate:!!fd.get("onLate"),onNewMember:!!fd.get("onNewMember"),onDigest:!!fd.get("onDigest")}});await refresh();toast("Alert settings saved")}
 else if(kind==="webhook"){await api("settings_save",{webhookUrl:g("url")});await refresh();toast("Webhook saved")}
 else if(kind==="pw"){await api("password_change",{current:fd.get("current"),new:fd.get("new")});closeModal();toast("Password changed")}
 else if(kind==="kiosk"){const m=S.byId[+form.dataset.id];const j=await api("att_sign",{memberId:m.id,pin:g("pin"),kind:form.dataset.a,photo:S.settings.kioskPhoto?grabPhoto():""});closeModal();S.kq="";await refresh();
  toast(form.dataset.a==="in"?`Welcome, ${m.fullName.split(" ")[0]}. Signed in at ${hm(j.record.in)}`:`Goodbye, ${m.fullName.split(" ")[0]}. Signed out at ${hm(j.record.out)}`)}
 else if(kind==="rec"){await api("att_edit",{memberId:+form.dataset.id,date:form.dataset.d,in:g("in"),out:g("out"),excused:!!fd.get("excused"),note:g("note")});closeModal();invalidate();await refresh();toast("Saved")}
 else if(kind==="member"){const id=+form.dataset.id||0;const keys=["fullName","gender","dob","phone","email","address","photo","gName","gRel","gPhone","gEmail","officeId","joinedDate","neolifeId","sponsorId","sponsorName","sponsorPhone","uplineId","uplineName","stage","rank","toolsDate","status","pin","occupation","why","prevJoined","prevWhen","whyQuit","notes"];
  const m={id};keys.forEach(k=>m[k]=g(k));m.officeId=+m.officeId;m.tools=[fd.get("toolPhone")&&"Phone",fd.get("toolLaptop")&&"Laptop"].filter(Boolean);m.consent=!!fd.get("consent");
  m.sponsorId="";m.uplineId="";if(m.prevJoined!=="yes")m.whyQuit="";
  const conv=S.convertingProspect;const saved=await api("member_save",{member:m});closeModal();
  if(!id&&conv){const p=(S.prospects||[]).find(x=>x.id===conv);if(p)await api("prospect_save",{prospect:Object.assign({},p,{status:"joined",memberId:saved.member.id})});S.convertingProspect=null;S.prospects=null}
  await refresh();toast(id?"Changes saved":"Member added")}
 else if(kind==="fin"){await api("finance_add",{date:g("date"),memberId:+g("memberId")||null,type:g("type"),currency:g("currency"),amount:parseFloat(g("amount")),note:g("note")});form.reset();S.fin.entries=null;render();toast("Entry added")}
 else if(kind==="offices"){await api("offices_save",{offices:S.offices.map(o=>({id:o.id,name:g("name_"+o.id),code:g("code_"+o.id),lateAfter:g("late_"+o.id),closeAt:g("close_"+o.id)}))});await refresh();toast("Offices saved")}
 else if(kind==="addOffice"){await api("office_add",{name:g("name")});await refresh();toast("Office added")}
 else if(kind==="ranks"){await api("ranks_save",{ranks:g("ranks").split("\n").map(s=>s.trim()).filter(Boolean)});await refresh();toast("Ranks saved")}
 else if(kind==="leader"){const name=g("name"),email=g("email"),pw=g("password");await api("user_create",{name,email,password:pw,officeId:+g("officeId"),role:g("role")});S.users=null;S.newPw=null;render();loginCard(name,email,pw)}
 }finally{if(btn&&btn.isConnected)btn.disabled=false}})});
boot();
