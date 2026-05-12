<?php
/**
 * includes/VirtualCardSystem.php
 * Internal Virtual Debit Card System for JZStore
 */

class VirtualCardSystem {
    private $conn;
    private $enc_key;

    public function __construct($db_conn) {
        $this->conn = $db_conn;
        $this->enc_key = env('CARD_ENCRYPTION_KEY', 'default_secret_key_123456');
    }

    /**
     * Generate a new virtual card using Luhn algorithm
     */
    public function generateCard($user_id, $pin, $daily_limit = 5000.00) {
        $card_number = $this->generateLuhnNumber('4532'); // Starting with 4532 (Visa style for internal)
        $cvv = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        $expiry_month = date('n');
        $expiry_year = date('Y') + 3; // 3 years validity
        
        $cvv_encrypted = $this->encrypt($cvv);
        $pin_hash = password_hash($pin, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare("INSERT INTO virtual_cards (user_id, card_number, cvv_encrypted, expiry_month, expiry_year, pin_hash, daily_limit) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisd", $user_id, $card_number, $cvv_encrypted, $expiry_month, $expiry_year, $pin_hash, $daily_limit);
        
        if ($stmt->execute()) {
            return [
                'ok' => true,
                'card_number' => $card_number,
                'cvv' => $cvv,
                'expiry' => sprintf('%02d/%d', $expiry_month, $expiry_year % 100)
            ];
        }
        return ['ok' => false, 'err' => $stmt->error];
    }

    private function generateLuhnNumber($prefix) {
        $number = $prefix;
        while (strlen($number) < 15) {
            $number .= rand(0, 9);
        }
        
        // Calculate check digit
        $sum = 0;
        for ($i = 0; $i < strlen($number); $i++) {
            $digit = (int)$number[$i];
            if ((strlen($number) - $i) % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) $digit -= 9;
            }
            $sum += $digit;
        }
        $check_digit = (10 - ($sum % 10)) % 10;
        return $number . $check_digit;
    }

    private function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $this->enc_key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public function decrypt($data) {
        if (!$data) return '';
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->enc_key, 0, $iv);
    }

    public function validateCardPayment($card_number, $expiry_month, $expiry_year, $cvv, $pin, $amount) {
        $stmt = $this->conn->prepare("SELECT * FROM virtual_cards WHERE card_number = ? LIMIT 1");
        $stmt->bind_param("s", $card_number);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$card) return ['ok' => false, 'err' => 'Card not found'];
        if ($card['status'] !== 'active') return ['ok' => false, 'err' => 'Card is ' . $card['status']];
        if ($card['expiry_month'] != $expiry_month || $card['expiry_year'] != $expiry_year) return ['ok' => false, 'err' => 'Invalid expiry date'];
        
        if ($this->decrypt($card['cvv_encrypted']) !== $cvv) {
            return ['ok' => false, 'err' => 'Invalid CVV'];
        }

        if (!password_verify($pin, $card['pin_hash'])) {
            $this->conn->query("UPDATE virtual_cards SET wrong_pin_attempts = wrong_pin_attempts + 1 WHERE id = " . $card['id']);
            return ['ok' => false, 'err' => 'Incorrect PIN'];
        }

        // Reset wrong attempts on success
        $this->conn->query("UPDATE virtual_cards SET wrong_pin_attempts = 0 WHERE id = " . $card['id']);

        // Check Daily Limit
        $stmt = $this->conn->prepare("SELECT SUM(amount) as spent FROM card_transactions WHERE card_id = ? AND status = 'success' AND created_at >= CURDATE()");
        $stmt->bind_param("i", $card['id']);
        $stmt->execute();
        $spent = (float)($stmt->get_result()->fetch_assoc()['spent'] ?? 0);
        $stmt->close();

        if (($spent + $amount) > $card['daily_limit']) {
            return ['ok' => false, 'err' => 'Daily spending limit exceeded'];
        }

        // Check Wallet Balance
        $stmt = $this->conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $card['user_id']);
        $stmt->execute();
        $balance = (float)($stmt->get_result()->fetch_assoc()['wallet_balance'] ?? 0);
        $stmt->close();

        if ($balance < $amount) {
            return ['ok' => false, 'err' => 'Insufficient wallet balance'];
        }

        return ['ok' => true, 'card' => $card];
    }

    public function processTransaction($card_id, $user_id, $amount, $merchant = 'Internal Store') {
        $this->conn->begin_transaction();
        try {
            // Deduct balance
            $stmt = $this->conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $user_id);
            $stmt->execute();

            // Log transaction
            $stmt = $this->conn->prepare("INSERT INTO card_transactions (card_id, user_id, amount, merchant, status) VALUES (?, ?, ?, ?, 'success')");
            $stmt->bind_param("iids", $card_id, $user_id, $amount, $merchant);
            $stmt->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
}
