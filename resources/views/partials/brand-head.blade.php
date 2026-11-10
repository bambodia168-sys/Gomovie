{{-- Centralized Go Movie favicon and light/dark branding. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v=20261110" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=20261110">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=20261110">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=20261110">
<style>
.gm-brand-assets{display:inline-flex;align-items:center;justify-content:flex-start;flex:0 0 auto;vertical-align:middle}
.gm-brand-logo{display:block;width:155px;max-width:100%;height:auto;object-fit:contain}
.gm-brand-logo--dark{display:none}
html.dark .gm-brand-logo--light{display:none}
html.dark .gm-brand-logo--dark{display:block}
.gm-brand-small .gm-brand-logo{width:122px}
.gm-studio-brand .gm-brand-assets{display:inline-flex}
.gm-studio-brand .gm-brand-logo{width:152px}
@media (max-width:640px){.gm-site-header .gm-brand-logo{width:125px}.gm-auth-topbar .gm-brand-logo{width:137px}}
</style>

{{-- Auth-only compatibility: render the approved favicon over the original
     red play badge in customized authentication templates, without modifying
     login, registration, or reset form markup. --}}
<style id="gomovie-auth-favicon-card">
body.gm-auth-body .gm-auth-card-mark:not(.gm-auth-card-mark--favicon),
body.gm-auth-body .gm-auth-card .gm-brand-mark {
  background:transparent url('{{ asset('apple-touch-icon.png') }}?v=20261110') center / contain no-repeat !important;
  box-shadow:none !important;
  color:transparent !important;
  font-size:0 !important;
  text-shadow:none !important;
  padding:0 !important;
  transform:none !important;
  border:0 !important;
}
body.gm-auth-body .gm-auth-card-mark:not(.gm-auth-card-mark--favicon) > *,
body.gm-auth-body .gm-auth-card .gm-brand-mark > * {visibility:hidden !important;}
</style>
