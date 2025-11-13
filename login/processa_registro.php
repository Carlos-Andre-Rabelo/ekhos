<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";

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
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);
    $collection = $database->selectCollection('clientes');

    //ve se ja tem email
    $existingUser = $collection->findOne(['email_cliente' => $email]);
    if ($existingUser) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Este email já está cadastrado.'];
        header('Location: register.php');
        exit;
    }

    //id int cliente
    $maxId = 0;
    $lastUser = $collection->findOne([], ['sort' => ['_id' => -1]]);
    if ($lastUser) {
        $maxId = (int)$lastUser['_id'];
    }
    $newId = $maxId + 1;

    //cripto
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    //cria novo user
    $newUser = [
        '_id' => $newId,
        'nome_cliente' => $name,
        'email_cliente' => $email,
        'senha_cliente' => $hashedPassword,
        'telefones' => [],
        'compras' => [],
        'endereco' => new stdClass(),
        'carrinho' => [],
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