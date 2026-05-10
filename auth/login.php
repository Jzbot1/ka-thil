<?php
// Display errors during development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Secure session setup (60 Days Persistence) ---
$session_lifetime = 60 * 24 * 60 * 60; // 60 days in seconds
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

// --- Auto-redirect if already logged in (FIXED TO ROOT PATH) ---
if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// --- Security headers ---
header(
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com https://code.jquery.com https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://accounts.google.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: blob: https://jzstore.in https://cdn.moogold.com https://lh3.googleusercontent.com https://www.svgrepo.com https://kevosofficial.shop https://img.icons8.com; " .
    "frame-src https://www.google.com https://accounts.google.com;"
);

// --- Include DB connection ---
// --- Include Config (which loads DB and Functions) ---
require_once dirname(__DIR__) . '/includes/config.php';

// --- Fetch Store Settings via Central Helper ---
$setting = get_settings();

// Fallback for logo if not set explicitly
$store_logo = !empty($setting['fav_icon']) ? $setting['fav_icon'] : 'https://jzstore.in/logo/jzstorelogo.jpg';
if (strpos($store_logo, 'http') !== 0) $store_logo = BASE_URL . '/' . ltrim($store_logo, '/');


$error = '';
$success = '';

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success = 'Password reset successful. Please log in.';
}

// --- 1. HANDLE GOOGLE CALLBACK ---
if (isset($_GET['code'])) {
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'code' => $_GET['code'],
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri' => $google_redirect_uri,
        'grant_type' => 'authorization_code'
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
        $user_info = json_decode(file_get_contents($user_info_url), true);

        if (isset($user_info['email'])) {
            $email = $user_info['email'];
            $username_base = explode('@', $email)[0];

            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
            } else {
                $username = $username_base . rand(100, 999);
                $random_pass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
                $role = 'user';

                $insert = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $insert->bind_param("ssss", $username, $email, $random_pass, $role);
                $insert->execute();
                
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            }

            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['is_premium'] = isset($user['premium']) ? $user['premium'] : 0;
            
            // FIXED TO ROOT PATH
            header("Location: /index.php");
            exit();
        }
    } else {
        $error = "Google Login failed.";
    }
}

// --- 2. GENERATE GOOGLE AUTH URL ---
$google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $google_client_id,
    'redirect_uri' => $google_redirect_uri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online'
]);

