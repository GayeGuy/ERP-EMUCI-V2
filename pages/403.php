<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accès refusé — StockApp</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans&display=swap" rel="stylesheet">
  <style>
    body { font-family:'DM Sans',sans-serif; background:#f0f4f8; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { background:white; border-radius:16px; padding:48px; text-align:center; max-width:420px; border:1px solid #e2e8f0; }
    .code { font-family:'Syne',sans-serif; font-size:80px; font-weight:800; color:#e74c3c; line-height:1; margin-bottom:8px; }
    h2 { font-family:'Syne',sans-serif; font-size:22px; color:#0d1f35; margin-bottom:12px; }
    p { color:#64748b; font-size:14px; margin-bottom:28px; }
    a { display:inline-block; padding:12px 24px; background:#e67e22; color:white; border-radius:8px; text-decoration:none; font-weight:600; }
  </style>
</head>
<body>
  <div class="box">
    <div class="code">403</div>
    <h2>Accès refusé</h2>
    <p>Vous n'avez pas les permissions nécessaires pour accéder à cette page. Contactez votre administrateur si vous pensez qu'il s'agit d'une erreur.</p>
    <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/pages/dashboard.php">← Retour au tableau de bord</a>
  </div>
</body>
</html>
