{{-- Shared compact workspace header, persistent desktop sidebar and mobile navigation.
     Scoped to authenticated Studio/Account pages; public frontend remains unchanged. --}}
<style id="gomovie-workspace-ui">
.gm-workspace-shell{min-height:100vh;background:var(--studio-bg,#0b0c11);color:#f5f6f9}
.gm-workspace-shell .gm-workspace-frame{display:flex;align-items:stretch;min-height:100vh;width:100%;min-width:0}
.gm-workspace-shell .gm-workspace-main{flex:1 1 0;min-width:0;max-width:100%}
.gm-workspace-shell .gm-workspace-sidebar{width:252px;flex:0 0 252px;min-width:0;position:sticky;top:0;height:100vh;overflow-x:hidden;overflow-y:auto;transition:width .18s ease,flex-basis .18s ease;background:#111218;z-index:65}
.gm-workspace-shell .gm-workspace-brand{height:89px;flex:0 0 auto;padding:14px 23px!important;display:flex!important;align-items:center;justify-content:flex-start;overflow:hidden}
.gm-workspace-shell .gm-workspace-brand-expanded{display:block;min-width:0}
.gm-workspace-shell .gm-workspace-brand-expanded .gm-brand-logo{max-width:175px}
.gm-workspace-shell .gm-workspace-brand-expanded small{display:block;margin-top:4px;white-space:nowrap}
.gm-workspace-shell .gm-workspace-brand-compact{display:none;align-items:center;justify-content:center}
.gm-workspace-shell .gm-workspace-brand-compact img{display:block;width:34px;height:34px;object-fit:contain}
.gm-workspace-shell .gm-workspace-sidebar-nav{padding:8px 12px 22px}
.gm-workspace-shell .gm-workspace-sidebar-nav>a{display:flex;align-items:center;gap:12px;padding:10px 12px;min-height:43px;font-size:13px;white-space:nowrap}
.gm-workspace-shell .gm-workspace-sidebar-nav>a .gm-nav-icon{width:18px;height:18px;flex:0 0 18px}
.gm-workspace-shell .gm-workspace-link-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gm-workspace-shell .gm-workspace-sidebar-identity{display:flex;align-items:center;gap:10px;padding:14px 15px;font-size:12px;overflow:hidden}
.gm-workspace-shell .gm-workspace-sidebar-avatar{width:30px;height:30px;display:grid;place-items:center;flex:0 0 30px;border-radius:50%;background:#512b45;overflow:hidden}
.gm-workspace-shell .gm-workspace-sidebar-avatar .gm-avatar-img{width:100%;height:100%;object-fit:cover}
.gm-workspace-shell .gm-workspace-sidebar-identity-label{display:flex;flex-direction:column;min-width:0;gap:2px}
.gm-workspace-shell .gm-workspace-sidebar-identity-label small{display:block;font-size:10px;color:#8e92a0}
.gm-workspace-shell .gm-workspace-sidebar-identity-label strong{display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:12px;color:#f5f4f8}
.gm-workspace-shell .gm-workspace-topbar{position:sticky;top:0;z-index:90;display:grid!important;grid-template-columns:minmax(130px,1fr) minmax(170px,390px) minmax(238px,1fr);align-items:center;gap:15px;min-height:58px!important;height:58px;padding:0 22px!important;background:#111218;overflow:visible}
.gm-workspace-shell .gm-workspace-topbar h1.gm-workspace-header-title{font-size:15px;line-height:1.2;font-weight:800;letter-spacing:-.025em;color:inherit;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gm-workspace-shell .gm-workspace-header-start{min-width:0;display:flex;align-items:center;gap:12px}
.gm-workspace-shell .gm-workspace-toggle{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;flex:0 0 36px;border-radius:10px;border:1px solid #464756;background:#1e202b;color:#f5f5f9;cursor:pointer}
.gm-workspace-shell .gm-workspace-toggle:hover,.gm-workspace-shell .gm-workspace-toggle:focus-visible{border-color:#e8ad64;background:#2e2b39}
.gm-workspace-shell .gm-workspace-header-search{display:flex;align-items:center;width:100%;max-width:390px;height:36px;gap:9px;padding:0 13px;border:1px solid #3a3a4a;border-radius:999px;background:#171822;min-width:0;margin:0 auto}
.gm-workspace-shell .gm-workspace-header-search .gm-nav-icon{flex:none;width:17px;height:17px;color:#a5a6b5}
.gm-workspace-shell .gm-workspace-header-search input{width:100%;min-width:0;height:100%;font-size:12px;background:transparent;border:0;outline:0;color:#f8f7fb;box-shadow:none;padding:0}
.gm-workspace-shell .gm-workspace-header-search:focus-within{border-color:#d7a76c;box-shadow:0 0 0 2px #d8a75a19}
.gm-workspace-shell .gm-workspace-header-search input::placeholder{color:#9395a6}
.gm-workspace-shell .gm-workspace-header-end{display:flex;justify-content:flex-end;align-items:center;gap:9px;min-width:0}
.gm-workspace-shell .gm-workspace-home{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;min-width:36px;padding:0;flex:0 0 36px}
.gm-workspace-shell .gm-workspace-home .gm-nav-icon{width:18px;height:18px}
.gm-workspace-shell .gm-header-tools{gap:7px}
.gm-workspace-shell .gm-header-tools .gm-header-tool{height:36px;min-width:36px}
.gm-workspace-shell .gm-header-tools .gm-profile-tool{height:36px;width:36px}
.gm-workspace-shell .gm-workspace-content{width:min(100%,1536px);padding:26px;margin:0 auto}
.gm-workspace-shell .gm-workspace-account-content .gm-account-shell{display:block!important;min-height:0;margin:0;padding:0!important;grid-template-columns:none!important;gap:0!important}
.gm-workspace-shell .gm-workspace-account-content .gm-account-main{width:100%;min-width:0}
.gm-workspace-shell .gm-workspace-backdrop{display:none}
@media(min-width:901px){
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar{width:86px;flex-basis:86px}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-brand{height:75px;padding:13px 9px!important;justify-content:center}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-brand-expanded{display:none}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-brand-compact{display:flex}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-nav{padding:9px 8px 22px}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-nav>a{padding:9px 3px;min-height:47px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;text-align:center}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-link-label{display:none}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-nav>a.is-current .gm-workspace-link-label{display:block;max-width:74px;text-overflow:clip;white-space:normal;overflow-wrap:break-word;font-size:10px;line-height:1.2;font-weight:750}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-nav>a .gm-nav-icon{height:19px;width:19px;flex:0 0 19px}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-nav .gm-studio-nav-heading{height:1px;padding:0;margin:13px 7px 9px;background:#ffffff18;color:transparent;overflow:hidden;font-size:0;line-height:0}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-identity{justify-content:center;padding:15px 8px}
 .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-identity-label{display:none}
}
@media(max-width:1120px){
 .gm-workspace-shell .gm-workspace-topbar{grid-template-columns:minmax(90px,1fr) minmax(145px,300px) auto;gap:10px;padding:0 14px!important}
 .gm-workspace-shell .gm-workspace-header-start{gap:8px}
 .gm-workspace-shell .gm-workspace-home{width:33px;min-width:33px}
}
@media(max-width:900px){
 .gm-workspace-shell .gm-workspace-sidebar{display:flex!important;position:fixed!important;top:0;left:0;height:100dvh;width:min(268px,calc(100vw - 55px))!important;flex:0 0 auto!important;transform:translateX(-103%);transition:transform .2s ease;box-shadow:none;z-index:121}
 .gm-workspace-shell.is-sidebar-open .gm-workspace-sidebar{transform:translateX(0);box-shadow:15px 0 65px #000b}
 .gm-workspace-shell .gm-workspace-backdrop{position:fixed;inset:0;background:#04040aba;border:0;z-index:115;cursor:pointer}
 .gm-workspace-shell.is-sidebar-open .gm-workspace-backdrop:not([hidden]){display:block}
 .gm-workspace-shell .gm-workspace-topbar{grid-template-columns:minmax(38px,1fr) minmax(115px,360px) auto;padding:0 12px!important;min-height:56px!important;height:56px;gap:8px}
 .gm-workspace-shell .gm-workspace-header-title{display:none}
 .gm-workspace-shell .gm-workspace-content{padding:20px 16px}
}
@media(max-width:580px){
 .gm-workspace-shell .gm-workspace-topbar{gap:6px;grid-template-columns:38px minmax(80px,1fr) auto;padding:0 9px!important}
 .gm-workspace-shell .gm-workspace-toggle{width:33px;height:33px;flex-basis:33px}
 .gm-workspace-shell .gm-workspace-header-end{gap:5px}
 .gm-workspace-shell .gm-workspace-home{width:32px;height:32px;min-width:32px}
 .gm-workspace-shell .gm-header-tools{gap:4px}
 .gm-workspace-shell .gm-header-tools .gm-header-tool{width:32px;height:32px;min-width:32px;padding:2px}
 .gm-workspace-shell .gm-workspace-header-search{height:34px;padding:0 9px;gap:5px}
 .gm-workspace-shell .gm-workspace-header-search input{font-size:12px}
 .gm-workspace-shell .gm-workspace-content{padding:16px 12px}
}
html.light .gm-workspace-shell .gm-workspace-toggle,
html.light .gm-workspace-shell .gm-workspace-header-search{background:#f7f8fb;color:#273145;border-color:#d9dce6}
html.light .gm-workspace-shell .gm-workspace-header-search input{color:#273145}
html.light .gm-workspace-shell .gm-workspace-header-search input::placeholder{color:#7c8394}
html.light .gm-workspace-shell .gm-workspace-sidebar-identity-label strong{color:#20293a}
html.light .gm-workspace-shell .gm-workspace-sidebar-nav .gm-studio-nav-heading{color:#738092}
html.light .gm-workspace-shell.is-sidebar-collapsed .gm-workspace-sidebar-nav .gm-studio-nav-heading{background:#e4e7ee;color:transparent}
@media(prefers-reduced-motion:reduce){
 .gm-workspace-shell .gm-workspace-sidebar{transition:none!important}
}
</style>
<script id="gomovie-workspace-sidebar-controller">
(function(){
'use strict';
const setup=function(){
  document.querySelectorAll('[data-gm-workspace]').forEach(function(root){
    if(root.dataset.gmWorkspaceReady==='1')return;
    root.dataset.gmWorkspaceReady='1';
    const sidebar=root.querySelector('[data-gm-sidebar]');
    const toggle=root.querySelector('[data-gm-sidebar-toggle]');
    const backdrop=root.querySelector('[data-gm-sidebar-backdrop]');
    if(!sidebar||!toggle||!backdrop)return;
    const query=window.matchMedia('(max-width:900px)');
    const key='gomovie.workspace.sidebar.collapsed';
    let collapsed=false, open=false;
    try{collapsed=window.localStorage.getItem(key)==='1'}catch(_){}
    const sync=function(){
      const mobile=query.matches;
      root.classList.toggle('is-sidebar-collapsed',!mobile&&collapsed);
      root.classList.toggle('is-sidebar-open',mobile&&open);
      backdrop.hidden=!(mobile&&open);
      const expanded=mobile?open:!collapsed;
      toggle.setAttribute('aria-expanded',String(expanded));
      toggle.setAttribute('aria-pressed',String(!mobile&&collapsed));
      toggle.setAttribute('aria-label',mobile?(open?'Close menu':'Open menu'):(collapsed?'Expand sidebar':'Collapse sidebar'));
      toggle.title=toggle.getAttribute('aria-label');
      sidebar.setAttribute('aria-hidden',String(mobile&&!open));
      if('inert' in sidebar)sidebar.inert=mobile&&!open;
    };
    toggle.addEventListener('click',function(){
      if(query.matches){open=!open}
      else {
        collapsed=!collapsed;
        try{window.localStorage.setItem(key,collapsed?'1':'0')}catch(_){}
      }
      sync();
    });
    backdrop.addEventListener('click',function(){open=false;sync();toggle.focus({preventScroll:true})});
    sidebar.addEventListener('click',function(event){
      if(query.matches&&event.target.closest('a[href]')){open=false;sync()}
    });
    document.addEventListener('keydown',function(event){
      if(event.key==='Escape'&&open){open=false;sync();toggle.focus({preventScroll:true})}
    });
    if(query.addEventListener)query.addEventListener('change',function(){open=false;sync()});
    else if(query.addListener)query.addListener(function(){open=false;sync()});
    sync();
  });
};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup,{once:true});
else setup();
})();
</script>
