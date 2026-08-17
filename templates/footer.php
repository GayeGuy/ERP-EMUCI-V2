  </main><!-- end .page-content -->
</div><!-- end .main-wrap -->

<script>
// ===== USER MENU =====
function toggleUserMenu(e) {
  e.stopPropagation();
  const dd     = document.getElementById('user-menu-dd');
  const caret  = document.getElementById('user-chip-caret');
  const open   = dd.style.display !== 'none';
  dd.style.display    = open ? 'none' : 'block';
  caret.style.transform = open ? '' : 'rotate(180deg)';
}
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('user-menu-wrap');
  if (wrap && !wrap.contains(e.target)) {
    const dd    = document.getElementById('user-menu-dd');
    const caret = document.getElementById('user-chip-caret');
    if (dd)    dd.style.display     = 'none';
    if (caret) caret.style.transform = '';
  }
});

// ===== NOTIFICATIONS =====
function toggleNotifs() {
  document.getElementById('notif-dropdown').classList.toggle('open');
}
document.addEventListener('click', e => {
  const dd = document.getElementById('notif-dropdown');
  if (!e.target.closest('.notif-btn') && !e.target.closest('.notif-dropdown')) {
    dd.classList.remove('open');
  }
});
function readNotif(id, lien) {
  fetch('<?= APP_URL ?>/api/notifications.php', {
    method: 'POST',
    headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=mark_read&id=' + id
  }).then(() => { if (lien) window.location.href = '<?= APP_URL ?>' + lien; else refreshNotifs(); });
}
function markAllRead() {
  fetch('<?= APP_URL ?>/api/notifications.php', {
    method: 'POST',
    headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=mark_all'
  }).then(() => refreshNotifs());
}
function refreshNotifs() {
  fetch('<?= APP_URL ?>/api/notifications.php?action=get', {
    headers: {'X-Requested-With':'XMLHttpRequest'}
  }).then(r=>r.json()).then(d=>{
    if (!d.success) return;
    const count = d.data.count || 0;
    const badge = document.querySelector('.notif-count');
    const btn   = document.querySelector('.notif-btn');
    if (badge) { badge.textContent = count > 9 ? '9+' : count; badge.style.display = count > 0 ? '' : 'none'; }
    else if (count > 0 && btn) {
      const s = document.createElement('span');
      s.className = 'notif-count'; s.textContent = count > 9 ? '9+' : count;
      btn.appendChild(s);
    }
    const list = document.querySelector('.notif-list');
    if (!list) return;
    if (count === 0) { list.innerHTML = '<div class="notif-empty"><i class="ph ph-check-circle" aria-hidden="true"></i> Aucune notification</div>'; return; }
    const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    list.innerHTML = d.data.data.map(n=>`
      <div class="notif-item" data-id="${n.id}" data-lien="${esc(n.lien||'')}">
        <div class="n-titre">${esc(n.titre)}</div>
        <div class="n-date">${esc(n.created_at)}</div>
      </div>`).join('');
    list.querySelectorAll('.notif-item').forEach(el=>{
      el.addEventListener('click',()=>readNotif(el.dataset.id, el.dataset.lien));
    });
  }).catch(()=>{});
}
// Rafraîchir toutes les 60 secondes
setInterval(refreshNotifs, 60000);

// ===== RAFRAICHISSEMENT LIVE (polling léger) =====
// Remplace périodiquement le contenu d'un conteneur par un fragment HTML
// renvoyé par le serveur (même page, ?fragment=1) — pas de connexion
// persistante (l'hébergement actuel ne le permet pas), juste un appel
// espacé. S'interrompt quand l'onglet n'est pas visible ou qu'une modale
// est ouverte : on ne doit jamais remplacer l'écran sous les yeux de
// quelqu'un qui a une fenêtre ouverte ou un champ en cours de saisie.
// Usage : liveRefresh({ url, container: '#id', interval: 10000 }).
function liveRefresh(opts) {
  const container = typeof opts.container === 'string' ? document.querySelector(opts.container) : opts.container;
  if (!container) return;
  const interval = opts.interval || 10000;
  const isBusy = opts.isBusy || (() => !!document.querySelector('.ach-modal-bg.open, .modal-overlay.show'));
  let inFlight = false;
  function tick() {
    if (document.hidden || isBusy() || inFlight) return;
    inFlight = true;
    fetch(opts.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.ok ? r.text() : Promise.reject())
      .then(html => { container.innerHTML = html; if (opts.onUpdate) opts.onUpdate(); })
      .catch(() => {})
      .finally(() => { inFlight = false; });
  }
  setInterval(tick, interval);
}

// ===== SIDEBAR SCROLL RESTORE =====
(function() {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;
  const KEY = 'digistock_sidebar_scroll';
  const saved = sessionStorage.getItem(KEY);
  if (saved) sidebar.scrollTop = parseInt(saved, 10);
  sidebar.addEventListener('scroll', () => {
    sessionStorage.setItem(KEY, sidebar.scrollTop);
  });
})();

// ===== TOAST NOTIFICATIONS =====
// Région aria-live unique et réutilisée (pas un <div> recréé à chaque appel) :
// un lecteur d'écran surveille un nœud stable de façon fiable, alors que
// détecter l'insertion répétée de nouveaux nœuds porteurs de role="status"
// est moins constant d'un lecteur d'écran à l'autre.
function toast(msg, type = 'success') {
  let t = document.getElementById('toast-live');
  const neuf = !t;
  if (neuf) {
    t = document.createElement('div');
    t.id = 'toast-live';
    t.setAttribute('role', 'status');
    t.setAttribute('aria-live', 'polite');
    t.setAttribute('aria-atomic', 'true');
    document.body.appendChild(t);
  }
  clearTimeout(t._hideTimer);
  t.className = `toast toast-${type}`;
  t.innerHTML = msg;
  setTimeout(() => t.classList.add('show'), neuf ? 10 : 0);
  t._hideTimer = setTimeout(() => t.classList.remove('show'), 3500);
}
</script>

<style>
.toast {
  position: fixed; bottom: 24px; right: 24px; z-index: 9999;
  padding: 14px 20px; border-radius: 10px;
  font-size: 13.5px; font-weight: 500;
  box-shadow: 0 8px 24px rgba(0,0,0,.15);
  opacity: 0; transform: translateY(10px);
  transition: background-color .3s ease, border-color .3s ease, color .3s ease, box-shadow .3s ease, transform .3s ease, opacity .3s ease;
  max-width: 360px;
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast-success { background: #eafaf1; color: #1e8449; border-left: 3px solid #27ae60; }
.toast-danger  { background: #fdf0ef; color: #922b21; border-left: 3px solid #e74c3c; }
.toast-warning { background: #fef9e7; color: #9a7d0a; border-left: 3px solid #f39c12; }
.toast-info    { background: #ebf5fb; color: #1a5276; border-left: 3px solid #2980b9; }
</style>

</body>
</html>
