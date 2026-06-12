<?php
/**
 * Mimics chat-agent.php get_waiting action inline to catch errors - DELETE AFTER USE
 */
ob_start(); // capture any PHP warnings/notices
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
startSecureSession();
if (empty($_SESSION['agent_id'])) die('Not logged in');

$db      = db();
$isAdmin = ($_SESSION['role'] === 'admin');
$agentId = (int)$_SESSION['agent_id'];

// Run the ALTER TABLE statements (same as chat-agent.php)
$alterErrors = [];
try { @$db->exec("ALTER TABLE chat_sessions ADD COLUMN transferred_from VARCHAR(100) NULL"); } catch(Exception $e) { $alterErrors[] = $e->getMessage(); }
try { @$db->exec("ALTER TABLE chat_sessions ADD COLUMN rating INT DEFAULT NULL"); } catch(Exception $e) { $alterErrors[] = $e->getMessage(); }
try { @$db->exec("ALTER TABLE chat_sessions ADD COLUMN rating_comment TEXT NULL"); } catch(Exception $e) { $alterErrors[] = $e->getMessage(); }

$phpOutput = ob_get_clean(); // capture anything printed so far

// Run the exact get_waiting query from chat-agent.php
$error = null;
$sessions = [];
try {
    if ($isAdmin) {
        $stmt = $db->query("SELECT cs.id,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.created_at,cs.transferred_from,cs.department,cs.customer_username,cs.wallet_balance,a.full_name AS agent_name, cs.agent_id FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.status='waiting' AND (cs.agent_id IS NULL OR cs.agent_id=0) ORDER BY cs.id ASC");
        $sessions = $stmt->fetchAll();
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'           => $error === null,
    'error'        => $error,
    'php_output'   => $phpOutput,
    'alter_errors' => $alterErrors,
    'session_count'=> count($sessions),
    'sessions'     => $sessions
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
