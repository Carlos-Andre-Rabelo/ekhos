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
    <title>Registro - ēkhos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="navbar">
        <a href="../index.php" class="logo">ēkhos</a>
        <nav>
            <ul>
                <li><a href="../index.php">Início</a></li>
            </ul>
        </nav>
        <button class="login-btn-nav" onclick="location.href='login.php'">Login</button>
    </header>

    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h2>Criar Conta</h2>
                <a href="../index.php" class="close-btn" title="Voltar para Home">&times;</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?= $message['type'] ?>">
                    <?= htmlspecialchars($message['text']) ?>
                </div>
            <?php endif; ?>

            <form action="processa_registro.php" method="POST">
                <div class="input-group">
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" placeholder="Seu nome completo" required>
                    <span class="icon">&#128100;</span>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Seu email" required>
                    <span class="icon">&#9993;</span>
                </div>
                <div class="input-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="Crie uma senha" required>
                    <span class="icon">&#128274;</span>
                </div>
                <button type="submit" class="login-submit-btn">Registrar</button>
            </form>
            <div class="register-link">
                Já possui uma conta? <a href="login.php">Login</a>
            </div>
        </div>
    </div>
    <style>
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; color: #fff; text-align: center; }
        .message.success { background-color: #28a745; }
        .message.error { background-color: #cf6679; }
    </style>
</body>
</html>