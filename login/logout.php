<?php
declare(strict_types=1);

// Garante que a sessão seja iniciada antes de ser manipulada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis de sessão.
$_SESSION = [];

// Destrói a sessão.
session_destroy();

// Redireciona o usuário para a página inicial.
header('Location: ../index.php');
exit;