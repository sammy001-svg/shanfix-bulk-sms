<?php
/**
 * WhatsApp Chatbot Webhook Handler
 * Processes incoming messages and triggers automated responses.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/gateways/whatsapp.php';

// Capture incoming JSON
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    die("No data received.");
}

// 1. Identify Account & Sender
// We assume the payload contains 'instance_id' and 'sender'
$instanceId = $data['instance_id'] ?? $data['instance'] ?? '';
$sender = $data['sender'] ?? $data['from'] ?? '';
$incomingMsg = trim($data['message'] ?? $data['text'] ?? '');

if (!$instanceId || !$sender || !$incomingMsg) {
    die("Invalid payload.");
}

// Normalize phone
$sender = preg_replace('/[^0-9]/', '', $sender);

// Find Active Account
$account = DB::queryOne("SELECT * FROM whatsapp_accounts WHERE instance_id = ? AND status = 'active'", [$instanceId]);
if (!$account) {
    die("Account not found or inactive.");
}

$uid = $account['user_id'];
$accId = $account['id'];

// 2. Manage Session State
// Sessions expire after 30 minutes
$session = DB::queryOne("
    SELECT * FROM whatsapp_bot_sessions 
    WHERE account_id = ? AND sender_phone = ? 
    AND last_interaction > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
", [$accId, $sender]);

$currentMenuId = $session ? $session['current_menu_id'] : null;

// 3. Match Logic
$match = null;

// A. Check for sub-options if in a menu
if ($currentMenuId) {
    $subOptions = DB::query("SELECT * FROM whatsapp_chatbots WHERE parent_id = ? AND user_id = ?", [$currentMenuId, $uid]);
    
    // Check for numeric input first (e.g., user sent "1")
    if (is_numeric($incomingMsg)) {
        $idx = (int)$incomingMsg - 1;
        if (isset($subOptions[$idx])) {
            $match = $subOptions[$idx];
        }
    }
    
    // Check for keyword match in sub-options
    if (!$match) {
        foreach ($subOptions as $opt) {
            if (compareKeyword($incomingMsg, $opt['keyword'], $opt['match_type'])) {
                $match = $opt;
                break;
            }
        }
    }
}

// B. Check for global triggers if no sub-option match
if (!$match) {
    $globalRules = DB::query("SELECT * FROM whatsapp_chatbots WHERE parent_id IS NULL AND user_id = ?", [$uid]);
    foreach ($globalRules as $rule) {
        if (compareKeyword($incomingMsg, $rule['keyword'], $rule['match_type'])) {
            $match = $rule;
            break;
        }
    }
}

// 4. Action execution
if ($match) {
    $responseText = $match['response'];
    $mediaUrl = $match['media_url'];

    // Handle Dynamic Data Lookup
    if ($match['is_dynamic'] && !empty($match['data_source_table'])) {
        $searchKey = $incomingMsg; // The ID provided by user
        $table = $match['data_source_table'];
        
        $dataRow = DB::queryOne("
            SELECT data_value FROM whatsapp_custom_data 
            WHERE user_id = ? AND table_name = ? AND data_key = ?
        ", [$uid, $table, $searchKey]);
        
        if ($dataRow) {
            $values = json_decode($dataRow['data_value'], true);
            // Replace placeholders like {{status}} with actual data
            foreach ($values as $k => $v) {
                $responseText = str_replace("{{" . $k . "}}", $v, $responseText);
                
                // Smart Media Detection: If field name suggests an image and has a value, use it as mediaUrl
                if (empty($mediaUrl) && (stripos($k, 'image') !== false || stripos($k, 'picture') !== false || stripos($k, 'photo') !== false)) {
                    if (!empty($v) && (strpos($v, 'http') === 0 || strpos($v, '/uploads') === 0)) {
                        $mediaUrl = $v;
                    }
                }
            }
        } else {
            $responseText = "Sorry, I couldn't find any record for '$searchKey' in our $table database. Please verify the ID and try again.";
        }
    }
    
    // If it's a menu, append sub-options
    if ($match['is_menu']) {
        $children = DB::query("SELECT keyword FROM whatsapp_chatbots WHERE parent_id = ? AND user_id = ? ORDER BY id ASC", [$match['id'], $uid]);
        if ($children) {
            $responseText .= "\n\nReply with a number:";
            foreach ($children as $idx => $child) {
                $responseText .= "\n" . ($idx + 1) . ". " . $child['keyword'];
            }
        }
        
        // Update/Create session
        if ($session) {
            DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = ? WHERE id = ?", [$match['id'], $session['id']]);
        } else {
            DB::execute("INSERT INTO whatsapp_bot_sessions (account_id, sender_phone, current_menu_id) VALUES (?, ?, ?)", [$accId, $sender, $match['id']]);
        }
    } else {
        // Not a menu, end the session context (or clear menu)
        if ($session) {
            DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = NULL WHERE id = ?", [$session['id']]);
        }
    }

    // Increment Trigger Count
    DB::execute("UPDATE whatsapp_chatbots SET trigger_count = trigger_count + 1 WHERE id = ?", [$match['id']]);

    // Send Response
    $gateway = new WhatsApp_Gateway($account['instance_id'], $account['token'], $accId);
    $gateway->sendMessage($sender, $responseText, $mediaUrl);
}

/**
 * Keyword Comparison Helper (Basic NLP)
 */
function compareKeyword($input, $keyword, $type) {
    $input = strtolower(trim($input));
    $keyword = strtolower(trim($keyword));
    
    // Basic NLP: If input is a long sentence, check if the keyword is a core component
    if (strlen($input) > 20 && ($type == 'contains' || $type == 'exact')) {
        // If the exact keyword is found as a word in the sentence
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $input)) {
            return true;
        }
    }

    switch ($type) {
        case 'exact':
            return $input === $keyword;
        case 'contains':
            return strpos($input, $keyword) !== false;
        case 'starts_with':
            return strpos($input, $keyword) === 0;
        default:
            return false;
    }
}
