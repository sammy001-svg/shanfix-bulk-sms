<?php
/**
 * WhatsApp Webhook Receiver & Intelligent Router
 * -----------------------------------------------
 * This script handles incoming messages from the WhatsApp Gateway,
 * logs them, and routes them to Chatbots or Self-Service modules.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/gateways/whatsapp.php';

// 1. Capture Incoming Data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// For debugging (optional, remove in production)
file_put_contents(__DIR__ . '/webhook_log.txt', date('[Y-m-d H:i:s] ') . $rawData . PHP_EOL, FILE_APPEND);

if (!$data || !isset($data['instance_id'])) {
    http_response_code(400);
    die('Invalid Data');
}

$instanceId = $data['instance_id'];
$sender     = preg_replace('/[^0-9]/', '', $data['from'] ?? '');
$message    = trim($data['message'] ?? '');

if (empty($sender) || empty($message)) {
    die('Ignored: Empty message or sender');
}

// 2. Identify Account & User
$account = DB::queryOne("SELECT id, user_id, token FROM whatsapp_accounts WHERE instance_id = ? AND status = 'active'", [$instanceId]);

if (!$account) {
    die('Ignored: Account inactive or not found');
}

$uid = $account['user_id'];
$accId = $account['id'];

// 3. Log Incoming Message
DB::insert("INSERT INTO whatsapp_inbox (user_id, account_id, sender, message, direction, status) VALUES (?, ?, ?, ?, 'in', 'received')", [$uid, $accId, $sender, $message]);

// 4. Intelligent Routing
$responseFound = false;
$responseText  = "";
$mediaUrl      = null;

$lowMsg = strtolower($message);

// --- A. Check Chatbot Rules ---
// Exact match first, then starts with, then contains
$rules = DB::query("SELECT * FROM whatsapp_chatbots WHERE user_id = ? ORDER BY match_type='exact' DESC", [$uid]);

foreach ($rules as $rule) {
    $keyword = strtolower($rule['keyword']);
    $matched = false;

    if ($rule['match_type'] === 'exact' && $lowMsg === $keyword) $matched = true;
    elseif ($rule['match_type'] === 'starts_with' && strpos($lowMsg, $keyword) === 0) $matched = true;
    elseif ($rule['match_type'] === 'contains' && strpos($lowMsg, $keyword) !== false) $matched = true;

    if ($matched) {
        $responseText = $rule['response'];
        $mediaUrl     = $rule['media_url'];
        
        // Handle Dynamic Placeholders if enabled
        if ($rule['is_dynamic'] && !empty($rule['data_source_table'])) {
            // Attempt to find a data key in the message (e.g. "STATUS 1001")
            $parts = explode(' ', $message);
            $key = end($parts); // Assume the last word is the key
            
            $dynamicData = DB::queryOne("SELECT data_value FROM whatsapp_custom_data WHERE user_id = ? AND table_name = ? AND data_key = ?", [$uid, $rule['data_source_table'], $key]);
            
            if ($dynamicData) {
                $values = json_decode($dynamicData['data_value'], true);
                if (is_array($values)) {
                    foreach ($values as $k => $v) {
                        $responseText = str_replace(["{{{$k}}}", "{{$k}}", "{$k}"], $v, $responseText);
                    }
                }
            }
        }

        DB::execute("UPDATE whatsapp_chatbots SET trigger_count = trigger_count + 1 WHERE id = ?", [$rule['id']]);
        $responseFound = true;
        break;
    }
}

// --- B. Check Self-Service Modules (if no chatbot matched) ---
if (!$responseFound) {
    $modules = DB::query("SELECT * FROM whatsapp_self_service WHERE user_id = ? AND is_enabled = 1", [$uid]);
    foreach ($modules as $m) {
        $trigger = strtolower($m['trigger_keyword']);
        if (strpos($lowMsg, $trigger) !== false) {
            // Extract the argument (e.g. "ORDER #1234" -> 1234)
            $arg = trim(str_replace($trigger, '', $lowMsg));
            $arg = preg_replace('/[^a-zA-Z0-9]/', '', $arg);
            
            if (!empty($arg)) {
                // Lookup in custom data hub
                // We assume self-service modules map to table names or common keys
                $lookup = DB::queryOne("SELECT data_value FROM whatsapp_custom_data WHERE user_id = ? AND data_key = ?", [$uid, $arg]);
                
                if ($lookup) {
                    $responseText = $m['response_template'];
                    $values = json_decode($lookup['data_value'], true);
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            $responseText = str_replace(["{{{$k}}}", "{{$k}}", "{$k}", "{{$k}}", "{$k}"], $v, $responseText);
                        }
                    }
                    $responseFound = true;
                }
            }
        }
    }

    // --- PHASE 3: AI FALLBACK ---
    if (!$responseFound && !empty($account['ai_enabled']) && !empty($account['ai_api_key'])) {
        require_once __DIR__ . '/../includes/helpers/ai.php';
        $ai = new Gemini_AI($account['ai_api_key'], $account['ai_model'], $account['ai_prompt']);
        $aiResponse = $ai->generateResponse($message);
        
        if ($aiResponse) {
            $responseText = $aiResponse;
            $responseFound = true;
        }
    }
}

// 5. Send Response
if ($responseFound && !empty($responseText)) {
    $gateway = new WhatsApp_Gateway($instanceId, $account['token'], $accId);
    $res = $gateway->sendMessage($sender, $responseText, $mediaUrl);
    
    // Log outgoing message
    DB::insert("INSERT INTO whatsapp_inbox (user_id, account_id, sender, message, direction, status) VALUES (?, ?, ?, ?, 'out', 'sent')", [$uid, $accId, $sender, $responseText]);
    
    // Also log to general messages table
    DB::insert("INSERT INTO whatsapp_messages (user_id, account_id, recipient, message, media_url, status, external_id) VALUES (?, ?, ?, ?, ?, 'sent', ?)", 
        [$uid, $accId, $sender, $responseText, $mediaUrl, $res['message_id'] ?? null]);
}

echo json_encode(['success' => true, 'responded' => $responseFound]);
