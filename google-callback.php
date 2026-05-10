<?php
require 'vendor/autoload.php';
require 'includes/config.php';
session_start();

$client = new Google_Client();
$client->setClientId(env('GOOGLE_CLIENT_ID', ''));
$client->setClientSecret(env('GOOGLE_CLIENT_SECRET', ''));
$client->setRedirectUri('https://jzstore.in/google-callback.php');
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);
        $google_service = new Google_Service_Oauth2($client);
        $userData = $google_service->userinfo->get();

        $email = $userData['email'];
        $name = $userData['name'];
        $picture = $userData['picture'];

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Existing user
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
        } else {
            // New Google user
            $random_username = explode("@", $email)[0] . rand(1000, 9999);
            $referral_code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);

            $insert = $conn->prepare("INSERT INTO users (username, email, referral_code, google_picture) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $random_username, $email, $referral_code, $picture);
            $insert->execute();

            $_SESSION['user_id'] = $insert->insert_id;
            $_SESSION['username'] = $random_username;
            $_SESSION['email'] = $email;
        }

        header("Location: index.php");
        exit();
    }
}

header("Location: register.php");
exit();
?>
