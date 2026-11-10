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
body.gm-auth-body .gm-auth-card .gm-brand-mark,
.gm-auth-card .gm-auth-card-icon {
  background:transparent url('{{ asset('apple-touch-icon.png') }}?v=20261110') center / contain no-repeat !important;
  background-color:transparent !important;
  box-shadow:none !important;
  color:transparent !important;
  font-size:0 !important;
  text-shadow:none !important;
  padding:0 !important;
  transform:none !important;
  border:0 !important;
}
body.gm-auth-body .gm-auth-card-mark:not(.gm-auth-card-mark--favicon) > *,
body.gm-auth-body .gm-auth-card .gm-brand-mark > *,
.gm-auth-card .gm-auth-card-icon > * {visibility:hidden !important;}
.gm-auth-card .gm-auth-card-icon::before,
.gm-auth-card .gm-auth-card-icon::after {content:none !important;}
</style>

{{-- Shared, scoped fix for customized auth fields. Icon, floating label, typed
     value, browser autofill, and password toggle must have independent space.
     No form markup, saved credentials, or frontend page layout is changed. --}}
<style id="gomovie-auth-field-spacing">
.gm-auth-card .gm-auth-input {
  position:relative;
  min-width:0;
}
.gm-auth-card .gm-auth-input-icon {
  position:absolute;
  z-index:2;
  left:1rem;
  top:50%;
  width:1.25rem;
  height:1.25rem;
  display:grid;
  place-items:center;
  transform:translateY(-50%);
  pointer-events:none;
}
.gm-auth-card .gm-auth-input-control {
  min-width:0;
}
.gm-auth-card .gm-auth-input input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
.gm-auth-card input.gm-auth-input-control:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
  .gm-auth-card .gm-auth-input textarea {
  box-sizing:border-box;
  width:100%;
  min-width:0;
  min-height:3.8rem;
  padding-left:3.25rem !important;
  padding-right:3.35rem !important;
  padding-top:1.65rem !important;
  padding-bottom:.4rem !important;
  line-height:1.25 !important;
  text-indent:0 !important;
}
/* Default: empty, unfocused label centered vertically beside the SVG. */
.gm-auth-card .gm-auth-input label {
  position:absolute;
  z-index:2;
  top:50% !important;
  left:3.25rem !important;
  margin:0 !important;
  line-height:1.15 !important;
  transform:translateY(-50%) !important;
  transform-origin:left center !important;
  pointer-events:none;
  transition:top .17s ease, transform .17s ease, font-size .17s ease, color .17s ease;
}
/* Active, manually filled, or browser-autofilled: float inside the field. */
.gm-auth-card .gm-auth-input:focus-within label,
.gm-auth-card .gm-auth-input.gm-auth-has-value label,
.gm-auth-card .gm-auth-input:has(input:-webkit-autofill) label,
.gm-auth-card .gm-auth-input:has(input:autofill) label {
  top:.48rem !important;
  transform:translateY(0) !important;
  font-size:.72rem !important;
  transform-origin:left top !important;
}
@media (prefers-reduced-motion:reduce) {
  .gm-auth-card .gm-auth-input label {transition:none !important;}
}
.gm-auth-card .gm-auth-input input:-webkit-autofill,
.gm-auth-card .gm-auth-input input:-webkit-autofill:hover,
.gm-auth-card .gm-auth-input input:-webkit-autofill:focus {
  padding-left:3.25rem !important;
  padding-right:3.35rem !important;
  padding-top:1.65rem !important;
  padding-bottom:.4rem !important;
}
@media (max-width:480px) {
  .gm-auth-card .gm-auth-input input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
  .gm-auth-card input.gm-auth-input-control:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
  .gm-auth-card .gm-auth-input textarea {
    font-size:16px;
  }
}
</style>

{{-- Never inspect or retain credentials: only track whether an input is empty.
     Needed because Chrome and password managers may restore values without a
     normal input event, and these customized fields have no placeholders. --}}
<script id="gomovie-auth-floating-labels">
(() => {
  'use strict';
  const selector = '.gm-auth-card .gm-auth-input';
  const fieldSelector = 'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), textarea';
  function sync(field) {
    if (!field) return;
    const input = field.querySelector(fieldSelector);
    if (!input) return;
    field.classList.toggle('gm-auth-has-value', input.value.length > 0);
  }
  function syncAll() {
    document.querySelectorAll(selector).forEach(sync);
  }
  function onFieldInput(event) {
    const field = event.target && event.target.closest
      ? event.target.closest(selector) : null;
    if (field) sync(field);
  }
  document.addEventListener('input', onFieldInput, true);
  document.addEventListener('change', onFieldInput, true);
  document.addEventListener('focusin', onFieldInput, true);
  document.addEventListener('DOMContentLoaded', () => {
    syncAll();
    window.requestAnimationFrame(syncAll);
    window.setTimeout(syncAll, 300);
    window.setTimeout(syncAll, 1200);
  }, {once:true});
  window.addEventListener('pageshow', syncAll);
  if (document.readyState !== 'loading') syncAll();
})();
</script>

