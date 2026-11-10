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

/* Shared workspace header: borderless, background-free utility controls.
   Keep only the identity avatar circular; header popovers still have surfaces. */
.gm-workspace-shell .gm-workspace-home,
.gm-workspace-shell .gm-header-tools .gm-header-language,
.gm-workspace-shell .gm-header-tools .gm-theme-tool{
  background:transparent!important;
  border:1px solid transparent!important;
  box-shadow:none!important;
  border-radius:10px!important;
  color:#b5b7c6;
  transition:color .16s ease,background-color .16s ease;
}
.gm-workspace-shell .gm-workspace-home:hover,
.gm-workspace-shell .gm-workspace-home:focus-visible,
.gm-workspace-shell .gm-header-tools .gm-header-language:hover,
.gm-workspace-shell .gm-header-tools .gm-header-language:focus-visible,
.gm-workspace-shell .gm-header-tools .gm-header-language[aria-expanded="true"],
.gm-workspace-shell .gm-header-tools .gm-theme-tool:hover,
.gm-workspace-shell .gm-header-tools .gm-theme-tool:focus-visible{
  background:rgba(255,255,255,.055)!important;
  border-color:transparent!important;
  color:#fff!important;
}
.gm-workspace-shell .gm-header-tools .gm-header-language{padding-inline:4px!important;gap:5px}
.gm-workspace-shell .gm-header-tools .gm-theme-tool{padding:0!important}
.gm-workspace-shell .gm-workspace-home .gm-nav-icon{opacity:.95}
html.light .gm-workspace-shell .gm-workspace-home,
html.light .gm-workspace-shell .gm-header-tools .gm-header-language,
html.light .gm-workspace-shell .gm-header-tools .gm-theme-tool{
  color:#555d70!important;
  background:transparent!important;
  border-color:transparent!important;
  box-shadow:none!important;
}
html.light .gm-workspace-shell .gm-workspace-home:hover,
html.light .gm-workspace-shell .gm-workspace-home:focus-visible,
html.light .gm-workspace-shell .gm-header-tools .gm-header-language:hover,
html.light .gm-workspace-shell .gm-header-tools .gm-header-language:focus-visible,
html.light .gm-workspace-shell .gm-header-tools .gm-header-language[aria-expanded="true"],
html.light .gm-workspace-shell .gm-header-tools .gm-theme-tool:hover,
html.light .gm-workspace-shell .gm-header-tools .gm-theme-tool:focus-visible{
  background:rgba(20,26,40,.055)!important;
  color:#171b29!important;
  border-color:transparent!important;
}

