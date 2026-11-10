{{-- Shared Go Movie profile/language/theme styling. Existing dark layouts unchanged. --}}
<style id="gomovie-header-ui">
.gm-header-tools{display:flex;align-items:center;gap:8px;flex:none;position:relative;z-index:65}
.gm-header-popover-wrap{position:relative}
.gm-header-tool{height:38px;min-width:38px;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:0 9px;border:1px solid rgba(255,255,255,.17);border-radius:999px;background:#ffffff08;color:#edeaf2;cursor:pointer;font:inherit;transition:border-color .18s,background .18s}
.gm-header-tool:hover,.gm-header-tool[aria-expanded="true"]{background:#ffffff15;border-color:#eab96c}
.gm-profile-tool{width:39px;height:39px;padding:2px;overflow:hidden;border-color:rgba(235,83,111,.4)}
.gm-header-locale-code{font-size:11px;font-weight:800;line-height:1}
.gm-header-popover{position:absolute;right:0;top:calc(100% + 12px);width:min(310px,calc(100vw - 24px));max-height:min(72vh,640px);overflow:auto;overscroll-behavior:contain;background:#171521;border:1px solid #ffffff25;border-radius:16px;box-shadow:0 22px 65px #0008;padding:9px;z-index:150;color:#f5f2f9;text-align:left}
.gm-locale-popover{width:min(270px,calc(100vw - 24px))}
.gm-menu-title{font-size:12px;font-weight:850;color:#d9d1e1;padding:11px 12px 10px}
.gm-menu-link{display:flex!important;align-items:center;gap:10px;width:100%;min-height:40px;padding:10px 12px;border:0;border-radius:10px;background:transparent;color:#e5e0ec!important;text-decoration:none!important;cursor:pointer;font:inherit;font-size:13px!important;font-weight:650;line-height:1.25;text-align:left;white-space:normal}
.gm-menu-link:hover,.gm-menu-link:focus-visible{background:#ffffff12;color:#fff!important}
.gm-menu-link.is-selected{background:#f5c36c18;color:#ffd587!important}
.gm-menu-link.gm-menu-primary{background:#f12b4922;color:#fff!important}
.gm-menu-link.gm-menu-signout{color:#ff94a6!important}
.gm-menu-link .gm-nav-icon{width:18px;height:18px;flex:none}
.gm-menu-divider{height:1px;background:#ffffff18;margin:6px 4px}
.gm-flag{font-size:20px;line-height:1.2;width:24px;flex:none}
.gm-menu-check{margin-left:auto;color:#f5c36c}
.gm-locale-options{display:grid;gap:1px}
.gm-locale-options form,.gm-profile-popover form{margin:0}
.gm-locale-note{display:block;padding:9px 12px;color:#9b93ad;font-size:11px;line-height:1.4}
.gm-profile-summary{display:flex;gap:10px;padding:12px 9px 14px;align-items:center;min-width:0}
.gm-profile-summary-avatar{flex:none;display:grid;place-items:center;width:42px;height:42px;overflow:hidden;border-radius:50%;background:#4c354e}
.gm-profile-summary-text{min-width:0;display:flex;flex-direction:column;gap:3px}
.gm-profile-summary-text strong{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gm-profile-summary-text small{font-size:11px;color:#a8a2b5;overflow-wrap:anywhere;line-height:1.35}
.gm-avatar-img{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}
.gm-avatar-initial{display:grid;place-items:center;width:100%;height:100%;border-radius:50%;background:linear-gradient(145deg,#874b6b,#391a33);color:#fff;font-size:16px;font-weight:850}
.gm-avatar-preview{display:grid;place-items:center;width:68px;height:68px;flex:none;overflow:hidden;border-radius:50%;border:1px solid #ffffff38}
.gm-avatar-preview .gm-avatar-initial{font-size:27px}
.gm-theme-sun{display:none}
html.light .gm-theme-moon{display:none}
html.light .gm-theme-sun{display:block}
html.light{color-scheme:light;--gm-bg:#f7f8fc;--gm-surface:#fff;--gm-border:rgba(33,40,62,.15);--gm-muted:#6c7383}
html.light .gm-site{background:#f7f8fc;color:#1c2334;background-image:radial-gradient(ellipse at 0 0,#fce7ed,transparent 60%)}
html.light .gm-site-header,html.light .gm-studio-shell .gm-studio-topbar{background:#fff;color:#202334;border-color:#e5e7ef}
html.light .gm-main-nav a{color:#4b5367}
html.light .gm-main-nav a:hover,html.light .gm-main-nav a.is-active{color:#111827}
html.light .gm-header-search{background:#f5f6fa;color:#6b7484;border-color:#dce0ea}
html.light .gm-header-search input{color:#171c2a}
html.light .gm-site-footer{background:#fff;color:#5d6676;border-color:#dce0ea}
html.light .gm-site-footer a{color:#465065}
html.light .gm-header-tool{color:#202738;background:#f5f6fa;border-color:#dce0ea}
html.light .gm-header-tool:hover{background:#e9edf5;border-color:#ad93c1}
html.light .gm-header-popover{background:#fff;color:#202738;border-color:#e1e4ef;box-shadow:0 19px 55px #1b213337}
html.light .gm-menu-title{color:#41475a}
html.light .gm-menu-link{color:#30384a!important}
html.light .gm-menu-link:hover{background:#f0f2f8;color:#172033!important}
html.light .gm-menu-link.is-selected{background:#fff2d9;color:#825500!important}
html.light .gm-menu-link.gm-menu-primary{background:#fff0f2}
html.light .gm-menu-link.gm-menu-signout{color:#ab2947!important}
html.light .gm-menu-divider{background:#e6e8f0}
html.light .gm-profile-summary-text small,html.light .gm-locale-note{color:#667186}
html.light .gm-account-shell{color:#202839;--studio-bg:#f7f8fc;--studio-panel:#fff;--studio-panel2:#f8faff;--studio-line:#dce0ea;--studio-subtle:#6c7382}
html.light .gm-account-shell .gm-account-nav,html.light .gm-studio-shell .gm-studio-sidebar{background:#fff;color:#273044;border-color:#e7e9f0}
html.light .gm-account-shell .gm-studio-panel,html.light .gm-studio-shell .gm-studio-panel{background:#fff;color:#202939;border-color:#e2e6ef}
html.light .gm-studio-shell{background:#f7f8fc;color:#202939;--studio-panel:#fff;--studio-panel2:#f8faff;--studio-line:#dce0ea;--studio-subtle:#6c7382}
html.light .gm-studio-shell .gm-studio-main{background:#f7f8fc;color:#202939}
html.light .gm-account-shell .gm-account-nav a,html.light .gm-studio-shell .gm-studio-sidebar nav a{color:#505b70}
html.light .gm-account-shell .gm-account-nav a:hover,html.light .gm-studio-shell .gm-studio-sidebar nav a:hover{background:#eef1f7;color:#1b263a}
html.light .gm-account-shell .gm-account-nav a.is-current,html.light .gm-studio-shell .gm-studio-sidebar nav a.is-current{color:#8b1530;background:#fcebef}
@media(max-width:780px){
 .gm-header-tools{gap:5px}
 .gm-header-tool{height:35px;min-width:35px;padding:0 6px}
 .gm-profile-tool{width:35px;height:35px}
 .gm-header-locale-code{display:none}
 .gm-header-popover{top:calc(100% + 7px)}
}
</style>
<script id="gomovie-theme-mode">
(function(){
  'use strict';
  const root=document.documentElement;
  let chosen='dark';
  try {chosen=window.localStorage.getItem('gomovie.theme')==='light'?'light':'dark';} catch (_) {}
  function apply(value){
    root.classList.toggle('dark',value!=='light');
    root.classList.toggle('light',value==='light');
    root.dataset.gomovieTheme=value;
    document.querySelectorAll('[data-gm-theme-toggle]').forEach(function(button){
      button.setAttribute('aria-pressed',String(value==='light'));
    });
  }
  apply(chosen);
  document.addEventListener('click',function(event){
    const toggle=event.target.closest('[data-gm-theme-toggle]');
    if(!toggle)return;
    chosen=root.classList.contains('light')?'dark':'light';
    try{window.localStorage.setItem('gomovie.theme',chosen);}catch(_){}
    apply(chosen);
  });
  document.addEventListener('DOMContentLoaded',function(){apply(chosen);});
})();
</script>
