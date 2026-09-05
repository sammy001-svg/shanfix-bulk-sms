<?php
/**
 * Shared Delivery Report engine — Shanfix Technology
 *
 * Builds the per-day / per-delivery-status pivot used by the Delivery Reports
 * page in all three portals. One implementation so admin, reseller and client
 * see an identical report; only the row scope differs.
 *
 * The caller must define before including this file:
 *   $drScopeSql       string  SQL predicate on alias `m`, e.g. "m.user_id = ?"
 *   $drScopeParams    array   Bound parameters for $drScopeSql
 *   $drUserOptions    array   Rows of ['id','name'] for the owner filter, [] to hide it
 *   $drUserFilterLabel string Label for that filter ("User" / "Client")
 *
 * Handles the CSV export and exits, so include it BEFORE layout.php.
 *
 * WHERE THE STATUSES COME FROM
 * ----------------------------
 * messages.dlr_status holds the raw carrier state posted by the DLR webhook.
 * Rows that predate that column — or messages the carrier never reported on —
 * fall back to a label derived from messages.status, so the report is never
 * blank and grows more precise as real delivery receipts arrive.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/dlr-status.php';

// ── Filters ───────────────────────────────────────────────────────────────────
$drToday = date('Y-m-d');

/** Accept only YYYY-MM-DD; anything else falls back to $default. */
$drDate = static function (?string $v, string $default): string {
    $v = trim((string)$v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
};

$drPreset = $_GET['preset'] ?? '';
$drFrom   = $drDate($_GET['from'] ?? null, date('Y-m-d', strtotime('-6 days')));
$drTo     = $drDate($_GET['to']   ?? null, $drToday);

// A preset overrides the explicit range — matches the quick-pick chips.
switch ($drPreset) {
    case 'today':      $drFrom = $drToday;                                    $drTo = $drToday; break;
    case 'yesterday':  $drFrom = date('Y-m-d', strtotime('-1 day'));          $drTo = $drFrom;  break;
    case 'last7':      $drFrom = date('Y-m-d', strtotime('-6 days'));         $drTo = $drToday; break;
    case 'week':       $drFrom = date('Y-m-d', strtotime('monday this week'));$drTo = $drToday; break;
    case 'last30':     $drFrom = date('Y-m-d', strtotime('-29 days'));        $drTo = $drToday; break;
    case 'month':      $drFrom = date('Y-m-01');                              $drTo = $drToday; break;
    case 'lastmonth':  $drFrom = date('Y-m-01', strtotime('first day of last month'));
                       $drTo   = date('Y-m-t', strtotime('last day of last month')); break;
    default:           $drPreset = '';
}

// Guard an inverted range rather than silently returning nothing.
if ($drFrom > $drTo) { [$drFrom, $drTo] = [$drTo, $drFrom]; }

$drMode = ($_GET['mode'] ?? 'detailed') === 'aggregate' ? 'aggregate' : 'detailed';

$drUserOptions     = $drUserOptions     ?? [];
$drUserFilterLabel = $drUserFilterLabel ?? 'User';
$drUserId          = (int)($_GET['user_id'] ?? 0);

// Only honour a user filter that is actually in this viewer's allowed set.
if ($drUserId > 0 && !in_array($drUserId, array_map('intval', array_column($drUserOptions, 'id')), true)) {
    $drUserId = 0;
}

// ── Query ─────────────────────────────────────────────────────────────────────
// Derived label: the carrier's own status when we have it, otherwise mapped
// from our ENUM so historical messages still appear somewhere.
// Derived label, most specific source first:
//   1. the carrier receipt, when the DLR webhook recorded one;
//   2. the send-time failure text, which distinguishes an absent subscriber
//      from a blacklisted sender ID and so on;
//   3. our own ENUM, which can only ever say delivered / sent / failed.
// Kept in step with DlrStatus::normalise() so both agree on every label.
const DR_DERIVED_LABEL = "COALESCE(
        NULLIF(m.dlr_status, ''),
        CASE
            WHEN m.status IN ('failed','undelivered') AND COALESCE(m.failed_reason,'') <> '' THEN
                CASE
                    WHEN m.failed_reason LIKE '%unregistered%'
                      OR m.failed_reason LIKE '%invalid%number%'
                      OR m.failed_reason LIKE '%invalid%mobile%'
                      OR m.failed_reason LIKE '%absent%'                THEN 'AbsentSubscriber'
                    WHEN m.failed_reason LIKE '%sender%not approved%'
                      OR m.failed_reason LIKE '%sender id%'
                      OR m.failed_reason LIKE '%blacklist%'             THEN 'Sendername blacklisted'
                    WHEN m.failed_reason LIKE '%expired%'
                      OR m.failed_reason LIKE '%timed out%'             THEN 'Expired'
                    WHEN m.failed_reason LIKE '%reject%'                THEN 'REJECTD'
                    ELSE 'DeliveryImpossible'
                END
            WHEN m.status = 'delivered'   THEN 'DELIVRD'
            WHEN m.status = 'sent'        THEN 'Submitted'
            WHEN m.status = 'queued'      THEN 'Submitted'
            WHEN m.status = 'failed'      THEN 'REJECTD'
            WHEN m.status = 'undelivered' THEN 'DeliveryImpossible'
            ELSE 'Unknown'
        END)";

$drWhere  = "WHERE ($drScopeSql) AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$drParams = array_merge($drScopeParams, [$drFrom, $drTo]);

if ($drUserId > 0) {
    $drWhere   .= " AND m.user_id = ?";
    $drParams[] = $drUserId;
}

