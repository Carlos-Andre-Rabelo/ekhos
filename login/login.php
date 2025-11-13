<?php
session_start();

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ēkhos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <a href="../index.php" class="page-logo">ēkhos</a>
        <div class="login-box">
            <div id="login-form-wrapper" class="form-wrapper active">
                <div class="form-header">
                <h2>Login</h2>
                <a href="../index.php" class="close-btn" title="Voltar para Home">&times;</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?= $message['type'] ?>">
                    <?= htmlspecialchars($message['text']) ?>
                </div>
            <?php endif; ?>

            <form action="processa_login.php" method="POST">
                <div class="input-group">
                    <label for="email">Email</label>
                    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></span>
                    <input type="email" id="login-email" name="email" placeholder="Seu email" required>
                </div>
                <div class="input-group">
                    <label for="password">Senha</label>
                    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
                    <input type="password" id="login-password" name="password" placeholder="Sua senha" required>
                    <button type="button" class="password-toggle-btn" aria-label="Mostrar senha">
                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </button>
                </div>

                <button type="submit" class="login-submit-btn">Login</button>
            </form>
            <div class="switch-link">
                Não tem uma conta? <a href="#" id="show-register">Registre-se!</a>
            </div>
            </div>

            <!--formulario-->
            <div id="register-form-wrapper" class="form-wrapper">
                <div class="form-header">
                <h2>Criar Conta</h2>
                <a href="../index.php" class="close-btn" title="Voltar para Home">&times;</a>
            </div>
            
            <form action="processa_registro.php" method="POST">
                <div class="input-group">
                    <label for="name">Nome</label>
                    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
                    <input type="text" id="register-name" name="name" placeholder="Seu nome completo" required>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></span>
                    <input type="email" id="register-email" name="email" placeholder="Seu email" required>
                </div>
                <div class="input-group">
                    <label for="password">Senha</label>
                    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
                    <input type="password" id="register-password" name="password" placeholder="Crie uma senha" required>
                    <button type="button" class="password-toggle-btn" aria-label="Mostrar senha">
                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </button>
                </div>
                <button type="submit" class="login-submit-btn">Registrar</button>
            </form>
            <div class="switch-link">
                Já possui uma conta? <a href="#" id="show-login">Login</a>
            </div>
        </div>
        </div>
    </div>

    <style>
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; color: #fff; text-align: center; }
        .message.success { background-color: #28a745; }
        .message.error { background-color: #cf6679; }
    </style>

    <script>
        //alterna login registro
        const loginWrapper = document.getElementById('login-form-wrapper');
        const registerWrapper = document.getElementById('register-form-wrapper');
        const showRegisterLink = document.getElementById('show-register');
        const showLoginLink = document.getElementById('show-login');

        const switchForms = (hide, show) => {
            hide.classList.remove('active');
            hide.style.display = 'none';
            show.style.display = 'flex';
            show.classList.add('active');
        };

        showRegisterLink.addEventListener('click', (e) => {
            e.preventDefault();
            switchForms(loginWrapper, registerWrapper);
        });

        showLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            switchForms(registerWrapper, loginWrapper);
        });

        document.addEventListener('DOMContentLoaded', () => {
            if (window.location.hash === '#register') {
                switchForms(loginWrapper, registerWrapper);
            }
        });

        //mostrar ocultar senha
        document.querySelectorAll('.password-toggle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const passwordInput = this.previousElementSibling;
                const eyeOpen = this.querySelector('.eye-open');
                const eyeClosed = this.querySelector('.eye-closed');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeOpen.style.display = 'none';
                    eyeClosed.style.display = 'block';
                } else {
                    passwordInput.type = 'password';
                    eyeOpen.style.display = 'block';
                    eyeClosed.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>