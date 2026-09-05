<?php
/**
 * Payment reconciliation — Shanfix Technology
 *
 * Guarantees a successful M-Pesa payment credits the account even when the
 * Kopo Kopo webhook never arrives.
 *
 * Crediting has three independent paths, in order of speed:
 *   1. kopokopo_webhook.php   — instant, the normal case.
 *   2. This class, called from ajax-check-payment.php while the customer is
 *      still watching the "waiting for PIN" dialog. Asks Kopo Kopo directly.
 *   3. cron/reconcile_payments.php — sweeps anything left pending for someone
 *      who closed the browser.
 *
 * All three funnel into Purchase::complete() / USSD_Wallet::complete(), which
 * claim the row atomically, so a payment can never be credited twice no matter
 * how many paths race.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../gateways/kopokopo.php';
require_once __DIR__ . '/purchases.php';
require_once __DIR__ . '/ussd-wallet.php';

class PaymentReconcile {

    /** Don't badger the gateway about a push the customer hasn't answered yet. */
    const MIN_AGE_SECONDS = 5;

    /** Stop chasing a payment that was never completed. */
    const GIVE_UP_MINUTES = 60;

    private static function log(string $msg): void {
        $dir = __DIR__ . '/../../tmp';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($dir . '/payment_reconcile.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    }

    /**
     * Confirm one pending unit/WhatsApp purchase against Kopo Kopo.
     *
     * @return array{status:string, credited:bool, checked:bool, error?:string}
     *         status is the row's status after the attempt.
     */
    public static function purchase(int $purchaseId): array {
        $row = DB::queryOne(
            "SELECT id, status, gateway_ref, transaction_ref, created_at FROM purchases WHERE id = ?",
            [$purchaseId]
        );
        if (!$row) {
            return ['status' => 'unknown', 'credited' => false, 'checked' => false, 'error' => 'Purchase not found'];
        }
        if ($row['status'] !== 'pending') {
            return ['status' => $row['status'], 'credited' => false, 'checked' => false];
        }

        $gate = self::shouldCheck($row);
        if ($gate !== null) return $gate;

        $res = KopoKopo::getPaymentStatus((string)$row['gateway_ref']);
        if (empty($res['ok'])) {
            self::log("purchase #$purchaseId check failed: " . ($res['error'] ?? 'unknown'));
            return ['status' => 'pending', 'credited' => false, 'checked' => true, 'error' => $res['error'] ?? null];
        }

        if (!empty($res['successful'])) {
            // Record the M-Pesa receipt before crediting; at this point
            // transaction_ref still holds the payer's phone number.
            if (!empty($res['mpesa_ref'])) {
                DB::execute(
                    "UPDATE purchases SET transaction_ref = ?
                     WHERE id = ? AND (transaction_ref IS NULL OR transaction_ref = ''
                                       OR transaction_ref LIKE '%254%' OR transaction_ref LIKE '0%')",
                    [$res['mpesa_ref'], $purchaseId]
                );
            }
            $credited = Purchase::complete($purchaseId);
            self::log("purchase #$purchaseId SUCCESS via reconcile, credited=" . ($credited ? 'yes' : 'no (already done)'));
            return ['status' => 'completed', 'credited' => (bool)$credited, 'checked' => true];
        }

        if (!empty($res['failed'])) {
            DB::execute("UPDATE purchases SET status = 'failed' WHERE id = ? AND status = 'pending'", [$purchaseId]);
            self::log("purchase #$purchaseId FAILED at gateway ({$res['status']})");
            return ['status' => 'failed', 'credited' => false, 'checked' => true];
        }

        return ['status' => 'pending', 'credited' => false, 'checked' => true];
    }

    /**
     * Confirm one pending USSD wallet top-up against Kopo Kopo.
     *
     * @return array{status:string, credited:bool, checked:bool, error?:string}
     */
    public static function ussd(int $transactionId): array {
        $row = DB::queryOne(
            "SELECT id, status, gateway_ref, created_at FROM ussd_transactions WHERE id = ?",
            [$transactionId]
        );
        if (!$row) {
            return ['status' => 'unknown', 'credited' => false, 'checked' => false, 'error' => 'Transaction not found'];
        }
        if ($row['status'] !== 'pending') {
            return ['status' => $row['status'], 'credited' => false, 'checked' => false];
        }

        $gate = self::shouldCheck($row);
        if ($gate !== null) return $gate;

        $res = KopoKopo::getPaymentStatus((string)$row['gateway_ref']);
        if (empty($res['ok'])) {
            self::log("ussd #$transactionId check failed: " . ($res['error'] ?? 'unknown'));
            return ['status' => 'pending', 'credited' => false, 'checked' => true, 'error' => $res['error'] ?? null];
        }

        if (!empty($res['successful'])) {
            $credited = USSD_Wallet::complete($transactionId, $res['mpesa_ref'] ?? null);
            self::log("ussd #$transactionId SUCCESS via reconcile, credited=" . ($credited ? 'yes' : 'no (already done)'));
            return ['status' => 'completed', 'credited' => (bool)$credited, 'checked' => true];
        }

        if (!empty($res['failed'])) {
            DB::execute("UPDATE ussd_transactions SET status = 'failed' WHERE id = ? AND status = 'pending'", [$transactionId]);
            self::log("ussd #$transactionId FAILED at gateway ({$res['status']})");
            return ['status' => 'failed', 'credited' => false, 'checked' => true];
        }

        return ['status' => 'pending', 'credited' => false, 'checked' => true];
    }

    /**
     * Shared guards before spending an HTTP call on a row.
     * Returns a result array to short-circuit with, or null to go ahead.
     */
    private static function shouldCheck(array $row): ?array {
        if (trim((string)($row['gateway_ref'] ?? '')) === '') {
            // Pre-migration row, or the STK push never returned a resource URL.
            return ['status' => 'pending', 'credited' => false, 'checked' => false,
                    'error' => 'No gateway reference stored for this payment.'];
        }

        $age = time() - strtotime($row['created_at']);
        if ($age < self::MIN_AGE_SECONDS) {
            return ['status' => 'pending', 'credited' => false, 'checked' => false];
        }
        if ($age > self::GIVE_UP_MINUTES * 60) {
            return ['status' => 'pending', 'credited' => false, 'checked' => false,
                    'error' => 'Payment window elapsed.'];
        }

        return null;
    }

    /**
     * Sweep pending payments and settle them. Used by the reconciliation cron.
     *
     * @param int $limit Maximum rows of each type to examine in one run.
     * @return array{purchases_checked:int, purchases_credited:int, ussd_checked:int, ussd_credited:int}
     */
    public static function sweep(int $limit = 50): array {
        $out = ['purchases_checked' => 0, 'purchases_credited' => 0,
                'ussd_checked' => 0, 'ussd_credited' => 0];

        $minAge = self::MIN_AGE_SECONDS;
        $maxAge = self::GIVE_UP_MINUTES;

        $purchases = DB::query(
            "SELECT id FROM purchases
             WHERE status = 'pending'
               AND gateway_ref IS NOT NULL AND gateway_ref <> ''
               AND created_at <= NOW() - INTERVAL $minAge SECOND
               AND created_at >= NOW() - INTERVAL $maxAge MINUTE
             ORDER BY id ASC LIMIT $limit"
        );
        foreach ($purchases as $p) {
            $r = self::purchase((int)$p['id']);
            if (!empty($r['checked']))  $out['purchases_checked']++;
            if (!empty($r['credited'])) $out['purchases_credited']++;
        }

        $ussd = DB::query(
            "SELECT id FROM ussd_transactions
             WHERE status = 'pending'
               AND gateway_ref IS NOT NULL AND gateway_ref <> ''
               AND created_at <= NOW() - INTERVAL $minAge SECOND
               AND created_at >= NOW() - INTERVAL $maxAge MINUTE
             ORDER BY id ASC LIMIT $limit"
        );
        foreach ($ussd as $t) {
            $r = self::ussd((int)$t['id']);
            if (!empty($r['checked']))  $out['ussd_checked']++;
            if (!empty($r['credited'])) $out['ussd_credited']++;
        }

        return $out;
    }
}