$drGrouped = DB::query(
    "SELECT DATE(m.created_at) AS day,
            " . DR_DERIVED_LABEL . " AS dlr,
            COUNT(*) AS cnt,
            COALESCE(SUM(m.units_charged), 0) AS units
     FROM messages m
     $drWhere
     GROUP BY day, dlr
     ORDER BY day DESC",
    $drParams
);

// ── Where the labels came from ────────────────────────────────────────────────
// Lets an empty column be explained: either no message reached that state, or
// no receipt ever arrived to say so.
$drSources = DB::query(
    "SELECT COALESCE(NULLIF(m.dlr_status, ''), '(no carrier receipt)') AS source,
            m.status AS enum_status,
            COALESCE(NULLIF(m.failed_reason, ''), '') AS reason,
            COUNT(*) AS cnt
     FROM messages m
     $drWhere
     GROUP BY source, enum_status, reason
     ORDER BY cnt DESC
     LIMIT 25",
    $drParams
);

// ── Receipt coverage ──────────────────────────────────────────────────────────
// How much of this period is backed by a real carrier receipt rather than
// derived from our own ENUM. If this stays at 0, Onfon is not calling the DLR
// URL and the granular columns cannot populate.
$drCoverage = DB::queryOne(
    "SELECT COUNT(*) AS total,
            SUM(m.dlr_status IS NOT NULL AND m.dlr_status <> '') AS with_dlr
     FROM messages m
     $drWhere",
    $drParams
);
$drTotalMsgs   = (int)($drCoverage['total'] ?? 0);
$drWithDlr     = (int)($drCoverage['with_dlr'] ?? 0);
$drCoveragePct = $drTotalMsgs > 0 ? round($drWithDlr / $drTotalMsgs * 100, 1) : 0.0;

// ── Bucketing (Aggregate mode) ────────────────────────────────────────────────
// Delivered / Pending / Failed, decided by the shared status vocabulary so the
// two modes can never classify the same carrier state differently.

// ── Pivot ─────────────────────────────────────────────────────────────────────
$drRows    = [];   // day => ['cells' => [col => n], 'total_sms' => n, 'total_units' => f]
$drColSeen = [];   // column name => total across all days
$drTotals  = ['cells' => [], 'total_sms' => 0, 'total_units' => 0.0];

foreach ($drGrouped as $g) {
    $day   = $g['day'];
    // Normalise on read as well: rows written before the shared vocabulary
    // existed may hold a raw carrier string such as 'DeliveredToTerminal'.
    $label = DlrStatus::normalise((string)$g['dlr']);
    $col   = $drMode === 'aggregate' ? DlrStatus::bucket($label) : $label;
    $cnt   = (int)$g['cnt'];
    $units = (float)$g['units'];

    if (!isset($drRows[$day])) {
        $drRows[$day] = ['cells' => [], 'total_sms' => 0, 'total_units' => 0.0];
    }

    $drRows[$day]['cells'][$col] = ($drRows[$day]['cells'][$col] ?? 0) + $cnt;
    $drRows[$day]['total_sms']   += $cnt;
    $drRows[$day]['total_units'] += $units;

    $drColSeen[$col]        = ($drColSeen[$col] ?? 0) + $cnt;
    $drTotals['cells'][$col] = ($drTotals['cells'][$col] ?? 0) + $cnt;
    $drTotals['total_sms']   += $cnt;
    $drTotals['total_units'] += $units;
}

// ── Column order ──────────────────────────────────────────────────────────────
// The canonical statuses are always rendered, even with no traffic in the
// period, so the table keeps the same shape as the Onfon report rather than
// gaining and losing columns as the date range changes. Any carrier state we
// have not catalogued is appended after them, A-Z, so it stays visible.
if ($drMode === 'aggregate') {
    $drPriority = ['Delivered', 'Pending', 'Failed', 'Unknown'];
    $drColumns  = $drPriority;
} else {
    $drPriority = DlrStatus::CANONICAL;
    $drColumns  = $drPriority;
}

$drExtra = array_diff(array_keys($drColSeen), $drPriority);
sort($drExtra, SORT_NATURAL | SORT_FLAG_CASE);
$drColumns = array_merge($drColumns, $drExtra);

// Newest day first, as in the source report.
krsort($drRows);

// ── Query-string helper ───────────────────────────────────────────────────────
function dr_qs(array $overrides = []): string {
    global $drFrom, $drTo, $drMode, $drUserId;
    $base = ['from' => $drFrom, 'to' => $drTo, 'mode' => $drMode];
    if ($drUserId > 0) $base['user_id'] = $drUserId;
    return http_build_query(array_filter(
        array_merge($base, $overrides),
        static fn($v) => $v !== null && $v !== ''
    ));
}

// ── CSV export ────────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="delivery_report_' . $drFrom . '_to_' . $drTo . '.csv"');

    $fh = fopen('php://output', 'w');
    fputcsv($fh, array_merge(['Date'], $drColumns, ['total_sms', 'total_units']));

    foreach ($drRows as $day => $row) {
        $line = [$day];
        foreach ($drColumns as $col) {
            $line[] = $row['cells'][$col] ?? 0;
        }
        $line[] = $row['total_sms'];
        $line[] = round($row['total_units'], 2);
        fputcsv($fh, $line);
    }

    if (!empty($drRows)) {
        $line = ['TOTAL'];
        foreach ($drColumns as $col) {
            $line[] = $drTotals['cells'][$col] ?? 0;
        }
        $line[] = $drTotals['total_sms'];
        $line[] = round($drTotals['total_units'], 2);
        fputcsv($fh, $line);
    }

    fclose($fh);
    exit;
}
