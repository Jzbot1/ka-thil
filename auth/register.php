<?php
// Display errors during development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Secure session setup ---
$session_lifetime = 60 * 24 * 60 * 60; 
ini_set('session.gc_maxlifetime', $session_lifetime);
ini_set('session.cookie_lifetime', $session_lifetime);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Security headers ---
header(
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com https://code.jquery.com https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: blob: https://jzstore.in https://cdn.moogold.com https://kevosofficial.shop https://www.svgrepo.com https://img.icons8.com; " .
    "frame-src https://www.google.com;"
);

// --- Include Config (which loads DB and Functions) ---
require_once dirname(__DIR__) . '/includes/config.php';

// --- Fetch Store Settings via Central Helper ---
$setting = get_settings();

// Fallback for logo if not set explicitly
$store_logo = !empty($setting['fav_icon']) ? $setting['fav_icon'] : 'https://jzstore.in/logo/jzstorelogo.jpg';
if (strpos($store_logo, 'http') !== 0) $store_logo = BASE_URL . '/' . ltrim($store_logo, '/');


// --- Helper Functions ---
function generateUniqueReferralCode($conn, $length = 8) {
    do {
        $code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->store_result();
    } while ($stmt->num_rows > 0);
    $stmt->close();
    return $code;
}

function rewardReferrer($conn, $referrer_id, $reward_amount = 1.00) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $referrer_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $new_balance = $user['wallet_balance'] + $reward_amount;
            $update = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
            $update->bind_param("di", $new_balance, $referrer_id);
            $update->execute();
        }
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

