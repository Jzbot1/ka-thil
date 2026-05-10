<?php
/**
 * Google OAuth Callback Handler
 * Rewritten to use cURL (No SDK required)
 */

require_once 'includes/config.php';

// The variables $google_client_id and $google_client_secret are defined in includes/config.php
// We also need to define the redirect URI for this specific file if it's different from auth/login.php
$this_redirect_uri = BASE_URL . '/google-callback.php';

if (isset($_GET['code'])) {
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'code'          => $_GET['code'],
        'client_id'     => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri'  => $this_redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $user_info_url = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $data['access_token'];
        $user_info_json = file_get_contents($user_info_url);
        $user_info = json_decode($user_info_json, true);

        if (isset($user_info['email'])) {
            $email = $user_info['email'];
            $name = $user_info['name'] ?? '';
            $picture = $user_info['picture'] ?? '';

            // Check if user already exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                // Existing user
                $user = $result->fetch_assoc();
            } else {
                // New Google user
                $username = explode("@", $email)[0] . rand(1000, 9999);
                $random_pass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
                $role = 'user';

                // We try to be safe with column names, matching what we saw in login.php
                // and keeping referral_code/google_picture if they exist
                $cols = "username, email, password, role";
                $placeholders = "?, ?, ?, ?";
                $params = [$username, $email, $random_pass, $role];
                $types = "ssss";

                // Check for extra columns dynamically
                $res_cols = $conn->query("SHOW COLUMNS FROM users");
                $existing_cols = [];
                while($c = $res_cols->fetch_assoc()) { $existing_cols[] = $c['Field']; }

                if (in_array('referral_code', $existing_cols)) {
                    $cols .= ", referral_code";
                    $placeholders .= ", ?";
                    $params[] = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
                    $types .= "s";
                }
                if (in_array('google_picture', $existing_cols)) {
                    $cols .= ", google_picture";
                    $placeholders .= ", ?";
                    $params[] = $picture;
                    $types .= "s";
                }

                $sql = "INSERT INTO users ($cols) VALUES ($placeholders)";
                $insert = $conn->prepare($sql);
                $insert->bind_param($types, ...$params);
                $insert->execute();

                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            }

            // Set Session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['is_premium'] = $user['premium'] ?? 0;

            header("Location: index.php");
            exit();
        }
    }
}

// If something fails, redirect back to login
header("Location: auth/login.php?error=google_failed");
exit();
