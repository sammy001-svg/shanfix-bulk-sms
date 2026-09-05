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
const DR_DERIVED_LABEL = "COALESCE(NULLIF(m.dlr_status, ''), CASE m.status
            WHEN 'delivered'   THEN 'DELIVRD'
            WHEN 'sent'        THEN 'Submitted'
            WHEN 'failed'      THEN 'REJECTD'
            WHEN 'undelivered' THEN 'DeliveryImpossible'
            WHEN 'queued'      THEN 'Queued'
            ELSE 'Unknown' END)";

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

// ── Bucketing (Aggregate mode) ────────────────────────────────────────────────
/** Collapse a granular carrier status into Delivered / Pending / Failed. */
function dr_bucket(string $label): string {
    $key = strtolower(preg_replace('/[^a-z]/i', '', $label));

    $delivered = ['delivrd', 'delivered', 'delivredtoterminal', 'deliveredtoterminal', 'success'];
    $pending   = ['submitted', 'acceptd', 'accepted', 'enroute', 'buffered', 'pending', 'queued'];

    if (in_array($key, $delivered, true)) return 'Delivered';
    if (in_array($key, $pending, true))   return 'Pending';
    if ($key === 'unknown')               return 'Unknown';
    return 'Failed';
}

// ── Pivot ─────────────────────────────────────────────────────────────────────
$drRows    = [];   // day => ['cells' => [col => n], 'total_sms' => n, 'total_units' => f]
$drColSeen = [];   // column name => total across all days
$drTotals  = ['cells' => [], 'total_sms' => 0, 'total_units' => 0.0];

foreach ($drGrouped as $g) {
    $day   = $g['day'];
    $col   = $drMode === 'aggregate' ? dr_bucket((string)$g['dlr']) : (string)$g['dlr'];
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
// Fixed priority for the statuses the carrier commonly reports, so the table
// keeps a stable, readable shape; anything unrecognised is appended A-Z.
$drPriority = $drMode === 'aggregate'
    ? ['Delivered', 'Pending', 'Failed', 'Unknown']
    : [
        'DelivredToTerminal', 'DeliveredToTerminal', 'DELIVRD', 'Delivered',
        'Submitted', 'Queued', 'ACCEPTD', 'ENROUTE', 'Buffered',
        'AbsentSubscriber', 'DeliveryImpossible', 'REJECTD', 'Rejected',
        'Sendername blacklisted', 'Expired', 'Unknown',
      ];

$drColumns = array_keys($drColSeen);
usort($drColumns, static function ($a, $b) use ($drPriority) {
    $ia = array_search($a, $drPriority, true);
    $ib = array_search($b, $drPriority, true);
    if ($ia === false && $ib === false) return strcasecmp($a, $b);
    if ($ia === false) return 1;
    if ($ib === false) return -1;
    return $ia <=> $ib;
});

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
