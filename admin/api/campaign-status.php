<?php
/**
 * API: Admin-scoped campaign status + delivery detail
 *
 * The client endpoint (/client/api/campaign-status.php) filters every query by
 * the caller's own user_id, so an admin polling it gets nothing back. This is
 * the same contract, scoped platform-wide instead.
 *
 * GET  /admin/api/campaign-status.php
 *      → { campaigns: [{id, status, sent_count, failed_count, total_count}, …] }
 *        All currently active campaigns across all users.
 *
 * GET  /admin/api/campaign-status.php?ids=1,2,3
 *      → { campaigns: [ … ] }   Progress for the listed campaigns, any status.
 *
 * GET  /admin/api/campaign-status.php?campaign_id=X[&page=N]
 *      → { campaign: {…}, breakdown: {status: count, …}, units_charged: N,
 *          failed_messages: [ … ], failed_total: N, pages: N, page: N }
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = auth_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Single campaign detail ────────────────────────────────────────────────────
if (isset($_GET['campaign_id'])) {
    $campaignId = (int)$_GET['campaign_id'];
    $campaign   = DB::queryOne(
        "SELECT id, user_id, name, status, sent_count, failed_count, total_count,
                units_used, locked_at, last_heartbeat_at
         FROM campaigns WHERE id = ?",
        [$campaignId]
    );
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        exit;
    }

    // Message-level breakdown — the campaign counters only track sent vs failed,
    // so delivery receipts (delivered/undelivered) are only visible here.
    $breakdown = [];
    $units     = 0.0;
    foreach (DB::query(
        "SELECT status, COUNT(*) AS c, COALESCE(SUM(units_charged),0) AS units
         FROM messages WHERE campaign_id = ? GROUP BY status", [$campaignId]
    ) as $row) {
        $breakdown[$row['status']] = (int)$row['c'];
        $units += (float)$row['units'];
    }

    $out = [
        'campaign'      => $campaign,
        'breakdown'     => $breakdown,
        'units_charged' => round($units, 4),
    ];

    if (($_GET['failed'] ?? '') === '1') {
        $perPage     = 25;
        $page        = max(1, (int)($_GET['page'] ?? 1));
        $offset      = ($page - 1) * $perPage;
        $failedTotal = (int)DB::queryValue(
            "SELECT COUNT(*) FROM messages WHERE campaign_id = ? AND status IN ('failed','undelivered')",
            [$campaignId]
        );
        $out['failed_messages'] = DB::query(
            "SELECT recipient, failed_reason, status, created_at
             FROM messages
             WHERE campaign_id = ? AND status IN ('failed','undelivered')
             ORDER BY id DESC
             LIMIT $perPage OFFSET $offset",
            [$campaignId]
        );
        $out['failed_total'] = $failedTotal;
        $out['pages']        = max(1, (int)ceil($failedTotal / $perPage));
        $out['page']         = $page;
    }

    echo json_encode($out);
    exit;
}

// ── Specific campaign IDs (list page passes every visible row) ────────────────
if (isset($_GET['ids'])) {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_GET['ids'])))));
    if (empty($ids)) {
        echo json_encode(['campaigns' => []]);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    echo json_encode(['campaigns' => DB::query(
        "SELECT id, status, sent_count, failed_count, total_count
         FROM campaigns WHERE id IN ($placeholders) ORDER BY created_at DESC",
        $ids
    )]);
    exit;
}

// ── All active campaigns, platform-wide ───────────────────────────────────────
echo json_encode(['campaigns' => DB::query(
    "SELECT id, status, sent_count, failed_count, total_count
     FROM campaigns WHERE status IN ('queued','running','sending') ORDER BY created_at DESC"
)]);
