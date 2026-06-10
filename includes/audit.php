<?php
// ============================================================
//  includes/audit.php — Journal d'audit
// ============================================================
function audit_log(?int $user_id, string $action, string $table, ?int $record_id, string $details = ''): void {
    try {
        db_query(
            "INSERT INTO audit_log (user_id,action,table_name,record_id,details,ip_address,created_at)
             VALUES (?,?,?,?,?,?,NOW())",
            [$user_id, $action, $table, $record_id, $details, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}
