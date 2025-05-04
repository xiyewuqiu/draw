<?php
session_start();
// 默认管理员凭据 - 生产环境中应使用更安全的方式
$admin_username = "admin";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT); // 使用默认密码

// 检查是否已登录
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// 检查登录表单提交
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        // 验证凭据 (实际应用中应从数据库验证)
        if ($username === $admin_username && password_verify($password, $admin_password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header("Location: index.php");
            exit;
        } else {
            $login_error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI画图生成器 - 管理员登录</title>
    <link rel="icon" type="image/png" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <style>
        :root {
            --primary-color: #7c5dfa;
            --primary-light: #9d89fc;
            --primary-gradient: linear-gradient(135deg, #7c5dfa, #9d89fc);
            --secondary-color: #ff8a00;
            --dark-color: #252945;
            --white: #ffffff;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --card-shadow: 0 8px 30px rgba(0,0,0,0.12);
            --input-shadow: 0 2px 10px rgba(0,0,0,0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --border-radius: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'PingFang SC', 'Microsoft YaHei', 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f9fafe;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top right, rgba(157, 137, 252, 0.1), transparent 70%),
                        radial-gradient(circle at bottom left, rgba(56, 190, 201, 0.1), transparent 70%);
            z-index: -1;
            pointer-events: none;
        }
        
        .login-container {
            width: 420px;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--primary-gradient);
        }
        
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .login-logo i {
            font-size: 2.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 1rem;
        }
        
        .login-logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .login-form {
            margin-top: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            background-color: var(--white);
            transition: var(--transition);
            box-shadow: var(--input-shadow);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(124, 93, 250, 0.1);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-300);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .input-group i:hover {
            color: var(--dark-color);
        }
        
        .btn-submit {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: var(--border-radius);
            background: var(--primary-gradient);
            color: var(--white);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(124, 93, 250, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(124, 93, 250, 0.4);
        }
        
        .error-message {
            color: #ef4565;
            margin-top: 1rem;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            background-color: rgba(239, 69, 101, 0.1);
            font-weight: 500;
            display: <?php echo $login_error ? 'block' : 'none'; ?>;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .back-to-site {
            margin-top: 2rem;
            font-size: 0.9rem;
        }
        
        .back-to-site a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-to-site a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-paintbrush"></i>
            <h1>AI画图管理系统</h1>
        </div>
        
        <div class="login-form">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">管理员账号</label>
                    <div class="input-group">
                        <input type="text" id="username" name="username" class="form-control" placeholder="请输入管理员账号" required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">管理员密码</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="请输入管理员密码" required>
                        <i class="fas fa-eye-slash toggle-password"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> 登录管理系统
                </button>
                
                <div class="error-message"><?php echo $login_error; ?></div>
            </form>
        </div>
        
        <div class="back-to-site">
            <a href="../index.html"><i class="fas fa-arrow-left"></i> 返回前台网站</a>
        </div>
    </div>
    
    <script>
        // 密码显示/隐藏功能
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html> 