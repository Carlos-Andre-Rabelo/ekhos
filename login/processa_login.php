<?php
declare(strict_types=1);

session_start();

// Carrega o arquivo de conexão central
require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Email e senha são obrigatórios.'];
    header('Location: login.php');
    exit;
}

try {
    // --- CORREÇÃO AQUI ---
    // Apagamos a criação manual do client.
    // Usamos direto a variável $database que veio do db_connect.php
    $collection = $database->selectCollection('clientes');

    $user = $collection->findOne(['email_cliente' => $email]);

    if (!$user) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Email ou senha inválidos.'];
        header('Location: login.php');
        exit;
    }

    if (password_verify($password, (string)($user['senha_cliente'] ?? ''))) {
        $_SESSION['user_id'] = (string)$user['_id'];
        $_SESSION['user_name'] = (string)$user['nome_cliente'];

        $isAdmin = isset($user['adm']) && $user['adm'] === true;
        $_SESSION['user_role'] = $isAdmin ? 'admin' : 'client';

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Login realizado com sucesso!'];
        
        header('Location: ../index.php');
        exit;
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Email ou senha inválidos.'];
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro no servidor: ' . $e->getMessage()];
    header('Location: login.php');
    exit;
}