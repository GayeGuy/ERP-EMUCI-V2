<?php
// ============================================================
//  templates/403_recette.php — refus d'accès en mode recette Achats
// ============================================================
//
//  templates/403.php renvoie vers pages/dashboard.php, écran lui-même fermé
//  en recette : le testeur y tournerait en rond. Cette page-ci ramène vers
//  le module Achats et dit pourquoi l'écran demandé est clos, pour qu'un
//  refus attendu ne remonte pas comme une anomalie.
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('APP_URL')) {
    require_once dirname(__DIR__) . '/includes/db.php';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Écran hors périmètre de la recette — ERP EMUCI</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Plus Jakarta Sans',Arial,sans-serif;background:#f0f4f8;min-height:100vh;
         display:flex;align-items:center;justify-content:center;padding:24px}
    .box{background:#fff;border-radius:20px;padding:44px 40px;text-align:center;max-width:520px;width:100%;
         box-shadow:0 8px 32px rgba(0,0,0,.1)}
    .icon{font-size:56px;margin-bottom:18px}
    h1{font-size:21px;color:#06033A;margin-bottom:14px}
    p{font-size:14.5px;color:#475569;line-height:1.65;margin-bottom:12px}
    .note{font-size:13px;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;
          padding:12px 14px;margin:20px 0;text-align:left}
    a.btn{display:inline-block;margin-top:8px;background:#B45309;color:#fff;text-decoration:none;
          font-weight:700;font-size:14px;padding:13px 26px;border-radius:10px}
  </style>
</head>
<body>
  <div class="box">
    <div class="icon">🧭</div>
    <h1>Cet écran ne fait pas partie de la recette</h1>
    <p>Cette instance est dédiée à l'essai du <strong>module Achats</strong>. Les autres
       modules — Stock, Bobines, Opérations, Administration — y sont volontairement fermés.</p>
    <div class="note">
      Ce refus est <strong>attendu</strong> : inutile de le signaler comme anomalie.
      Vos retours portent sur le parcours d'achat — expression de besoin, arbitrage,
      offres, visas, suivi des commandes et réceptions.
    </div>
    <a class="btn" href="<?= APP_URL ?>/pages/achats/mes_feb.php">Revenir au module Achats</a>
  </div>
</body>
</html>
