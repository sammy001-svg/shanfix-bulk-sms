<?php
try {
$pageTitle = 'WhatsApp Conversations';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Inbox']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Fetch all unique conversations (last message from each sender)
$conversations = DB::query("
    SELECT i1.*, a.instance_id
    FROM whatsapp_inbox i1
    JOIN whatsapp_accounts a ON i1.account_id = a.id
    WHERE i1.user_id = ? 
    AND i1.id IN (
        SELECT MAX(id) 
        FROM whatsapp_inbox 
        WHERE user_id = ? 
        GROUP BY sender
    )
    ORDER BY i1.created_at DESC
", [$uid, $uid]) ?: [];

$selectedSender = $_GET['chat'] ?? ($conversations[0]['sender'] ?? null);
$chatHistory = [];

if ($selectedSender) {
    $chatHistory = DB::query("
        SELECT * FROM whatsapp_inbox 
        WHERE user_id = ? AND sender = ? 
        ORDER BY created_at ASC
    ", [$uid, $selectedSender]) ?: [];
}

// Handle Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $replyMsg = sanitize($_POST['message']);
        $sender = $_POST['sender'];
        
        // Find the active account for this user
        $acc = DB::queryOne("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$uid]);
        
        if ($acc && !empty($replyMsg)) {
            require_once __DIR__ . '/../includes/gateways/whatsapp.php';
            $gateway = new WhatsApp_Gateway($acc['instance_id'], $acc['token'], $acc['id']);
            $res = $gateway->sendMessage($sender, $replyMsg);
            
            if ($res['success']) {
                // Log the outgoing message
                DB::insert("INSERT INTO whatsapp_inbox (user_id, account_id, sender, message, direction, status) VALUES (?, ?, ?, ?, 'out', 'sent')", [$uid, $acc['id'], $sender, $replyMsg]);
                flash_set('success', 'Message sent.');
                header("Location: whatsapp-inbox.php?chat=" . $sender);
                exit;
            } else {
                flash_set('danger', 'Failed to send: ' . $res['message']);
            }
        }
    }
}
?>

<style>
.inbox-container { display: flex; height: calc(100vh - 250px); background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); overflow: hidden; }

/* Left Sidebar: Contact List */
.inbox-list { width: 350px; border-right: 1px solid var(--border); display: flex; flex-direction: column; }
.inbox-list-header { padding: 20px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 18px; }
.inbox-items { flex: 1; overflow-y: auto; }
.inbox-item { padding: 15px 20px; border-bottom: 1px solid var(--border); cursor: pointer; transition: all 0.2s; display: flex; gap: 12px; align-items: center; }
.inbox-item:hover { background: var(--bg-muted); }
.inbox-item.active { background: rgba(0, 200, 150, 0.05); border-left: 4px solid var(--primary); }
.avatar { width: 45px; height: 45px; border-radius: 50%; background: var(--bg-muted); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary); font-size: 18px; }

.item-info { flex: 1; min-width: 0; }
.item-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.item-name { font-weight: 700; font-size: 14px; }
.item-time { font-size: 11px; color: var(--text-muted); }
.item-msg { font-size: 12px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Right Section: Chat Window */
.chat-window { flex: 1; display: flex; flex-direction: column; background: #f8fafc; }
.chat-header { padding: 15px 25px; background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.chat-messages { flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }

.bubble { max-width: 70%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.5; position: relative; }
.bubble.in { align-self: flex-start; background: #fff; border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.bubble.out { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 4px; }
.bubble-time { font-size: 10px; margin-top: 5px; opacity: 0.7; display: block; text-align: right; }

.chat-footer { padding: 20px; background: var(--bg-card); border-top: 1px solid var(--border); }
.chat-input-wrapper { display: flex; gap: 12px; background: var(--bg-muted); padding: 8px; border-radius: 30px; border: 1px solid var(--border); }
.chat-input { flex: 1; background: transparent; border: none; padding: 10px 20px; outline: none; font-size: 14px; }

.empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); }
</style>

<div class="inbox-container">
    <!-- Sidebar -->
    <div class="inbox-list">
        <div class="inbox-list-header">Conversations</div>
        <div class="inbox-items">
            <?php if (empty($conversations)): ?>
                <div style="padding:40px; text-align:center; color:var(--text-muted)">
                    <i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:10px; display:block"></i>
                    No messages yet
                </div>
            <?php endif; ?>
            <?php foreach ($conversations as $c): ?>
                <a href="?chat=<?= $c['sender'] ?>" class="inbox-item <?= $selectedSender === $c['sender'] ? 'active' : '' ?>">
                    <div class="avatar"><?= substr($c['sender'], -2) ?></div>
                    <div class="item-info">
                        <div class="item-top">
                            <span class="item-name"><?= htmlspecialchars($c['sender']) ?></span>
                            <span class="item-time"><?= date('H:i', strtotime($c['created_at'])) ?></span>
                        </div>
                        <div class="item-msg"><?= htmlspecialchars($c['message']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="chat-window">
        <?php if ($selectedSender): ?>
            <div class="chat-header">
                <div style="display:flex; align-items:center; gap:12px">
                    <div class="avatar"><?= substr($selectedSender, -2) ?></div>
                    <div>
                        <div style="font-weight:700"><?= htmlspecialchars($selectedSender) ?></div>
                        <div style="font-size:11px; color:var(--success)">● Online via Instance</div>
                    </div>
                </div>
                <div style="display:flex; gap:10px">
                    <button class="btn btn-sm btn-icon"><i class="fa-solid fa-phone"></i></button>
                    <button class="btn btn-sm btn-icon"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php foreach ($chatHistory as $chat): ?>
                    <div class="bubble <?= $chat['direction'] ?>">
                        <?= nl2br(htmlspecialchars($chat['message'])) ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; opacity:0.7; font-size:10px">
                            <span class="source-tag">
                                <?php if($chat['direction'] === 'out'): ?>
                                    <i class="fa-solid <?= $chat['source'] === 'ai' ? 'fa-brain' : ($chat['source'] === 'bot' ? 'fa-robot' : 'fa-user') ?>" style="margin-right:3px"></i>
                                    <?= strtoupper($chat['source']) ?>
                                <?php endif; ?>
                            </span>
                            <span><?= date('H:i', strtotime($chat['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-footer">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="sender" value="<?= htmlspecialchars($selectedSender) ?>">
                    <div class="chat-input-wrapper">
                        <input type="text" name="message" class="chat-input" placeholder="Type a response..." required autocomplete="off">
                        <button type="submit" name="send_reply" class="btn btn-primary" style="border-radius:50%; width:40px; height:40px; padding:0; display:flex; align-items:center; justify-content:center">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:64px; color:var(--bg-muted); margin-bottom:20px"><i class="fa-solid fa-comments"></i></div>
                <h3>Select a conversation</h3>
                <p>Choose a contact from the left to start messaging</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-scroll chat to bottom
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>

<?php
} catch (Throwable $e) {
    echo "<div style='padding:20px; border:2px solid red; background:#fff1f1; color:red; font-family:monospace; margin:20px; border-radius:10px;'>";
    echo "<h3>⚠️ Inbox Interface Error</h3>";
    echo "<b>Message:</b> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<b>File:</b> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "</div>";
}
?>