/* Real-data dashboard visual hierarchy. Reusable metric component + scoped table. */
.gm-dashboard-overview{--gm-dashboard-ink:#f6f6fa;--gm-dashboard-muted:#a6a9bc;display:grid;gap:24px;min-width:0;padding-bottom:20px}
.gm-dashboard-heading{margin:0;align-items:flex-end;gap:16px}
.gm-dashboard-heading h2{font-size:clamp(27px,2.5vw,34px);line-height:1.12}
.gm-dashboard-heading>div>p:not(.gm-dashboard-eyebrow){max-width:660px;line-height:1.55}
.gm-dashboard-eyebrow{font-size:10px!important;color:#efba62!important;text-transform:uppercase;letter-spacing:.19em;font-weight:800;display:flex;align-items:center;gap:9px;margin:0 0 8px!important}
.gm-dashboard-eyebrow span{font-size:12px;color:#757786}
.gm-dashboard-heading .gm-page-actions{display:flex;align-items:center;justify-content:flex-end;gap:9px;flex-wrap:wrap}
.gm-dashboard-heading .gm-page-actions .gm-studio-button{min-height:38px;display:inline-flex;align-items:center;gap:9px}
.gm-dashboard-section{min-width:0;display:grid;gap:14px}
.gm-dashboard-section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.gm-dashboard-section-heading h3{font-size:14px;font-weight:850;letter-spacing:-.018em;color:var(--gm-dashboard-ink)}
.gm-dashboard-section-heading p{font-size:12px;color:var(--gm-dashboard-muted);margin-top:3px}
.gm-dashboard-live-indicator{display:inline-flex;align-items:center;gap:7px;font-size:11px;font-weight:700;color:#b4b8c7}
.gm-dashboard-live-indicator>span{width:7px;height:7px;border-radius:50%;background:#64d9ad;box-shadow:0 0 0 3px #64d9ad18}
.gm-dashboard-metric-grid{display:grid;gap:15px;min-width:0}
.gm-dashboard-metric-grid--four{grid-template-columns:repeat(4,minmax(0,1fr))}
.gm-dashboard-metric-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}
.gm-dashboard-publication{margin-top:2px}
.gm-visual-metric{
  --gm-metric-rgb:128,120,180;
  --gm-metric-accent:#b4a6e8;
  position:relative;isolation:isolate;display:flex;flex-direction:column;
  min-height:195px;min-width:0;padding:19px 20px 18px;
  border:1px solid rgba(176,180,205,.19);border-radius:18px;
  background:radial-gradient(ellipse 200px 175px at 92% 8%,rgba(var(--gm-metric-rgb),.16),transparent 76%),
             linear-gradient(152deg,#1b1c26 0%,#14151e 63%,#11131a 100%);
  overflow:hidden;color:#f5f6fb;box-shadow:inset 0 1px 0 #ffffff08,0 5px 22px #00000012;
  transition:border-color .2s ease,transform .2s ease,box-shadow .2s ease;
}
.gm-dashboard-metric-grid--three .gm-visual-metric{min-height:173px}
.gm-visual-metric:hover{border-color:rgba(var(--gm-metric-rgb),.46);transform:translateY(-2px);box-shadow:0 12px 30px #00000020}
.gm-visual-metric--violet{--gm-metric-rgb:166,116,231;--gm-metric-accent:#c4a5f5}
.gm-visual-metric--blue{--gm-metric-rgb:89,164,227;--gm-metric-accent:#95c7f4}
.gm-visual-metric--indigo{--gm-metric-rgb:125,139,224;--gm-metric-accent:#a9b7fb}
.gm-visual-metric--rose{--gm-metric-rgb:235,105,137;--gm-metric-accent:#f4a0b4}
.gm-visual-metric--teal{--gm-metric-rgb:68,198,164;--gm-metric-accent:#7ce4c3}
.gm-visual-metric--amber{--gm-metric-rgb:231,176,82;--gm-metric-accent:#ffd589}
.gm-visual-metric--slate{--gm-metric-rgb:159,166,188;--gm-metric-accent:#b8c2d4}
.gm-visual-metric-art{position:absolute;z-index:-1;right:-19px;bottom:-18px;pointer-events:none;display:grid;place-items:center;opacity:.095;transform:rotate(-13deg)}
.gm-visual-metric-art .gm-nav-icon{width:156px;height:156px;stroke-width:1.15;color:var(--gm-metric-accent);opacity:1}
.gm-visual-metric-header{display:flex;align-items:center;justify-content:space-between;gap:8px;position:relative;z-index:1}
.gm-visual-metric-icon{display:grid;place-items:center;width:39px;height:39px;border-radius:12px;background:rgba(var(--gm-metric-rgb),.12);border:1px solid rgba(var(--gm-metric-rgb),.2);color:var(--gm-metric-accent)}
.gm-visual-metric-icon .gm-nav-icon{width:20px;height:20px;opacity:1}
.gm-visual-metric-link{display:grid;place-items:center;width:30px;height:30px;border-radius:8px;color:#aab0c2;text-decoration:none;transition:background .16s,color .16s}
.gm-visual-metric-link:hover,.gm-visual-metric-link:focus-visible{background:#ffffff10;color:#fff}
.gm-visual-metric-data{position:relative;z-index:1;margin-top:auto;padding-top:18px}
.gm-visual-metric-data h3{font-size:12px;line-height:1.35;font-weight:700;color:#b5b8c8}
.gm-visual-metric-value{display:block;font-size:clamp(30px,2.45vw,40px);font-weight:875;line-height:1.07;letter-spacing:-.055em;margin-top:6px;font-variant-numeric:tabular-nums;color:#f9f9fc}
.gm-visual-metric-caption{font-size:11px;line-height:1.45;color:#979bad;margin-top:9px;max-width:95%}
.gm-visual-metric-accent{width:34px;height:2px;background:var(--gm-metric-accent);opacity:.8;border-radius:10px;margin-top:16px}
.gm-visual-metric-progress{height:4px;width:100%;background:#ffffff12;border-radius:999px;overflow:hidden;margin-top:16px}
.gm-visual-metric-progress>span{display:block;height:100%;border-radius:inherit;background:var(--gm-metric-accent);min-width:0}
.gm-dashboard-table-panel{margin-top:1px;border-color:#353743;background:linear-gradient(140deg,#1a1b25,#14151c 80%);box-shadow:0 9px 25px #00000012}
.gm-dashboard-table-header{padding:19px 21px;gap:14px}
.gm-dashboard-table-heading{display:flex;gap:12px;align-items:center;min-width:0}
.gm-dashboard-table-heading-icon{display:grid;place-items:center;flex:0 0 39px;width:39px;height:39px;border:1px solid #eaa34b26;border-radius:11px;background:#edb36213;color:#f5c77b}
.gm-dashboard-table-heading-icon .gm-nav-icon{width:20px;height:20px;opacity:1}
.gm-dashboard-table-heading h3{font-size:15px;letter-spacing:-.02em}
.gm-dashboard-table-heading small{font-size:12px;line-height:1.4}
.gm-dashboard-table-controls{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.gm-dashboard-record-count{font-size:11px;font-weight:750;color:#b1b3c2;white-space:nowrap}
.gm-dashboard-table-controls .gm-studio-button{display:inline-flex;align-items:center;gap:8px;min-height:36px}
.gm-dashboard-table-controls .gm-studio-button .gm-nav-icon{width:15px;height:15px}
.gm-dashboard-table{min-width:780px}
.gm-dashboard-table th{background:rgba(8,10,17,.46);padding:14px 17px;font-size:10px;letter-spacing:.14em;color:#9296aa}
.gm-dashboard-table td{padding:13px 17px;border-bottom:1px solid #ffffff0e}
.gm-dashboard-table tbody tr:nth-child(even){background:#ffffff02}
.gm-dashboard-table tbody tr:hover{background:#ffffff08}
.gm-dashboard-row-number{color:#9fa3b5;min-width:46px;font-size:12px}
.gm-dashboard-movie{display:flex;align-items:center;gap:11px;min-width:0}
.gm-dashboard-movie-icon{width:40px;height:44px;flex:0 0 40px;display:grid;place-items:center;border-radius:10px;background:linear-gradient(145deg,#3d3148,#262333);border:1px solid #ffffff12;color:#c7adcf}
.gm-dashboard-movie-icon .gm-nav-icon{width:19px;height:19px;opacity:1}
.gm-dashboard-movie-copy{min-width:0;display:flex;flex-direction:column;gap:4px}
.gm-dashboard-movie-title{display:block;max-width:460px;font-size:12.5px;line-height:1.4;font-weight:750;color:#f0f0f5;text-decoration:none;overflow-wrap:anywhere}
.gm-dashboard-movie-title:hover,.gm-dashboard-movie-title:focus-visible{color:#ffc985}
.gm-dashboard-movie-copy small{font-size:10.5px;line-height:1.2;color:#979aaa}
.gm-dashboard-table .gm-studio-pill{gap:8px;font-size:11px;padding:6px 10px;font-weight:750}
.gm-dashboard-pill-dot{width:6px;height:6px;border-radius:50%;background:currentColor;flex:none}
.gm-dashboard-access{display:inline-flex;align-items:center;min-height:25px;padding:4px 9px;border-radius:8px;border:1px solid #ffffff12;background:#ffffff06;font-size:11px;font-weight:750;color:#c1c5d2}
.gm-dashboard-access.is-premium{color:#f8d18b;background:#d39d3412;border-color:#eab55b33}
.gm-dashboard-update-date{font-size:11.5px;color:#b0b3c2;white-space:nowrap}
.gm-dashboard-table .gm-studio-dots{width:33px;height:33px;border-radius:9px;box-shadow:none}
.gm-dashboard-table-panel .gm-studio-pager{background:#12131b;border-top:1px solid #ffffff12}

/* Light mode supports the same visual hierarchy with readable contrast. */
html.light .gm-dashboard-overview{--gm-dashboard-ink:#232a39;--gm-dashboard-muted:#6a7285}
html.light .gm-dashboard-heading h2{color:#202635}
html.light .gm-dashboard-eyebrow{color:#a87929!important}
html.light .gm-visual-metric{background:radial-gradient(ellipse 200px 180px at 92% 8%,rgba(var(--gm-metric-rgb),.13),transparent 78%),linear-gradient(148deg,#fff,#f7f8fc);border-color:#dfe2ec;box-shadow:0 6px 22px #2330440d;color:#263046}
html.light .gm-visual-metric:hover{border-color:rgba(var(--gm-metric-rgb),.55)}
html.light .gm-visual-metric-value{color:#1e2636}
html.light .gm-visual-metric-data h3{color:#555f74}
html.light .gm-visual-metric-caption{color:#747e91}
html.light .gm-visual-metric-link{color:#586275}
html.light .gm-visual-metric-link:hover{background:#e8ebf4;color:#27364c}
html.light .gm-visual-metric-progress{background:#e3e7ef}
html.light .gm-dashboard-table-panel{background:#fff;border-color:#dfe2eb;box-shadow:0 6px 24px #1928440a}
html.light .gm-dashboard-table th{background:#f5f6fa;color:#6a7385}
html.light .gm-dashboard-table td{border-bottom-color:#e9ebf1}
html.light .gm-dashboard-table tbody tr:nth-child(even){background:#fbfbfe}
html.light .gm-dashboard-table tbody tr:hover{background:#f0f3f9}
html.light .gm-dashboard-movie-icon{background:linear-gradient(150deg,#efe8f7,#e1e9fa);border-color:#e3ddec;color:#7b668d}
html.light .gm-dashboard-movie-title{color:#242b39}
html.light .gm-dashboard-movie-title:hover{color:#9b4e52}
html.light .gm-dashboard-movie-copy small{color:#737b8c}
html.light .gm-dashboard-access{background:#f2f4f8;border-color:#dfe3ed;color:#566174}
html.light .gm-dashboard-access.is-premium{background:#fff7e7;color:#946521;border-color:#ead7b0}
html.light .gm-dashboard-record-count,html.light .gm-dashboard-update-date,html.light .gm-dashboard-row-number{color:#646d7e}
html.light .gm-dashboard-table-panel .gm-studio-pager{background:#fff;border-top-color:#e5e8ef}
html.light .gm-dashboard-table-heading-icon{background:#fff7e9;color:#b47e27;border-color:#f3debd}
@media(max-width:1180px){
 .gm-dashboard-metric-grid--four{grid-template-columns:repeat(2,minmax(0,1fr))}
 .gm-dashboard-metric-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}
 .gm-visual-metric{min-height:174px}
 .gm-dashboard-metric-grid--three .gm-visual-metric{min-height:170px}
}
@media(max-width:770px){
 .gm-dashboard-metric-grid--three{grid-template-columns:repeat(2,minmax(0,1fr))}
 .gm-dashboard-heading{align-items:flex-start}
 .gm-dashboard-table-header{align-items:flex-start}
 .gm-dashboard-record-count{display:none}
}
@media(max-width:520px){
 .gm-dashboard-overview{gap:19px}
 .gm-dashboard-metric-grid{gap:10px}
 .gm-dashboard-metric-grid--four,.gm-dashboard-metric-grid--three{grid-template-columns:1fr}
 .gm-visual-metric,.gm-dashboard-metric-grid--three .gm-visual-metric{min-height:158px;padding:15px 17px}
 .gm-visual-metric-art .gm-nav-icon{width:135px;height:135px}
 .gm-dashboard-heading .gm-page-actions{width:100%;justify-content:flex-start}
 .gm-dashboard-table-header{padding:15px}
}
@media(prefers-reduced-motion:reduce){
 .gm-visual-metric{transition:none}
 .gm-visual-metric:hover{transform:none}
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
