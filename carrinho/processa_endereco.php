<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

//ve se eh cliente
if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastrar_endereco.php');
    exit;
}

$nome_bairro = trim($_POST['nome_bairro'] ?? '');
$logradouro = trim($_POST['logradouro'] ?? '');
$numero = trim($_POST['numero'] ?? '');

if (empty($nome_bairro) || empty($logradouro) || empty($numero) || !is_numeric($numero)) {
    header('Location: cadastrar_endereco.php?error=invalid_data');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";

//salva no mongo
try {
    $client = new MongoDB\Client($mongoUri);
    $collection = $client->selectDatabase($dbName)->selectCollection('clientes');
    $userId = (int)$_SESSION['user_id'];

    $updateResult = $collection->updateOne(
        ['_id' => $userId],
        ['$set' => [
            'endereco' => [
                'nome_bairro' => $nome_bairro,
                'logradouro' => $logradouro,
                'numero' => (int)$numero,
            ]
        ]]
    );

    if ($updateResult->getModifiedCount() === 1 || $updateResult->getMatchedCount() === 1) {
        header('Location: checkout.php');
        exit;
    } else {
        header('Location: checkout.php');
        exit;
    }

} catch (Exception $e) {
    header('Location: cadastrar_endereco.php?error=db_error');
    exit;
}
