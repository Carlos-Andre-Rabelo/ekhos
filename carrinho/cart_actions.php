<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

// Apenas clientes logados podem adicionar ao carrinho.
if (!is_client()) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado. Faça login como cliente.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    exit;
}

header('Content-Type: application/json');

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$albumId = filter_input(INPUT_POST, 'album_id', FILTER_VALIDATE_INT);
$formatoTipo = filter_input(INPUT_POST, 'formato_tipo', FILTER_SANITIZE_STRING);
$quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
$userId = (int)$_SESSION['user_id'];

// Validação básica
if (!$action || !$albumId || !$formatoTipo) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos fornecidos.']);
    exit;
}

// Validação de quantidade para ações que a exigem
if (($action === 'add' || $action === 'update_quantity') && (!$quantidade || $quantidade <= 0)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Quantidade inválida.']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);
    $albunsCollection = $database->selectCollection('albuns');
    $clientesCollection = $database->selectCollection('clientes');

    if ($action === 'add' || $action === 'update_quantity') {
        // 1. Verificar estoque
        $album = $albunsCollection->findOne(['_id' => $albumId]);
        if (!$album) {
            throw new Exception("Álbum não encontrado.");
        }

        $estoqueDisponivel = 0;
        foreach ($album['formatos'] as $formato) {
            if ($formato['tipo'] === $formatoTipo) {
                $estoqueDisponivel = (int)$formato['quantidade_estoque'];
                break;
            }
        }

        if ($quantidade > $estoqueDisponivel) {
            throw new Exception("Quantidade solicitada indisponível. Estoque atual: $estoqueDisponivel.");
        }

        // 2. Adicionar/Atualizar item no carrinho de forma mais eficiente
        // Tenta atualizar a quantidade de um item existente
        $updateResult = $clientesCollection->updateOne(
            ['_id' => $userId, 'carrinho.album_id' => $albumId, 'carrinho.formato_tipo' => $formatoTipo],
            ['$set' => ['carrinho.$.quantidade' => $quantidade]]
        );

        // Se nenhum item foi atualizado (ou seja, não existia), adiciona um novo
        if ($updateResult->getModifiedCount() === 0) {
            $updateResult = $clientesCollection->updateOne(
                ['_id' => $userId],
                ['$push' => [
                    'carrinho' => [
                        'album_id' => $albumId,
                        'formato_tipo' => $formatoTipo,
                        'quantidade' => $quantidade
                    ]
                ]]
            );
        }

        $message = ($action === 'add') ? 'Item adicionado ao carrinho!' : 'Quantidade atualizada!';
        echo json_encode(['status' => 'success', 'message' => $message]);

    } elseif ($action === 'remove_item') {
        // Remove o item do carrinho
        $updateResult = $clientesCollection->updateOne(
            ['_id' => $userId],
            ['$pull' => ['carrinho' => ['album_id' => $albumId, 'formato_tipo' => $formatoTipo]]]
        );

        if ($updateResult->getModifiedCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Item removido do carrinho.']);
        } else {
            throw new Exception("Item não encontrado no carrinho.");
        }

    } else {
        throw new Exception("Ação desconhecida.");
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>