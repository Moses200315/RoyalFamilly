<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Login Page
 * Version: 1.0
 * Description: Admin authentication and login interface
 * ============================================
 */

require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Initialize variables
$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/auth_functions.php';
    
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error_message = t('login_required');
    } else {
        $result = login($mysqli, $username, $password);
        
        if ($result['success']) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo currentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('sign_in'); ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow: hidden;
            background: #111827;
            font-family: "Poppins", "Segoe UI", Tahoma, sans-serif;
        }

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(90deg, rgba(11, 18, 32, .72), rgba(11, 18, 32, .2)),
                url("assets/images/royal-family-logo.jpg") center / cover no-repeat;
        }

        .login-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(2px);
            animation: loginFloat 8s ease-in-out infinite;
        }

        .login-orb:nth-child(1) {
            width: 180px;
            height: 180px;
            top: 10%;
            left: 10%;
            opacity: .35;
            background: #7c3cff;
        }

        .login-orb:nth-child(2) {
            width: 250px;
            height: 250px;
            right: 8%;
            bottom: -60px;
            opacity: .25;
            background: #00d9ff;
            animation-delay: -3s;
        }

        .login-orb:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 65%;
            left: 20%;
            opacity: .25;
            background: #ff3cac;
            animation-delay: -5s;
        }

        @keyframes loginFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -40px) scale(1.1); }
        }

        .login-box {
            position: relative;
            z-index: 1;
            width: 400px;
            max-width: calc(100% - 32px);
            padding: 45px 40px;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 25px;
            background: rgba(255, 255, 255, .08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, .45);
            animation: loginEnter 1s cubic-bezier(.17, .67, .3, 1.3);
        }

        @keyframes loginEnter {
            from { opacity: 0; transform: translateY(60px) scale(.85); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            display: block;
            object-fit: cover;
            border: 3px solid #e4b63d;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .32);
        }

        .login-box h1 {
            margin: 0 0 8px;
            color: white;
            font-size: 30px;
            text-align: center;
        }

        .login-subtitle {
            margin: 0 0 32px;
            color: #b8bdd2;
            font-size: 14px;
            text-align: center;
        }

        .login-field {
            position: relative;
            margin-bottom: 22px;
        }

        .login-field input {
            width: 100%;
            padding: 16px 18px;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 12px;
            outline: none;
            background: rgba(0, 0, 0, .2);
            color: white;
            font-size: 14px;
            transition: .3s;
        }

        .login-field input::placeholder {
            color: transparent;
        }

        .login-field input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, .12);
            transform: translateY(-2px);
        }

        .login-field label {
            position: absolute;
            top: 16px;
            left: 16px;
            color: #8e94aa;
            pointer-events: none;
            transition: .3s;
        }

        .login-field input:focus + label,
        .login-field input:not(:placeholder-shown) + label {
            top: -9px;
            left: 12px;
            padding: 0 7px;
            border-radius: 5px;
            background: #171c35;
            color: #a78bfa;
            font-size: 12px;
        }

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 5px 0 25px;
            color: #aeb4c9;
            font-size: 13px;
        }

        .login-remember {
            display: flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
        }

        .login-remember input {
            accent-color: #7c3cff;
        }

        .login-options a {
            color: #a78bfa;
            text-decoration: none;
        }

        .login-options a:hover {
            color: #00d9ff;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            border: 0;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            background: linear-gradient(90deg, #7c3cff, #00b8ff);
            background-size: 200% 100%;
            box-shadow: 0 10px 25px rgba(124, 60, 255, .3);
            transition: .4s;
            animation: loginGradient 4s infinite alternate;
        }

        @keyframes loginGradient {
            from { background-position: 0%; }
            to { background-position: 100%; }
        }

        .btn-login:hover {
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 190, 255, .35);
        }

        .btn-login:active {
            transform: scale(.97);
        }

        .login-alert {
            border: 1px solid rgba(255, 110, 130, .3);
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 12px 14px;
            color: #ffd9df;
            background: rgba(190, 40, 70, .2);
        }

        @media (max-width: 500px) {
            .login-box {
                padding: 35px 25px;
            }

            .login-box h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <main class="login-page">
        <div class="login-orb"></div>
        <div class="login-orb"></div>
        <div class="login-orb"></div>

        <section class="login-box" aria-labelledby="login-title">
            <img class="login-logo" src="assets/images/royal-family-logo.jpg" alt="Royal Family Pure Drinking Water">
            <h1 id="login-title"><?php echo currentLanguage() === 'sw' ? 'Karibu' : 'Welcome'; ?></h1>
            <p class="login-subtitle"><?php echo currentLanguage() === 'sw' ? 'Ingia kwenye akaunti yako' : 'Sign in to your account'; ?></p>

                <?php if ($error_message): ?>
                    <div class="login-alert" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="login-alert" role="status">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="login-field">
                        <input type="text" id="username" name="username" placeholder=" " required autofocus>
                        <label for="username"><?php echo t('username'); ?></label>
                    </div>

                    <div class="login-field">
                        <input type="password" id="password" name="password" placeholder=" " required>
                        <label for="password"><?php echo currentLanguage() === 'sw' ? 'Nenosiri' : 'Password'; ?></label>
                    </div>

                    <div class="login-options">
                        <label class="login-remember">
                            <input type="checkbox" id="showPassword">
                            <?php echo t('show_password'); ?>
                        </label>
                    </div>

                    <button type="submit" class="btn-login">
                        <?php echo t('sign_in'); ?> <span aria-hidden="true">&#8594;</span>
                    </button>
                </form>
        </section>
    </main>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const showPassword = document.getElementById('showPassword');
        const passwordInput = document.getElementById('password');

        if (showPassword && passwordInput) {
            showPassword.addEventListener('change', function () {
                passwordInput.type = this.checked ? 'text' : 'password';
            });
        }
    </script>
</body>
</html>