$message = '';
$message_type = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $referral_code = trim($_POST['referral_code']);

    if (empty($email) || empty($username) || empty($password)) {
        $message = "Please fill out all fields.";
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "Email or Username already exists.";
            $message_type = 'error';
        } else {
            $referrer_id = null;
            if (!empty($referral_code)) {
                $ref_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
                $ref_stmt->bind_param("s", $referral_code);
                $ref_stmt->execute();
                if ($referrer = $ref_stmt->get_result()->fetch_assoc()) {
                    $referrer_id = $referrer['id'];
                } else {
                    $message = "Invalid referral code.";
                    $message_type = 'error';
                }
            }

            if ($message_type !== 'error') {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $newCode = generateUniqueReferralCode($conn);
                $insert = $conn->prepare("INSERT INTO users (username, email, password, referral_code, referred_by_id) VALUES (?, ?, ?, ?, ?)");
                $insert->bind_param("ssssi", $username, $email, $hashed_password, $newCode, $referrer_id);

                if ($insert->execute()) {
                    if ($referrer_id) rewardReferrer($conn, $referrer_id);
                    $message = "Account created! Redirecting to login...";
                    $message_type = 'success';
                    header("refresh:2;url=login.php");
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign Up - <?php echo htmlspecialchars($store_settings['store_name']); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($store_settings['fav_icon']); ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        themePink: '#08203E',
                        themeBlue: '#557C93',
                        themeGreen: '#80bf15',
                        themeDark: '#ffffff',
                        midnight: '#08203E', 
                        card: 'rgba(255, 255, 255, 0.1)', 
                        accent: '#ffffff', 
                        gold: '#ffffff' 
                    },
                    fontFamily: { 
                        poppins: ['Poppins', 'sans-serif'], 
                        dynapuff: ['DynaPuff', 'cursive'] 
                    },
                    animation: { 'slide-up': 'slideUp 0.3s ease-out forwards' },
                    keyframes: { slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } } }
                }
            }
        }
    </script>

    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid: DXImageTransform.Microsoft.gradient( startColorstr="#08203E", endColorstr="#557C93", GradientType=1 );
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
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
        }
        .input-field {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff !important;
            backdrop-filter: blur(4px);
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
    </style>
</head>
<body class="pb-32 antialiased relative">
    <div class="bg-blob" style="top: -100px; right: -100px;"></div>
    <div class="bg-blob" style="bottom: -100px; left: -100px; animation-delay: -5s;"></div>

    <header class="fixed top-0 w-full z-40 glass-panel h-16 border-b-0">
        <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
            <a href="login.php" class="w-10 h-10 rounded-2xl bg-white/40 flex items-center justify-center border border-white/30 transition active:scale-90">
                <i class="fa-solid fa-arrow-left text-themeDark text-sm"></i>
            </a>
            <div class="font-bold text-lg text-themeDark font-dynapuff tracking-wider">Join Us</div>
            <div class="w-10"></div> 
        </div>
    </header>

    <main class="max-w-md mx-auto pt-24 px-5 min-h-screen">
        <div class="flex flex-col items-center justify-center mb-10 animate-slide-up">
            <div class="w-24 h-24 rounded-[2rem] overflow-hidden border-2 border-white/50 shadow-2xl mb-6 bg-white/40 p-1">
                <img src="<?php echo htmlspecialchars($store_logo); ?>" alt="Logo" class="w-full h-full object-cover rounded-[1.8rem]">
            </div>
            <h1 class="text-3xl font-black text-themeDark font-dynapuff tracking-tight">Create Account</h1>
            <p class="text-[11px] font-bold text-themeDark/40 mt-2 uppercase tracking-[0.2em]">Join the community of elite gamers</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 <?php echo $message_type === 'success' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20'; ?> px-5 py-4 rounded-[1.5rem] border text-xs font-bold flex items-center gap-3">
                <i class="fa-solid <?php echo $message_type === 'success' ? 'fa-circle-check text-base' : 'fa-circle-exclamation text-base'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel rounded-[2.5rem] p-8 mb-8 animate-slide-up" style="animation-delay: 0.1s;">
            <form method="POST" action="">
                <div class="mb-5">
                    <label class="text-[10px] uppercase font-black text-themeDark/40 ml-2 mb-2 block tracking-widest">Username</label>
                    <div class="relative">
                        <span class="absolute left-4 top-4 text-themeDark/40"><i class="fa-regular fa-user"></i></span>
                        <input type="text" name="username" required class="input-field w-full rounded-2xl pl-12 pr-4 py-4 text-sm font-semibold transition outline-none" placeholder="gamer_pro">
                    </div>
                </div>

                <div class="mb-5">
                    <label class="text-[10px] uppercase font-black text-themeDark/40 ml-2 mb-2 block tracking-widest">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-4 top-4 text-themeDark/40"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" name="email" required class="input-field w-full rounded-2xl pl-12 pr-4 py-4 text-sm font-semibold transition outline-none" placeholder="name@example.com">
                    </div>
                </div>

                <div class="mb-5">
                    <label class="text-[10px] uppercase font-black text-themeDark/40 ml-2 mb-2 block tracking-widest">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-4 text-themeDark/40"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" required class="input-field w-full rounded-2xl pl-12 pr-12 py-4 text-sm font-semibold transition outline-none" placeholder="••••••••">
                        <button type="button" class="togglePassword absolute right-4 top-4 text-themeDark/40 hover:text-themeDark transition">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="text-[10px] uppercase font-black text-themeDark/40 ml-2 mb-2 block tracking-widest">Confirm Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-4 text-themeDark/40"><i class="fa-solid fa-shield-check"></i></span>
                        <input type="password" name="confirm_password" required class="input-field w-full rounded-2xl pl-12 pr-4 py-4 text-sm font-semibold transition outline-none" placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full bg-themeDark text-white py-4.5 rounded-2xl font-black text-sm shadow-xl shadow-themeDark/20 active:scale-[0.97] transition-all flex items-center justify-center gap-3 uppercase tracking-wider">
                    <span>Create Account</span>
                    <i class="fa-solid fa-user-plus text-xs"></i>
                </button>

                <div class="mt-10 text-center border-t border-themeDark/5 pt-8">
                    <p class="text-[11px] font-bold text-themeDark/40 uppercase tracking-widest">Already a member?</p>
                    <a href="login.php" class="text-sm font-black text-themeDark mt-2 block transition hover:scale-105 active:scale-95">LOG IN INSTEAD</a>
                </div>
            </form>
        </div>
    </main>

    <nav class="fixed bottom-0 w-full glass-panel z-50 h-16 flex justify-around items-center max-w-md left-1/2 -translate-x-1/2 border-t-0 shadow-[0_-8px_30px_rgba(0,0,0,0.04)]">
        <a href="<?php echo htmlspecialchars($store_settings['whatsapp']); ?>" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
            <i class="fa-brands fa-whatsapp text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Support</span>
        </a>
        <a href="<?= BASE_URL ?>/smm_orders" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
            <i class="fa-solid fa-receipt text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Orders</span>
        </a>
        <div class="relative -top-4">
            <a href="<?= BASE_URL ?>" class="w-12 h-12 bg-themeDark rounded-full flex items-center justify-center shadow-xl shadow-themeDark/20 text-white border-4 border-white transition active:scale-90">
                <i class="fa-solid fa-house text-sm"></i>
            </a>
        </div>
        <a href="<?= BASE_URL ?>/wallet" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
            <i class="fa-solid fa-wallet text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Wallet</span>
        </a>
        <a href="login.php" class="flex flex-col items-center text-themeDark">
            <i class="fa-solid fa-circle-user text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Login</span>
        </a>
    </nav>

    <script>
        document.querySelectorAll('.togglePassword').forEach(btn => {
            btn.addEventListener('click', function () {
                const input = this.closest('.relative').querySelector('input');
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>

    <script>
        document.querySelectorAll('.togglePassword').forEach(btn => {
            btn.addEventListener('click', function () {
                const input = this.closest('.relative').querySelector('input');
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>