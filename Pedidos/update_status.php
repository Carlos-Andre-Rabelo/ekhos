<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

header('Content-Type: application/json');

// Protege a API: apenas administradores podem atualizar o status.
if (!is_admin()) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']);
    exit;
}

// Garante que a página seja acessada via POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    exit;
}

$pedidoId = $_POST['pedido_id'] ?? null;
$novoStatus = $_POST['status'] ?? null;
$validStatuses = ['processando', 'em preparação', 'pedido enviado'];

if (!$pedidoId || !$novoStatus || !in_array($novoStatus, $validStatuses)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos.']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";

try {
    $client = new MongoDB\Client($mongoUri);
    // A coleção a ser atualizada é 'clientes', pois as compras estão dentro dela.
    $clientesCollection = $client->selectDatabase($dbName)->selectCollection('clientes');

    // O _id da compra é um objeto ObjectId.
    // Precisamos criar um objeto ObjectId a partir da string recebida.
    $objectId = new MongoDB\BSON\ObjectId($pedidoId);

    // Encontra o cliente que tem uma compra com o ID correspondente e atualiza o status APENAS dessa compra.
    $updateResult = $clientesCollection->updateOne(
        ['compras._id' => $objectId], // Encontra o documento pelo _id dentro do array 'compras'
        ['$set' => ['compras.$.status' => $novoStatus]] // Usa o operador posicional '$' para atualizar o item correto do array
    );

    // Retorna sucesso se pelo menos um documento foi modificado.
    echo json_encode(['status' => 'success', 'message' => 'Status do pedido atualizado!']);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}