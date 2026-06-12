<?php
/**
 * API: Toggle agent chat permission (admin only)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
startSecureSession();
requireAdmin();

$db = db();

// Add permissions column to agents if missing
try { $db->exec("ALTER TABLE agents ADD COLUMN permissions JSON NULL DEFAULT NULL"); } catch(Exception $e) {}

$action    = $_POST['action'] ?? '';
$agentId   = (int)($_POST['agent_id'] ?? 0);

// All state-changing actions require CSRF
if (in_array($action, ['toggle_chat', 'set_permission'])) {
    verifyCsrf();
}

if (!$agentId) jsonError('Missing agent_id');

switch ($action) {
    case 'toggle_chat':
        // Get current permissions
        $stmt = $db->prepare("SELECT permissions FROM agents WHERE id=?");
        $stmt->execute([$agentId]);
        $row = $stmt->fetch();
        $perms = json_decode($row['permissions'] ?? '{}', true) ?: [];

        // Toggle can_chat
        $perms['can_chat'] = empty($perms['can_chat']) ? true : false;

        $update = $db->prepare("UPDATE agents SET permissions=? WHERE id=?");
        $update->execute([json_encode($perms), $agentId]);

        jsonSuccess(['can_chat' => $perms['can_chat'], 'permissions' => $perms]);
        break;

    case 'set_permission':
        $perm  = $_POST['perm'] ?? '';
        $value = (bool)($_POST['value'] ?? false);
        $allowed = ['can_chat','chat_support','chat_wallet','view_orders','add_transaction','view_reports'];
        if (!in_array($perm, $allowed)) jsonError('Invalid permission');

        $stmt = $db->prepare("SELECT permissions FROM agents WHERE id=?");
        $stmt->execute([$agentId]);
        $row = $stmt->fetch();
        $perms = json_decode($row['permissions'] ?? '{}', true) ?: [];
        $perms[$perm] = $value;

        $db->prepare("UPDATE agents SET permissions=? WHERE id=?")->execute([json_encode($perms), $agentId]);
        jsonSuccess(['permissions' => $perms]);
        break;

    case 'get_permissions':
        $stmt = $db->prepare("SELECT id, full_name, role, permissions, is_active FROM agents WHERE id=?");
        $stmt->execute([$agentId]);
        $row = $stmt->fetch();
        if (!$row) jsonError('Agent not found');
        $row['permissions'] = json_decode($row['permissions'] ?? '{}', true) ?: [];
        jsonSuccess(['agent' => $row]);
        break;

    default:
        jsonError('Invalid action');
}
