{{-- A single global, accessible notification API shared by all Gomovie layouts. --}}
<style>
#gm-feedback-stack{position:fixed;top:18px;right:20px;z-index:99990;display:flex;flex-direction:column;align-items:flex-end;gap:10px;max-width:calc(100vw - 24px);pointer-events:none}
.gm-feedback-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:12px;width:min(410px,calc(100vw - 24px));padding:14px 14px 14px 15px;border:1px solid #365b4a;border-radius:13px;color:#e7fff1;background:#132b21;box-shadow:0 14px 40px #0008;font:550 13px/1.55 Inter,ui-sans-serif,system-ui,sans-serif;animation:gm-feedback-in .2s ease-out;overflow-wrap:anywhere}
.gm-feedback-toast[data-type="error"]{border-color:#854355;background:#321b26;color:#ffe1e7}
.gm-feedback-toast[data-type="warning"]{border-color:#85703f;background:#322a1b;color:#fff1c9}
.gm-feedback-toast[data-type="info"]{border-color:#3d6687;background:#172a3a;color:#dff1ff}
.gm-feedback-icon{flex:none;display:grid;place-items:center;width:22px;height:22px;border-radius:50%;background:#2c9e66;color:white;font-size:13px;font-weight:900;margin-top:1px}
.gm-feedback-toast[data-type="error"] .gm-feedback-icon{background:#c44465}
.gm-feedback-toast[data-type="warning"] .gm-feedback-icon{background:#b98e39}
.gm-feedback-toast[data-type="info"] .gm-feedback-icon{background:#3885b1}
.gm-feedback-copy{flex:1;min-width:0;white-space:pre-line}
.gm-feedback-close{flex:none;width:26px;height:26px;border:0;border-radius:7px;background:transparent;color:inherit;font:600 20px/1 system-ui,sans-serif;cursor:pointer;opacity:.8}
.gm-feedback-close:hover{background:#ffffff1b;opacity:1}
.gm-feedback-close:focus-visible{outline:2px solid currentColor;outline-offset:2px}
@keyframes gm-feedback-in{from{opacity:0;transform:translateY(-9px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:640px){#gm-feedback-stack{top:10px;right:10px}.gm-feedback-toast{padding:12px}}
@media(prefers-reduced-motion:reduce){.gm-feedback-toast{animation:none}}
</style>
@if(session('status'))
<meta name="gm-feedback-success" content="{{ session('status') }}">
@endif
@if(session('error'))
<meta name="gm-feedback-error" content="{{ session('error') }}">
@endif
@if($errors->any())
<meta name="gm-feedback-validation" content="{{ $errors->first() }}">
@endif
<script>
(function () {
  'use strict';
  if (window.GomovieFeedback) return;
  function stack() {
    if (!document.body) return null;
    var node = document.getElementById('gm-feedback-stack');
    if (!node) {
      node = document.createElement('div');
      node.id = 'gm-feedback-stack';
      node.setAttribute('aria-label', 'Notifications');
      document.body.appendChild(node);
    }
    return node;
  }
  function show(message, type, duration) {
    var text = String(message == null ? '' : message).trim();
    if (!text) return null;
    if (!document.body) {
      document.addEventListener('DOMContentLoaded', function () { show(text, type, duration); }, { once: true });
      return null;
    }
    var kind = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'success';
    var parent = stack();
    var toast = document.createElement('div');
    toast.className = 'gm-feedback-toast';
    toast.dataset.type = kind;
    toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-live', kind === 'error' ? 'assertive' : 'polite');
    var icon = document.createElement('span');
    icon.className = 'gm-feedback-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = kind === 'success' ? '✓' : kind === 'error' ? '!' : kind === 'warning' ? '!' : 'i';
    var copy = document.createElement('span');
    copy.className = 'gm-feedback-copy';
    copy.textContent = text;
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'gm-feedback-close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '×';
    close.addEventListener('click', function () { toast.remove(); });
    toast.append(icon, copy, close);
    parent.prepend(toast);
    while (parent.children.length > 3) parent.lastElementChild.remove();
    var timeout = Number.isFinite(duration) && duration >= 0 ? duration : 5000;
    if (timeout) {
      var timer = window.setTimeout(function () { toast.remove(); }, timeout);
      // Always dismiss after five seconds, including when hovered.
    }
    return toast;
  }
  window.GomovieFeedback = Object.freeze({
    show: show,
    success: function (msg) { return show(msg, 'success'); },
    error: function (msg) { return show(msg, 'error'); },
    warning: function (msg) { return show(msg, 'warning'); },
    info: function (msg) { return show(msg, 'info'); }
  });
  function showFlash() {
    [['gm-feedback-success', 'success'], ['gm-feedback-error', 'error'], ['gm-feedback-validation', 'error']].forEach(function (pair) {
      var node = document.querySelector('meta[name="' + pair[0] + '"]');
      if (node && node.content) show(node.content, pair[1]);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showFlash, { once: true });
  } else {
    showFlash();
  }
})();
</script>
