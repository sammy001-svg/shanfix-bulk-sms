<?php
/**
 * Cron: settle payments the webhook never confirmed — Shanfix Technology
 *
 * The webhook credits instantly and the browser poll covers the customer who
 * waits on the page. This catches the rest: someone who paid and immediately
 * closed the tab while the callback was blocked or delayed.
 *
 * Recommended crontab (every minute):
 *   * * * * * php /home/user/public_html/cron/reconcile_payments.php >> /home/user/logs/reconcile.log 2>&1
 *
 * Every path funnels into Purchase::complete() / USSD_Wallet::complete(),
 * which claim the row atomically — running this alongside a live webhook can
 * never double-credit.
 */
set_time_limit(0);

require_once __DIR__ . '/../includes/actions/payment-reconcile.php';

$started = microtime(true);
$result  = PaymentReconcile::sweep(50);
$elapsed = round(microtime(true) - $started, 2);

$credited = $result['purchases_credited'] + $result['ussd_credited'];
$checked  = $result['purchases_checked']  + $result['ussd_checked'];

// Stay quiet on idle ticks so the cron log remains readable.
if ($checked > 0 || $credited > 0) {
    printf(
        "[%s] checked %d (purchases %d, ussd %d) — credited %d (purchases %d, ussd %d) in %ss\n",
        date('Y-m-d H:i:s'),
        $checked, $result['purchases_checked'], $result['ussd_checked'],
        $credited, $result['purchases_credited'], $result['ussd_credited'],
        $elapsed
    );
}
