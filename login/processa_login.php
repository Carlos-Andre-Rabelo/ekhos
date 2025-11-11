<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Email e senha são obrigatórios.'];
    header('Location: login.php');
    exit;
}

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);
    $collection = $database->selectCollection('clientes');

    $user = $collection->findOne(['email_cliente' => $email]);

    if (!$user) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Email ou senha inválidos.'];
        header('Location: login.php');
        exit;
    }

    // Verifica a senha usando password_verify
    if (password_verify($password, (string)($user['senha_cliente'] ?? ''))) {
        // Login bem-sucedido
        $_SESSION['user_id'] = (string)$user['_id'];
        $_SESSION['user_name'] = (string)$user['nome_cliente'];
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Login realizado com sucesso! Bem-vindo(a), ' . htmlspecialchars($user['nome_cliente']) . '!'];
        
        // Redireciona para a página principal do sistema
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