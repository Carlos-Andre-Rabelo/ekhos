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
$codigoRastreio = $_POST['codigo_rastreio'] ?? null; // Novo campo
$validStatuses = ['processando', 'em preparação', 'pedido enviado'];

if (!$pedidoId || !$novoStatus || !in_array($novoStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos.']);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

try {
    // A coleção a ser atualizada é 'clientes', pois as compras estão dentro dela.
    $clientesCollection = $database->selectCollection('clientes');

    // O _id da compra é um objeto ObjectId.
    // Precisamos criar um objeto ObjectId a partir da string recebida.
    $objectId = new MongoDB\BSON\ObjectId($pedidoId);

    // Prepara os dados para atualização.
    $updateData = ['$set' => ['compras.$.status' => $novoStatus]];

    // Se o novo status for 'pedido enviado', adiciona o código de rastreio.
    if ($novoStatus === 'pedido enviado') {
        if (empty($codigoRastreio)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Código de rastreio é obrigatório para enviar o pedido.']);
            exit;
        }
        $updateData['$set']['compras.$.codigo_rastreio'] = $codigoRastreio;
    }

    // Encontra o cliente que tem uma compra com o ID correspondente e atualiza o status APENAS dessa compra.
    $updateResult = $clientesCollection->updateOne(
        ['compras._id' => $objectId],
        $updateData
    );

    // Retorna sucesso se pelo menos um documento foi modificado.
    echo json_encode(['status' => 'success', 'message' => 'Status do pedido atualizado!']);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}