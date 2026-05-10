<?php

// --- SECURITY HEADERS AND SESSION SETUP ---
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:;");

include 'db1.php'; // Ensure this path is correct

session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
]);
session_start();

// --- VARIABLES FOR MESSAGES ---
$message = '';
$message_type = ''; // 'success' or 'error'

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 1. SEND OTP LOGIC ---
    if (isset($_POST['send_otp'])) {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

        if ($email) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $otp = rand(100000, 999999);
                $_SESSION['otp'] = $otp;
                $_SESSION['reset_email'] = $email;
                $_SESSION['otp_sent_time'] = time(); // For OTP expiration (optional)

                $subject = 'Your Password Reset OTP';
                $message_body = '<html><body>';
                $message_body .= '<h2>Password Reset Request</h2>';
                $message_body .= '<p>Your One-Time Password (OTP) is: <strong>' . $otp . '</strong></p>';
                $message_body .= '<p>This OTP is valid for 10 minutes. If you did not request this, please ignore this email.</p>';
                $message_body .= '</body></html>';

                $headers = "From: no-reply@jzstore.in\r\n" .
                           "Reply-To: no-reply@jzstore.in\r\n" .
                           "Content-Type: text/html; charset=UTF-8\r\n" .
                           "X-Mailer: PHP/" . phpversion();

                if (mail($email, $subject, $message_body, $headers)) {
                    $_SESSION['otp_form_visible'] = true;
                    $message = 'An OTP has been sent to your email. Please check your inbox and spam folder.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to send OTP. Please try again later.';
                    $message_type = 'error';
                }
            } else {
                $message = 'No account found with that email address.';
                $message_type = 'error';
            }
        } else {
             $message = 'Invalid email format.';
             $message_type = 'error';
        }
    }

    // --- 2. RESET PASSWORD LOGIC ---
    if (isset($_POST['reset_password'])) {
        $entered_otp = $_POST['otp'];
        $new_password = $_POST['new_password'];

        // Basic validation
        if (empty($entered_otp) || empty($new_password)) {
             $message = 'Please fill in all fields.';
             $message_type = 'error';
        } elseif (!isset($_SESSION['otp']) || $entered_otp != $_SESSION['otp']) {
            $message = 'Invalid or expired OTP. Please try again.';
            $message_type = 'error';
        } else {
            // OTP is correct, proceed to update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            
            if ($stmt->execute([$hashed_password, $_SESSION['reset_email']])) {
                $message = "Password reset successfully! You can now <a href='login.php'>log in</a> with your new password.";
                $message_type = 'success';
                // Clear session variables to prevent reuse and hide the OTP form
                unset($_SESSION['otp'], $_SESSION['reset_email'], $_SESSION['otp_sent_time'], $_SESSION['otp_form_visible']);
            } else {
                $message = 'An error occurred while resetting your password. Please try again.';
                $message_type = 'error';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - JZ Store</title>
    <link rel="icon" href="https://jzstore.in/logo/jzstorelogo.jpg">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-CFKMZFB02S"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-CFKMZFB02S');
    </script>

    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background-attachment: fixed;
            color: #ffffff;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent; 
        }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.1); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
        }
        .input-field {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff !important;
            backdrop-filter: blur(4px);
            width: 100%;
            padding: 1rem 1.25rem;
            border-radius: 1.25rem;
            outline: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s;
        }
        .input-field:focus {
            background: rgba(255, 255, 255, 0.1) !important;
            border-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05);
        }
        .bg-blob { 
            position: absolute; 
            width: 400px; 
            height: 400px; 
            background: linear-gradient(135deg, rgba(8, 32, 62, 0.5) 0%, rgba(85, 124, 147, 0.5) 100%); 
            filter: blur(80px); 
            border-radius: 50%; 
            z-index: -1;
            animation: float 20s infinite alternate;
        }
        @keyframes float {
            from { transform: translate(-10%, -10%) scale(1); }
            to { transform: translate(10%, 10%) scale(1.1); }
        }
        .btn-theme {
            width: 100%;
            background: #ffffff;
            color: #0f172a;
            padding: 1.125rem;
            border-radius: 1.25rem;
            font-weight: 900;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.3s;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .btn-theme:active { transform: scale(0.97); }
    </style>
</head>
<body class="pb-32 antialiased relative min-h-screen">
    <div class="bg-blob" style="top: -100px; left: -100px;"></div>
    <div class="bg-blob" style="bottom: -100px; right: -100px; animation-delay: -5s;"></div>

    <header class="fixed top-0 w-full z-40 glass-panel h-16 border-b-0">
        <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
            <a href="auth/login.php" class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center border border-white/10 transition active:scale-90">
                <i class="fa-solid fa-arrow-left text-white text-sm"></i>
            </a>
            <div class="font-bold text-lg text-white font-dynapuff tracking-wider">Reset Pass</div>
            <div class="w-10"></div> 
        </div>
    </header>

    <main class="max-w-md mx-auto pt-24 px-5 w-full">
        <div class="flex flex-col items-center justify-center mb-10">
            <div class="w-24 h-24 rounded-[2rem] overflow-hidden border-2 border-white/50 shadow-2xl mb-6 bg-white/40 p-1">
                <img src="https://jzstore.in/logo/jzstorelogo.jpg" alt="Logo" class="w-full h-full object-cover rounded-[1.8rem]">
            </div>
            <h1 class="text-3xl font-black text-white font-dynapuff tracking-tight">Security First</h1>
            <p class="text-[11px] font-bold text-white/40 mt-2 uppercase tracking-[0.2em]">Recover your account access</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 <?php echo $message_type === 'success' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20'; ?> px-5 py-4 rounded-[1.5rem] border text-xs font-bold flex items-center gap-3">
                <i class="fa-solid <?php echo $message_type === 'success' ? 'fa-circle-check text-base' : 'fa-circle-exclamation text-base'; ?>"></i> 
                <span class="flex-1"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="glass-panel rounded-[2.5rem] p-8 mb-8">
            <?php if (!isset($_SESSION['otp_form_visible'])): ?>
                <h2 class="text-lg font-black text-white mb-2">Forgot Password?</h2>
                <p class="text-[11px] font-bold text-white/50 mb-8 leading-relaxed uppercase tracking-wider">Enter your registered email address to receive a 6-digit OTP.</p>
                
                <form method="POST" action="reset_password.php" class="space-y-6">
                    <div>
                        <label class="text-[10px] uppercase font-black text-white/40 ml-2 mb-2 block tracking-widest">Email Address</label>
                        <input type="email" name="email" required class="input-field" placeholder="your@email.com">
                    </div>
                    <button type="submit" name="send_otp" class="btn-theme flex items-center justify-center gap-3">
                        <span>Send OTP</span>
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                    </button>
                </form>
            <?php else: ?>
                <h2 class="text-lg font-black text-white mb-2">Verify OTP</h2>
                <p class="text-[11px] font-bold text-white/50 mb-8 leading-relaxed uppercase tracking-wider">An OTP has been sent to your email. Enter it below to reset.</p>
                
                <form method="POST" action="reset_password.php" class="space-y-6">
                    <div>
                        <label class="text-[10px] uppercase font-black text-white/40 ml-2 mb-2 block tracking-widest">Verification Code</label>
                        <input type="text" name="otp" required class="input-field text-center text-lg tracking-[0.5em]" placeholder="000000" maxlength="6">
                    </div>
                    <div>
                        <label class="text-[10px] uppercase font-black text-white/40 ml-2 mb-2 block tracking-widest">New Password</label>
                        <input type="password" name="new_password" required class="input-field" placeholder="••••••••">
                    </div>
                    <button type="submit" name="reset_password" class="btn-theme flex items-center justify-center gap-3">
                        <span>Reset Password</span>
                        <i class="fa-solid fa-shield-check text-xs"></i>
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-8 text-center border-t border-white/10 pt-8">
                <a href="auth/login.php" class="text-sm font-black text-white flex items-center justify-center gap-2 transition hover:scale-105 active:scale-95">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                    <span>BACK TO LOGIN</span>
                </a>
            </div>
        </div>
    </main>

    <nav class="fixed bottom-0 w-full glass-panel z-50 h-16 flex justify-around items-center max-w-md left-1/2 -translate-x-1/2 border-t-0 shadow-[0_-8px_30px_rgba(0,0,0,0.04)]">
        <a href="https://wa.me/918730063275" class="flex flex-col items-center text-white/40 hover:text-white transition">
            <i class="fa-brands fa-whatsapp text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Support</span>
        </a>
        <a href="smm_orders" class="flex flex-col items-center text-white/40 hover:text-white transition">
            <i class="fa-solid fa-receipt text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Orders</span>
        </a>
        <div class="relative -top-4">
            <a href="index.php" class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-xl shadow-white/5 text-black border-4 border-[#08203E] transition active:scale-90">
                <i class="fa-solid fa-house text-sm"></i>
            </a>
        </div>
        <a href="wallet" class="flex flex-col items-center text-white/40 hover:text-white transition">
            <i class="fa-solid fa-wallet text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Wallet</span>
        </a>
        <a href="auth/login.php" class="flex flex-col items-center text-white">
            <i class="fa-solid fa-circle-user text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Login</span>
        </a>
    </nav>
</body>
</html>