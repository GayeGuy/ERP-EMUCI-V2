<?php
// ============================================================
//  pages/consommables.php  —  Redirection vers articles.php
//  Module renommé : Consommables → Articles
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_auth();
header('Location: ' . APP_URL . '/pages/articles.php' . (!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : ''));
exit;
