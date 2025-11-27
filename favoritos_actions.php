<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/login/sessao.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

// Apenas clientes podem gerenciar favoritos
if (!is_client()) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    $clientesCollection = $database->selectCollection('clientes');

    switch ($action) {
        case 'toggle':
            // Adiciona ou remove dos favoritos
            $albumId = (int)$_POST['album_id'];
            
            // Busca o cliente atual
            $cliente = $clientesCollection->findOne(['_id' => $userId]);
            
            // Garante que favoritos seja um array (converte se for objeto MongoDB)
            $favoritos = [];
            if (isset($cliente['favoritos'])) {
                if (is_array($cliente['favoritos'])) {
                    $favoritos = $cliente['favoritos'];
                } else if ($cliente['favoritos'] instanceof MongoDB\Model\BSONArray) {
                    $favoritos = $cliente['favoritos']->getArrayCopy();
                }
            }
            
            // Garante que todos os valores sejam inteiros
            $favoritos = array_map('intval', $favoritos);
            
            // Verifica se já está nos favoritos
            $isFavorited = in_array($albumId, $favoritos, true);
            
            if ($isFavorited) {
                // Remove dos favoritos
                $favoritos = array_values(array_filter($favoritos, function($id) use ($albumId) {
                    return $id !== $albumId;
                }));
                
                $clientesCollection->updateOne(
                    ['_id' => $userId],
                    ['$set' => ['favoritos' => $favoritos]]
                );
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Removido dos favoritos',
                    'isFavorite' => false
                ]);
            } else {
                // Adiciona aos favoritos
                $favoritos[] = $albumId;
                
                $clientesCollection->updateOne(
                    ['_id' => $userId],
                    ['$set' => ['favoritos' => $favoritos]]
                );
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Adicionado aos favoritos',
                    'isFavorite' => true
                ]);
            }
            break;

        case 'get_favorites':
            // Retorna os IDs dos favoritos do usuário
            $cliente = $clientesCollection->findOne(['_id' => $userId]);
            
            // Garante que favoritos seja um array
            $favoritos = [];
            if (isset($cliente['favoritos'])) {
                if (is_array($cliente['favoritos'])) {
                    $favoritos = $cliente['favoritos'];
                } else if ($cliente['favoritos'] instanceof MongoDB\Model\BSONArray) {
                    $favoritos = $cliente['favoritos']->getArrayCopy();
                }
            }
            
            // Garante que todos os valores sejam inteiros
            $favoritos = array_map('intval', $favoritos);
            
            echo json_encode([
                'status' => 'success',
                'favorites' => array_values($favoritos)
            ]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
