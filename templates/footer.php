  </main><!-- end .page-content -->
</div><!-- end .main-wrap -->

<script>
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
  }).then(() => { if (lien) window.location.href = '<?= APP_URL ?>' + lien; });
}
function markAllRead() {
  fetch('<?= APP_URL ?>/api/notifications.php', {
    method: 'POST',
    headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=mark_all'
  }).then(() => location.reload());
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
function toast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = msg;
  document.body.appendChild(t);
  setTimeout(() => t.classList.add('show'), 10);
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3500);
}
</script>

<style>
.toast {
  position: fixed; bottom: 24px; right: 24px; z-index: 9999;
  padding: 14px 20px; border-radius: 10px;
  font-size: 13.5px; font-weight: 500;
  box-shadow: 0 8px 24px rgba(0,0,0,.15);
  opacity: 0; transform: translateY(10px);
  transition: all .3s ease;
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
