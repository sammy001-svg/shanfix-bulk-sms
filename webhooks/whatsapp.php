<?php
/**
 * WhatsApp Unified Webhook Handler & Intelligent Router
 * Processes incoming WhatsApp messages and triggers automated responses
 * using Chatbots, Hierarchical Menus, Self-Service modules, and Gemini AI.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/gateways/whatsapp.php';

// 1. Capture Incoming JSON / POST
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);
if (!$data) {
    $data = $_POST;
}

// Optional logging for developer diagnostics
file_put_contents(__DIR__ . '/webhook_log.txt', date('[Y-m-d H:i:s] ') . $rawData . PHP_EOL, FILE_APPEND);

if (!$data) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'No data received.']));
}

// Normalize instance, sender, and message
$instanceId = $data['instance_id'] ?? $data['instance'] ?? '';
$sender = $data['sender'] ?? $data['from'] ?? '';
$incomingMsg = trim($data['message'] ?? $data['text'] ?? $data['msg'] ?? $data['body'] ?? '');

if (empty($instanceId) || empty($sender) || empty($incomingMsg)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid payload.']));
}

$sender = preg_replace('/[^0-9]/', '', $sender);

// 2. Identify Active Account and User
$account = DB::queryOne("
    SELECT a.*, u.whatsapp_balance, u.whatsapp_rate 
    FROM whatsapp_accounts a
    JOIN users u ON a.user_id = u.id
    WHERE a.instance_id = ? AND a.status = 'active'
", [$instanceId]);

if (!$account) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'error' => 'Account not found or inactive.']));
}

$uid = $account['user_id'];
$accId = $account['id'];
$rate = (float)($account['whatsapp_rate'] ?? 1.00);

// 3. Log Incoming Message to Inbox
$inboxId = DB::insert("
    INSERT INTO whatsapp_inbox (user_id, account_id, sender, message, direction, status, source) 
    VALUES (?, ?, ?, ?, 'in', 'received', 'human')
", [$uid, $accId, $sender, $incomingMsg]);

// 4. Validate User Balance
if ($account['whatsapp_balance'] < $rate) {
    error_log("WhatsApp Webhook: Insufficient balance for User ID {$uid} to send auto-reply.");
    exit(json_encode(['success' => false, 'error' => 'Insufficient balance']));
}

// 5. Intelligent Routing Logic
$responseFound = false;
$responseText = "";
$mediaUrl = null;
$source = 'bot';

$lowMsg = strtolower($incomingMsg);

// Fetch Active Menu Session (within 30 minutes duration)
$session = DB::queryOne("
    SELECT * FROM whatsapp_bot_sessions 
    WHERE account_id = ? AND sender_phone = ? 
    AND last_interaction > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
", [$accId, $sender]);

$currentMenuId = $session ? $session['current_menu_id'] : null;

// --- A. Command Overrides (Exit/Back/Menu) ---
if ($session && in_array($lowMsg, ['exit', 'close', 'quit'])) {
    DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = NULL WHERE id = ?", [$session['id']]);
    $responseText = "Conversation session closed. Thank you!";
    $responseFound = true;
}

if (!$responseFound && $session && $currentMenuId && in_array($lowMsg, ['0', 'back', 'menu'])) {
    // Find parent menu
    $currentMenu = DB::queryOne("SELECT parent_id FROM whatsapp_chatbots WHERE id = ?", [$currentMenuId]);
    if ($currentMenu && $currentMenu['parent_id'] !== null) {
        $parentId = $currentMenu['parent_id'];
        $parentRule = DB::queryOne("SELECT * FROM whatsapp_chatbots WHERE id = ?", [$parentId]);
        if ($parentRule) {
            $responseText = $parentRule['response'];
            $mediaUrl = $parentRule['media_url'];
            
            DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = ? WHERE id = ?", [$parentId, $session['id']]);
            
            // Append sub-options
            $children = DB::query("SELECT keyword FROM whatsapp_chatbots WHERE parent_id = ? AND user_id = ? ORDER BY id ASC", [$parentId, $uid]);
            if ($children) {
                $responseText .= "\n\nReply with a number:";
                foreach ($children as $idx => $child) {
                    $responseText .= "\n" . ($idx + 1) . ". " . $child['keyword'];
                }
            }
            $responseFound = true;
        }
    } else {
        // Return to global context
        DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = NULL WHERE id = ?", [$session['id']]);
        $responseText = "Returned to the main menu.";
        $responseFound = true;
    }
}

// --- B. Check Active Menu Session Sub-Options ---
if (!$responseFound && $currentMenuId) {
    $subOptions = DB::query("SELECT * FROM whatsapp_chatbots WHERE parent_id = ? AND user_id = ?", [$currentMenuId, $uid]);
    $match = null;
    
    // Check numeric input
    if (is_numeric($incomingMsg)) {
        $idx = (int)$incomingMsg - 1;
        if (isset($subOptions[$idx])) {
            $match = $subOptions[$idx];
        }
    }
    
    // Check keyword comparison in sub-options
    if (!$match) {
        foreach ($subOptions as $opt) {
            if (compareKeyword($incomingMsg, $opt['keyword'], $opt['match_type'])) {
                $match = $opt;
                break;
            }
        }
    }
    
    if ($match) {
        $responseText = $match['response'];
        $mediaUrl = $match['media_url'];
        
        // Handle Dynamic Data Lookup
        if ($match['is_dynamic'] && !empty($match['data_source_table'])) {
            $searchKey = $incomingMsg;
            $table = $match['data_source_table'];
            $dataRow = DB::queryOne("
                SELECT data_value FROM whatsapp_custom_data 
                WHERE user_id = ? AND table_name = ? AND data_key = ?
            ", [$uid, $table, $searchKey]);
            
            if ($dataRow) {
                $values = json_decode($dataRow['data_value'], true);
                foreach ($values as $k => $v) {
                    $responseText = str_replace(["{{{$k}}}", "{{$k}}", "{$k}", "{" . $k . "}"], $v, $responseText);
                    
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
        
        if ($match['is_menu']) {
            $children = DB::query("SELECT keyword FROM whatsapp_chatbots WHERE parent_id = ? AND user_id = ? ORDER BY id ASC", [$match['id'], $uid]);
            if ($children) {
                $responseText .= "\n\nReply with a number:";
                foreach ($children as $idx => $child) {
                    $responseText .= "\n" . ($idx + 1) . ". " . $child['keyword'];
                }
            }
            DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = ? WHERE id = ?", [$match['id'], $session['id']]);
        } else {
            DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = NULL WHERE id = ?", [$session['id']]);
        }
        
        DB::execute("UPDATE whatsapp_chatbots SET trigger_count = trigger_count + 1 WHERE id = ?", [$match['id']]);
        $responseFound = true;
    }
}

// --- C. Check Self-Service Modules ---
if (!$responseFound) {
    $selfService = DB::query("SELECT * FROM whatsapp_self_service WHERE user_id = ? AND is_enabled = 1", [$uid]);
    foreach ($selfService as $ss) {
        $trigger = strtolower($ss['trigger_keyword']);
        
        if (strpos($lowMsg, $trigger) === 0 || strpos($lowMsg, " " . $trigger) !== false) {
            $arg = trim(str_ireplace($trigger, '', $incomingMsg));
            $arg = preg_replace('/[^a-zA-Z0-9-]/', '', $arg);
            
            $type = $ss['module_type'];
            $template = $ss['response_template'];
            
            if ($type === 'order_status') {
                if (!empty($arg)) {
                    $order = DB::queryOne("SELECT * FROM demo_orders WHERE order_no = ? OR order_no = ? OR id = ?", [$arg, "ORD-$arg", $arg]);
                    if ($order) {
                        $responseText = str_replace(
                            ['{order_no}', '{status}', '{delivery_date}', '{amount}'],
                            [$order['order_no'], $order['status'], $order['delivery_date'], number_format($order['amount'], 2)],
                            $template
                        );
                    } else {
                        $responseText = "Sorry, I couldn't find an order with ID: $arg. Please check and try again.";
                    }
                } else {
                    $responseText = "Please provide an Order ID (e.g., " . strtoupper($ss['trigger_keyword']) . " #1001).";
                }
                $responseFound = true;
            }
            elseif ($type === 'appointment') {
                $appt = DB::queryOne("SELECT * FROM demo_appointments WHERE customer_phone LIKE ? ORDER BY appointment_at DESC LIMIT 1", ["%$sender%"]);
                if ($appt) {
                    $responseText = str_replace(
                        ['{service_type}', '{appointment_at}', '{status}'],
                        [$appt['service_type'], date('d M Y H:i', strtotime($appt['appointment_at'])), $appt['status']],
                        $template
                    );
                } else {
                    $responseText = "You don't have any upcoming appointments. To book, visit our website or call us.";
                }
                $responseFound = true;
            }
            elseif ($type === 'account') {
                $customer = DB::queryOne("SELECT * FROM users WHERE phone LIKE ? OR email LIKE ?", ["%$sender%", "%$sender%"]);
                if ($customer) {
                    $responseText = str_replace(
                        ['{name}', '{balance}', '{status}'],
                        [$customer['name'], number_format($customer['sms_units']), $customer['status']],
                        $template
                    );
                } else {
                    $responseText = "I couldn't find an account linked to this phone number.";
                }
                $responseFound = true;
            }
            elseif ($type === 'delivery') {
                if (!empty($arg)) {
                    $lookup = DB::queryOne("SELECT data_value FROM whatsapp_custom_data WHERE user_id = ? AND table_name = 'delivery' AND data_key = ?", [$uid, $arg]);
                    
                    if ($lookup) {
                        $responseText = $template;
                        $values = json_decode($lookup['data_value'], true);
                        if (is_array($values)) {
                            foreach ($values as $k => $v) {
                                $responseText = str_replace(["{{{$k}}}", "{{$k}}", "{$k}", "{" . $k . "}"], $v, $responseText);
                            }
                        }
                    } else {
                        // Fallback to check demo_orders for tracking
                        $order = DB::queryOne("SELECT * FROM demo_orders WHERE order_no = ? OR order_no = ? OR id = ?", [$arg, "ORD-$arg", $arg]);
                        if ($order) {
                            $location = "Main Sorting Facility";
                            if (strcasecmp($order['status'], 'in transit') === 0) {
                                $location = "Dispatched from Nairobi Hub, in transit";
                            } elseif (strcasecmp($order['status'], 'delivered') === 0) {
                                $location = "Delivered to Customer";
                            } elseif (strcasecmp($order['status'], 'pending') === 0) {
                                $location = "Warehouse (Awaiting Dispatch)";
                            }
                            $responseText = str_replace(
                                ['{order_no}', '{status}', '{location}'],
                                [$order['order_no'], $order['status'], $location],
                                $template
                            );
                        } else {
                            $responseText = "Sorry, I couldn't find a delivery record for: $arg. Please check and try again.";
                        }
                    }
                } else {
                    $responseText = "Please provide a tracking code or Order ID (e.g., " . strtoupper($ss['trigger_keyword']) . " ORD-1001).";
                }
                $responseFound = true;
            }
        }
        if ($responseFound) break;
    }
}

// --- D. Check Global Chatbot Rules ---
if (!$responseFound) {
    $globalRules = DB::query("SELECT * FROM whatsapp_chatbots WHERE parent_id IS NULL AND user_id = ? ORDER BY match_type='exact' DESC", [$uid]);
    foreach ($globalRules as $rule) {
        if (compareKeyword($incomingMsg, $rule['keyword'], $rule['match_type'])) {
            $responseText = $rule['response'];
            $mediaUrl = $rule['media_url'];
            
            // Handle Dynamic Data Lookup
            if ($rule['is_dynamic'] && !empty($rule['data_source_table'])) {
                $parts = explode(' ', $incomingMsg);
                $key = end($parts);
                $dynamicData = DB::queryOne("SELECT data_value FROM whatsapp_custom_data WHERE user_id = ? AND table_name = ? AND data_key = ?", [$uid, $rule['data_source_table'], $key]);
                
                if ($dynamicData) {
                    $values = json_decode($dynamicData['data_value'], true);
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            $responseText = str_replace(["{{{$k}}}", "{{$k}}", "{$k}", "{" . $k . "}"], $v, $responseText);
                        }
                    }
                }
            }
            
            if ($rule['is_menu']) {
                $children = DB::query("SELECT keyword FROM whatsapp_chatbots WHERE parent_id = ? AND user_id = ? ORDER BY id ASC", [$rule['id'], $uid]);
                if ($children) {
                    $responseText .= "\n\nReply with a number:";
                    foreach ($children as $idx => $child) {
                        $responseText .= "\n" . ($idx + 1) . ". " . $child['keyword'];
                    }
                }
                
                if ($session) {
                    DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = ? WHERE id = ?", [$rule['id'], $session['id']]);
                } else {
                    DB::execute("INSERT INTO whatsapp_bot_sessions (account_id, sender_phone, current_menu_id) VALUES (?, ?, ?)", [$accId, $sender, $rule['id']]);
                }
            } else {
                if ($session) {
                    DB::execute("UPDATE whatsapp_bot_sessions SET current_menu_id = NULL WHERE id = ?", [$session['id']]);
                }
            }
            
            DB::execute("UPDATE whatsapp_chatbots SET trigger_count = trigger_count + 1 WHERE id = ?", [$rule['id']]);
            $responseFound = true;
            break;
        }
    }
}

// --- E. Google Gemini AI Fallback ---
if (!$responseFound && !empty($account['ai_enabled']) && !empty($account['ai_api_key'])) {
    require_once __DIR__ . '/../includes/helpers/ai.php';
    $aiModel = $account['ai_model'] ?? 'gemini-1.5-flash';
    $aiPrompt = $account['ai_prompt'] ?? '';
    
    $ai = new Gemini_AI($account['ai_api_key'], $aiModel, $aiPrompt);
    $aiResponse = $ai->generateResponse($incomingMsg);
    
    if ($aiResponse) {
        $responseText = $aiResponse;
        $source = 'ai';
        $responseFound = true;
    }
}

// 6. Dispatch Response and Deduct Rate
if ($responseFound && !empty($responseText)) {
    $gateway = new WhatsApp_Gateway($account['instance_id'], $account['token'], $accId);
    $res = $gateway->sendMessage($sender, $responseText, $mediaUrl);
    
    if ($res && isset($res['success']) && $res['success']) {
        // Deduct Rate Balance
        DB::execute("UPDATE users SET whatsapp_balance = whatsapp_balance - ? WHERE id = ?", [$rate, $uid]);
        
        // Log Outgoing response to Inbox
        DB::insert("
            INSERT INTO whatsapp_inbox (user_id, account_id, sender, message, direction, source, status) 
            VALUES (?, ?, ?, ?, 'out', ?, 'sent')
        ", [$uid, $accId, $sender, $responseText, $source]);
        
        // Log to general messages table
        DB::insert("
            INSERT INTO whatsapp_messages (user_id, account_id, recipient, message, status, external_id) 
            VALUES (?, ?, ?, ?, 'sent', ?)
        ", [$uid, $accId, $sender, $responseText, $res['message_id'] ?? null]);
    } else {
        DB::insert("
            INSERT INTO whatsapp_inbox (user_id, account_id, sender, message, direction, source, status) 
            VALUES (?, ?, ?, ?, 'out', ?, 'failed')
        ", [$uid, $accId, $sender, $responseText, $source]);
    }
}

echo json_encode(['success' => true, 'responded' => $responseFound]);

/**
 * Helper to match keywords with NLP fallbacks for sentences
 */
function compareKeyword($input, $keyword, $type) {
    $input = strtolower(trim($input));
    $keyword = strtolower(trim($keyword));
    
    if (strlen($input) > 20 && ($type == 'contains' || $type == 'exact')) {
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
