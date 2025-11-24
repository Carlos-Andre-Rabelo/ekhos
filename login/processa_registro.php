<?php
declare(strict_types=1);

session_start();

// Carrega a conexão central
require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Todos os campos são obrigatórios.'];
    header('Location: register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Formato de email inválido.'];
    header('Location: register.php');
    exit;
}

try {
    // --- CORREÇÃO AQUI ---
    // Usamos direto a variável $database que veio do db_connect.php
    $collection = $database->selectCollection('clientes');

    // Verifica se o email já existe
    $existingUser = $collection->findOne(['email_cliente' => $email]);
    if ($existingUser) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Este email já está cadastrado.'];
        header('Location: register.php');
        exit;
    }

    // Gera um novo _id inteiro para o cliente
    $maxId = 0;
    $lastUser = $collection->findOne([], ['sort' => ['_id' => -1]]);
    if ($lastUser) {
        $maxId = (int)$lastUser['_id'];
    }
    $newId = $maxId + 1;

    // Criptografa a senha
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $newUser = [
        '_id' => $newId,
        'nome_cliente' => $name,
        'email_cliente' => $email,
        'senha_cliente' => $hashedPassword,
        'telefones' => [],
        'compras' => [],
        'endereco' => new stdClass(), 
        'carrinho' => [],
        'role' => 'client', 
    ];

    $collection->insertOne($newUser);

    $_SESSION['message'] = ['type' => 'success', 'text' => 'Registro realizado com sucesso! Faça o login para continuar.'];
    header('Location: login.php');
    exit;
} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro no servidor: ' . $e->getMessage()];
    header('Location: register.php');
    exit;
}