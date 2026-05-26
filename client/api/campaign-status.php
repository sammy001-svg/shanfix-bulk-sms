<?php
/**
 * API: Real-time campaign status + failed-message detail
 * Used by the campaigns page for live polling (no page reload needed).
 *
 * GET  /client/api/campaign-status.php
 *      → { campaigns: [{id, status, sent_count, failed_count, total_count}, …] }
 *        Returns all active campaigns for the current user.
 *
 * GET  /client/api/campaign-status.php?campaign_id=X[&page=N]
 *      → { campaign: {…}, failed_messages: [{recipient, failed_reason, created_at}, …],
 *          failed_total: N, pages: N }
 *        Returns detail + paginated failed messages for one campaign.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = auth_user();
if (!$user || !in_array($user['role'], ['reseller', 'client'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$uid = (int)$user['id'];

// ── Single campaign detail ─────────────────────────────────────────────────────
if (isset($_GET['campaign_id'])) {
    $campaignId = (int)$_GET['campaign_id'];
    $campaign   = DB::queryOne(
        "SELECT id, name, status, sent_count, failed_count, total_count
         FROM campaigns WHERE id = ? AND user_id = ?",
        [$campaignId, $uid]
    );
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        exit;
    }

    $perPage     = 25;
    $page        = max(1, (int)($_GET['page'] ?? 1));
    $offset      = ($page - 1) * $perPage;
    $failedTotal = (int)DB::queryValue(
        "SELECT COUNT(*) FROM messages WHERE campaign_id = ? AND user_id = ? AND status = 'failed'",
        [$campaignId, $uid]
    );
    $failed = DB::query(
        "SELECT recipient, message, failed_reason, created_at
         FROM messages
         WHERE campaign_id = ? AND user_id = ? AND status = 'failed'
         ORDER BY id DESC
         LIMIT ? OFFSET ?",
        [$campaignId, $uid, $perPage, $offset]
    );

    echo json_encode([
        'campaign'        => $campaign,
        'failed_messages' => $failed,
        'failed_total'    => $failedTotal,
        'pages'           => max(1, (int)ceil($failedTotal / $perPage)),
        'page'            => $page,
    ]);
    exit;
}

// ── Specific campaign IDs (JS passes all visible IDs, regardless of status) ───
if (isset($_GET['ids'])) {
    $rawIds = explode(',', $_GET['ids']);
    $ids    = array_values(array_unique(array_filter(array_map('intval', $rawIds))));
    if (empty($ids)) {
        echo json_encode(['campaigns' => []]);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $campaigns = DB::query(
        "SELECT id, status, sent_count, failed_count, total_count
         FROM campaigns
         WHERE user_id = ? AND id IN ($placeholders)
         ORDER BY created_at DESC",
        array_merge([$uid], $ids)
    );
    echo json_encode(['campaigns' => $campaigns]);
    exit;
}

// ── All active campaigns (legacy / no-ids fallback) ───────────────────────────
$campaigns = DB::query(
    "SELECT id, status, sent_count, failed_count, total_count
     FROM campaigns
     WHERE user_id = ? AND status IN ('queued','running','sending')
     ORDER BY created_at DESC",
    [$uid]
);

echo json_encode(['campaigns' => $campaigns]);