// --- 3. HANDLE REGULAR POST LOGIN ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_GET['code'])) {
    $email = trim($_POST['email']);
    $password = (string) $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['is_premium'] = isset($user['premium']) ? $user['premium'] : 0;
            
            // FIXED TO ROOT PATH
            header("Location: /index.php");
            exit();
        } else {
            $error = "Invalid login credentials.";
        }
    } else {
        $error = "Invalid login credentials.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Log In - <?php echo htmlspecialchars($setting['store_name']); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($setting['fav_icon']); ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        themePink: '#fbc2eb',
                        themeBlue: '#a6c1ee',
                        themeGreen: '#80bf15',
                        themeDark: '#0f172a',
                        midnight: '#0B1635',
                        card: 'rgba(255, 255, 255, 0.4)',
                        accent: '#0f172a', 
                        gold: '#0f172a'
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
            background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15); 
            background-attachment: fixed;
            color: #0f172a;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent; 
        }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.4); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }
        .input-field {
            background: rgba(255, 255, 255, 0.3) !important;
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: #0f172a !important;
            backdrop-filter: blur(4px);
        }
        .input-field:focus {
            background: rgba(255, 255, 255, 0.5) !important;
            border-color: #0f172a;
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.05);
        }
        .bg-blob { 
            position: absolute; 
            width: 400px; 
            height: 400px; 
            background: linear-gradient(135deg, rgba(251, 194, 235, 0.5) 0%, rgba(166, 193, 238, 0.5) 100%); 
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
    <div class="bg-blob" style="top: -100px; left: -100px;"></div>
    <div class="bg-blob" style="bottom: -100px; right: -100px; animation-delay: -5s;"></div>

    <header class="fixed top-0 w-full z-40 glass-panel h-16 border-b-0">
        <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
            <a href="<?= BASE_URL ?>" class="w-10 h-10 rounded-2xl bg-white/40 flex items-center justify-center border border-white/30 transition active:scale-90">
                <i class="fa-solid fa-arrow-left text-themeDark text-sm"></i>
            </a>
            <div class="font-bold text-lg text-themeDark font-dynapuff tracking-wider"><?php echo htmlspecialchars($setting['store_name']); ?></div>
            <div class="w-10"></div> 
        </div>
    </header>

    <main class="max-w-md mx-auto pt-24 px-5 min-h-screen">
        <div class="flex flex-col items-center justify-center mb-10 animate-slide-up">
            <div class="w-24 h-24 rounded-[2rem] overflow-hidden border-2 border-white/50 shadow-2xl mb-6 bg-white/40 p-1">
                <img src="<?php echo htmlspecialchars($store_logo); ?>" alt="Logo" class="w-full h-full object-cover rounded-[1.8rem]">
            </div>
            <h1 class="text-3xl font-black text-themeDark font-dynapuff tracking-tight">Welcome Back</h1>
            <p class="text-[11px] font-bold text-themeDark/40 mt-2 uppercase tracking-[0.2em]">Sign in to your account</p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="mb-6 bg-emerald-500/10 text-emerald-600 px-5 py-4 rounded-[1.5rem] border border-emerald-500/20 text-xs font-bold flex items-center gap-3">
                <i class="fa-solid fa-circle-check text-base"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-rose-500/10 text-rose-600 px-5 py-4 rounded-[1.5rem] border border-rose-500/20 text-xs font-bold flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-base"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel rounded-[2.5rem] p-8 mb-8 animate-slide-up" style="animation-delay: 0.1s;">
            <form method="POST" action="">
                <div class="mb-5">
                    <label class="text-[10px] uppercase font-black text-themeDark/40 ml-2 mb-2 block tracking-widest">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-4 top-4 text-themeDark/40"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" name="email" required 
                            class="input-field w-full rounded-2xl pl-12 pr-4 py-4 text-sm font-semibold transition outline-none" 
                            placeholder="your@email.com">
                    </div>
                </div>

                <div class="mb-8">
                    <label class="text-[10px] uppercase font-black text-themeDark/40 ml-2 mb-2 block tracking-widest">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-4 text-themeDark/40"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" required 
                            class="input-field w-full rounded-2xl pl-12 pr-12 py-4 text-sm font-semibold transition outline-none" 
                            placeholder="••••••••">
                        <button type="button" id="togglePassword" class="absolute right-4 top-4 text-themeDark/40 hover:text-themeDark transition">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div class="text-right mt-3">
                        <a href="reset_password.php" class="text-[11px] font-bold text-themeDark/60 hover:text-themeDark transition">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" class="w-full bg-themeDark text-white py-4.5 rounded-2xl font-black text-sm shadow-xl shadow-themeDark/20 active:scale-[0.97] transition-all flex items-center justify-center gap-3">
                    <span>SIGN IN</span>
                    <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                </button>

                <div class="relative my-10">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-themeDark/5"></div>
                    </div>
                    <div class="relative flex justify-center text-[10px]">
                        <span class="px-4 bg-transparent text-themeDark/30 font-black tracking-[0.3em] uppercase">OR CONTINUE WITH</span>
                    </div>
                </div>

                <a href="<?php echo $google_login_url; ?>" class="w-full bg-white/60 hover:bg-white text-themeDark py-4 rounded-2xl font-bold active:scale-[0.97] transition-all flex items-center justify-center gap-3 shadow-sm border border-white/40">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="w-5 h-5">
                    <span class="text-xs font-black uppercase tracking-wider">Google Account</span>
                </a>

                <div class="mt-10 text-center border-t border-themeDark/5 pt-8">
                    <p class="text-[11px] font-bold text-themeDark/40 uppercase tracking-widest">Don't have an account?</p>
                    <a href="register.php" class="text-sm font-black text-themeDark mt-2 block transition hover:scale-105 active:scale-95">CREATE NEW ACCOUNT</a>
                </div>
            </form>
        </div>
    </main>

    <nav class="fixed bottom-0 w-full glass-panel z-50 h-16 flex justify-around items-center max-w-md left-1/2 -translate-x-1/2 border-t-0 shadow-[0_-8px_30px_rgba(0,0,0,0.04)]">
        <a href="<?php echo htmlspecialchars($setting['whatsapp'] ?? 'https://wa.me/'); ?>" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
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
        <a href="#" class="flex flex-col items-center text-themeDark">
            <i class="fa-solid fa-circle-user text-lg"></i>
            <span class="text-[9px] font-black uppercase tracking-tighter mt-1">Login</span>
        </a>
    </nav>

    <script>
        // Password Visibility Toggle
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
    </script>
</body>
</html>