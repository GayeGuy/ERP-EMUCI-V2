<?php
// ============================================================
//  logout.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/auth.php';

auth_logout(($_GET['timeout'] ?? '') === '1');
