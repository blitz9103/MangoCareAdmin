<?php
require_once '../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../admin/index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        header('Location: ../admin/index.php');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .leaf {
            display: inline-block;
            animation: leafFloat 2s ease-in-out infinite;
        }

        @keyframes leafFloat {
            0% { transform: rotate(0deg) translateY(0px); }
            50% { transform: rotate(10deg) translateY(-5px); }
            100% { transform: rotate(0deg) translateY(0px); }
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 38px;
            cursor: pointer;
            font-size: 18px;
            color: #666;
            transition: 0.3s ease;
        }

        .toggle-password:hover {
            color: #2e7d32;
            transform: scale(1.1);
        }

        /* MangoCare Brand Colors */
.brand-main {
    color: #1b5e20; /* Dark Mango Green */
    font-weight: 700;
}

.brand-sub {
    background: linear-gradient(90deg, #8bc34a, #c0ca33);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
}
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
               <h1>
    <span class="leaf">🍃</span>
    <span class="brand-main">MangoCare</span>
    <span class="brand-sub">Admin</span>
</h1>
                <p>Administrator Login</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group password-wrapper">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <i id="togglePassword" class="fa-solid fa-eye toggle-password"></i>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
        </div>
    </div>

    <script>
    const togglePassword = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    togglePassword.addEventListener("mousedown", function (e) {
        e.preventDefault(); // 🔥 prevents input from getting focused
    });

    togglePassword.addEventListener("click", function () {
        if (password.type === "password") {
            password.type = "text";
            this.classList.remove("fa-eye");
            this.classList.add("fa-eye-slash");
        } else {
            password.type = "password";
            this.classList.remove("fa-eye-slash");
            this.classList.add("fa-eye");
        }
    });
</script>
</body>
</html>