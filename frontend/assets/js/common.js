/* ============================================================
   FreshTrack — small shared UI helpers (ported from the old app.js).
   ============================================================ */

/** window.confirm() wrapper so cancel/remove buttons read consistently. */
function confirmAction(message) {
  return window.confirm(message || 'Are you sure?');
}

/** Wires a live "(n/min)" character-count hint under a password field with minlength + a sibling .hint. */
function wirePasswordHint(input) {
  const hint = input.parentElement.querySelector('.hint');
  if (!hint) return;
  const base = hint.textContent;
  input.addEventListener('input', () => {
    const min = Number(input.getAttribute('minlength'));
    hint.textContent = input.value.length >= min ? base : `${base} (${input.value.length}/${min})`;
  });
}

/** Shows/hides a `.form-msg` element, auto-dismissing success messages after a few seconds (mirrors the old auto-dismiss behaviour). */
function showFormMessage(el, message, type = 'success') {
  if (!message) {
    el.classList.remove('show');
    return;
  }
  el.textContent = message;
  el.className = `form-msg ${type} show`;
  if (type === 'success') {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
    }, 4000);
  }
}

/** Bottom-right toast, used for live-update notifications. */
function showToast(message, type = 'info') {
  const existing = document.getElementById('live-update-toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'live-update-toast';
  toast.textContent = message;
  toast.style.position = 'fixed';
  toast.style.bottom = '20px';
  toast.style.right = '20px';
  toast.style.color = '#fff';
  toast.style.padding = '12px 20px';
  toast.style.borderRadius = '4px';
  toast.style.zIndex = '1000';
  toast.style.fontSize = '14px';
  toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
  toast.style.opacity = '0';
  toast.style.transition = 'opacity 0.3s ease';
  toast.style.backgroundColor = { success: '#28a745', error: '#dc3545', info: '#17a2b8' }[type] || '#333';

  document.body.appendChild(toast);
  void toast.offsetWidth; // trigger reflow so the transition runs
  toast.style.opacity = '0.9';

  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
