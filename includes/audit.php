<?php
// ============================================================
//  includes/audit.php — Journal d'audit
// ============================================================
function audit_log(?int $user_id, string $action, string $module, ?int $entite_id, string $description = ''): void {
    try {
        db_query(
            "INSERT INTO audit_log (user_id,action,module,entite_id,description,ip_address,created_at)
             VALUES (?,?,?,?,?,?,NOW())",
            [$user_id, $action, $module, $entite_id, $description, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}
