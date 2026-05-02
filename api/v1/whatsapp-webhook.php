<?php
/**
 * WhatsApp Webhook - Shanfix Technology
 * Handles incoming messages and executes Self-Service / Chatbot logic.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/gateways/whatsapp.php';

// Simulate receiving JSON data from the provider
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['from'], $data['text'], $data['instance_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid payload']));
}

$from = $data['from'];
$text = trim($data['text']);
$instanceId = $data['instance_id'];

// 1. Identify User by Instance ID
$account = DB::queryOne("SELECT * FROM whatsapp_accounts WHERE instance_id = ?", [$instanceId]);
if (!$account) {
    exit(json_encode(['success' => false, 'error' => 'Instance not found']));
}
$uid = $account['user_id'];
$GLOBALS['uid'] = $uid;

// 2. Check Self-Service Modules First
$selfService = DB::query("SELECT * FROM whatsapp_self_service WHERE user_id = ? AND is_enabled = 1", [$uid]);

foreach ($selfService as $ss) {
    $keyword = strtoupper($ss['trigger_keyword']);
    if (str_starts_with(strtoupper($text), $keyword)) {
        handleSelfService($ss, $text, $from, $uid, $instanceId, $account['id']);
        exit;
    }
}

// 3. Fallback to Chatbot Rules
$rules = DB::query("SELECT * FROM whatsapp_chatbots WHERE user_id = ? ORDER BY created_at DESC", [$uid]);
foreach ($rules as $rule) {
    $match = false;
    $keyword = strtoupper($rule['keyword']);
    $msgText = strtoupper($text);
    
    if ($rule['match_type'] === 'exact' && $msgText === $keyword) $match = true;
    elseif ($rule['match_type'] === 'contains' && str_contains($msgText, $keyword)) $match = true;
    elseif ($rule['match_type'] === 'starts_with' && str_starts_with($msgText, $keyword)) $match = true;
    
    if ($match) {
        $gw = new WhatsApp_Gateway($instanceId, "SIMULATED_TOKEN", $account['id']);
        $gw->sendMessage($from, $rule['response'], $rule['media_url']);
        
        // Update trigger count
        DB::execute("UPDATE whatsapp_chatbots SET trigger_count = trigger_count + 1 WHERE id = ?", [$rule['id']]);
        exit;
    }
}

/**
 * Handle Specialized Self-Service Modules
 */
function handleSelfService($config, $text, $from, $uid, $instanceId, $accountId) {
    $type = $config['module_type'];
    $response = $config['response_template'];
    $gw = new WhatsApp_Gateway($instanceId, "SIMULATED_TOKEN", $accountId);

    if ($type === 'order_status') {
        // Extract order number from message (e.g. "ORDER #1001")
        preg_match('/#?([A-Z0-9-]+)/i', str_replace($config['trigger_keyword'], '', $text), $matches);
        $orderNo = trim($matches[1] ?? '');
        
        if ($orderNo) {
            $order = DB::queryOne("SELECT * FROM demo_orders WHERE order_no = ? OR order_no = ? OR id = ?", [$orderNo, "ORD-$orderNo", $orderNo]);
            if ($order) {
                $response = str_replace(
                    ['{order_no}', '{status}', '{delivery_date}', '{amount}'],
                    [$order['order_no'], $order['status'], $order['delivery_date'], number_format($order['amount'], 2)],
                    $response
                );
            } else {
                $response = "Sorry, I couldn't find an order with ID: $orderNo. Please check and try again.";
            }
        } else {
            $response = "Please provide an Order ID (e.g., " . $config['trigger_keyword'] . " #1001).";
        }
    } 
    elseif ($type === 'appointment') {
        // Find appointment by phone number
        $appt = DB::queryOne("SELECT * FROM demo_appointments WHERE customer_phone LIKE ? ORDER BY appointment_at DESC LIMIT 1", ["%$from%"]);
        if ($appt) {
            $response = str_replace(
                ['{service_type}', '{appointment_at}', '{status}'],
                [$appt['service_type'], date('d M Y H:i', strtotime($appt['appointment_at'])), $appt['status']],
                $response
            );
        } else {
            $response = "You don't have any upcoming appointments. To book, visit our website or call us.";
        }
    }
    elseif ($type === 'account') {
        // Query user details (simulating looking up the end-customer)
        $customer = DB::queryOne("SELECT * FROM users WHERE phone LIKE ?", ["%$from%"]);
        if ($customer) {
            $response = str_replace(
                ['{name}', '{balance}', '{status}'],
                [$customer['name'], $customer['sms_units'], $customer['status']],
                $response
            );
        } else {
            $response = "I couldn't find an account linked to this phone number.";
        }
    }

    $gw->sendMessage($from, $response);
}
