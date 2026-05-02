<?php
/**
 * USSD Wallet Logic - Shanfix Technology
 */
class USSD_Wallet {
    /**
     * Complete a pending transaction (usually from M-Pesa Callback)
     */
    public static function complete($transactionId, $mpesaCode = null) {
        $trans = DB::queryOne("SELECT * FROM ussd_transactions WHERE id = ?", [$transactionId]);
        
        if (!$trans || $trans['status'] !== 'pending') {
            return false;
        }

        try {
            DB::beginTransaction();

            // 1. Update User Balance
            $updated = DB::execute("UPDATE users SET ussd_balance = ussd_balance + ? WHERE id = ?", [$trans['amount'], $trans['user_id']]);

            // 2. Mark Transaction as Completed
            $marked = DB::execute("UPDATE ussd_transactions SET status = 'completed', reference = ? WHERE id = ?", [$mpesaCode ?? $trans['reference'], $transactionId]);

            if ($updated !== false && $marked !== false) {
                DB::commit();
                
                // Notify User
                notify($trans['user_id'], 'USSD Top Up Successful', "Your USSD wallet has been topped up with KES " . number_format($trans['amount'], 2), 'success');
                
                return true;
            } else {
                DB::rollback();
                return false;
            }
        } catch (Exception $e) {
            DB::rollback();
            error_log("USSD_Wallet::complete Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform a manual balance adjustment (Admin tool)
     */
    public static function manualAdjustment($userId, $amount, $type, $description) {
        try {
            DB::beginTransaction();

            if ($type === 'credit') {
                $updated = DB::execute("UPDATE users SET ussd_balance = ussd_balance + ? WHERE id = ?", [$amount, $userId]);
            } else {
                // For debit, check if user has enough balance
                $balance = DB::queryValue("SELECT ussd_balance FROM users WHERE id = ?", [$userId]);
                if ($balance < $amount) {
                    DB::rollback();
                    return false;
                }
                $updated = DB::execute("UPDATE users SET ussd_balance = ussd_balance - ? WHERE id = ?", [$amount, $userId]);
            }

            // Record the transaction as 'completed' immediately
            $transId = DB::insert("
                INSERT INTO ussd_transactions (user_id, amount, type, status, description, reference, created_at)
                VALUES (?, ?, ?, 'completed', ?, 'MANUAL', NOW())
            ", [$userId, $amount, $type, $description]);

            if ($updated !== false && $transId) {
                DB::commit();
                notify($userId, 'USSD Wallet Adjusted', "Your USSD wallet has been adjusted: $description", 'info');
                return true;
            } else {
                DB::rollback();
                return false;
            }
        } catch (Exception $e) {
            DB::rollback();
            error_log("USSD_Wallet::manualAdjustment Error: " . $e->getMessage());
            return false;
        }
    }
}
