<?php
// Si connecté → rediriger vers dashboard automatiquement
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('APP_URL')) {
    require_once dirname(__DIR__) . '/includes/db.php';
}
$dashboard_url = APP_URL . '/pages/dashboard.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accès refusé — DigiStock</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Plus Jakarta Sans',Arial,sans-serif;background:#f0f4f8;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .box{background:white;border-radius:20px;padding:48px 40px;text-align:center;max-width:440px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.1)}
    .icon{font-size:64px;margin-bottom:20px}
    h1{font-size:22px;font-weight:800;color:#06033A;margin-bottom:10px}
    p{font-size:14px;color:#777;margin-bottom:8px;line-height:1.6}
    .timer{font-size:13px;color:#aaa;margin-bottom:24px}
    .btn{display:inline-block;padding:12px 28px;background:#06033A;color:white;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;margin:6px;cursor:pointer;border:none}
    .btn:hover{background:#1B75BC}
    .btn-sec{background:#f0f4f8;color:#06033A;border:1.5px solid #dee2e6}
    .btn-sec:hover{background:#dee2e6;color:#06033A}
    .progress{height:4px;background:#e9ecef;border-radius:2px;margin-bottom:20px;overflow:hidden}
    .progress-bar{height:100%;background:#1B75BC;border-radius:2px;animation:progress 3s linear forwards}
    @keyframes progress{from{width:0%}to{width:100%}}
  </style>
</head>
<body>
  <div class="box">
    <div class="icon">🔒</div>
    <h1>Accès refusé</h1>
    <p>Vous n'avez pas les permissions pour accéder à cette page.</p>
    <p class="timer" id="timer">Redirection automatique dans <strong id="count">3</strong>s...</p>
    <div class="progress"><div class="progress-bar"></div></div>
    <a href="<?= $dashboard_url ?>" class="btn">🏠 Tableau de bord</a>
    <a href="javascript:history.go(-2)" class="btn btn-sec">← Retour</a>
  </div>
  <script>
    // Redirection automatique vers dashboard après 3 secondes
    let n = 3;
    const interval = setInterval(() => {
      n--;
      document.getElementById('count').textContent = n;
      if (n <= 0) {
        clearInterval(interval);
        window.location.replace('<?= $dashboard_url ?>');
      }
    }, 1000);
  </script>
</body>
</html>
