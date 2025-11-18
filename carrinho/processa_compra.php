<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

// Apenas clientes logados podem processar uma compra.
if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

// Garante que a página seja acessada via POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: carrinho.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$userId = (int)$_SESSION['user_id'];

$client = new MongoDB\Client($mongoUri);

try {
    $database = $client->selectDatabase($dbName);
    $clientesCollection = $database->selectCollection('clientes');
    $albunsCollection = $database->selectCollection('albuns');

    // 1. Buscar os dados do cliente (carrinho e endereço)
    $cliente = $clientesCollection->findOne(['_id' => $userId]);
    if (!$cliente || empty($cliente['carrinho'])) {
        throw new Exception("Carrinho vazio ou cliente não encontrado.");
    }

    $itensPedido = [];
    $totalPedido = 0;

    // 2. Iterar sobre o carrinho para validar estoque e preparar o pedido
    foreach ($cliente['carrinho'] as $itemCarrinho) {
        $albumId = (int)$itemCarrinho['album_id'];
        $formatoTipo = (string)$itemCarrinho['formato_tipo'];
        $quantidadeComprada = (int)$itemCarrinho['quantidade'];

        $album = $albunsCollection->findOne(['_id' => $albumId]);
        if (!$album) {
            throw new Exception("Álbum com ID $albumId não encontrado.");
        }

        $estoqueAtual = 0;
        $precoUnitario = 0;
        $formatoEncontrado = false;

        foreach ($album['formatos'] as $formato) {
            if ($formato['tipo'] === $formatoTipo) {
                $estoqueAtual = (int)$formato['quantidade_estoque'];
                $precoUnitario = (float)$formato['preco'];
                $formatoEncontrado = true;
                break;
            }
        }

        if (!$formatoEncontrado) {
            throw new Exception("Formato '$formatoTipo' não encontrado para o álbum '{$album['titulo_album']}'.");
        }

        if ($quantidadeComprada > $estoqueAtual) {
            throw new Exception("Estoque insuficiente para o item '{$album['titulo_album']}' ($formatoTipo). Disponível: $estoqueAtual, Solicitado: $quantidadeComprada.");
        }

        // 3. Atualizar (diminuir) o estoque do item
        $albunsCollection->updateOne(
            ['_id' => $albumId, 'formatos.tipo' => $formatoTipo],
            ['$inc' => ['formatos.$.quantidade_estoque' => -$quantidadeComprada]]
        );

        // Adicionar item ao array do pedido
        $itensPedido[] = [
            'album_id' => (int)$album['_id'], // Salva como o ID inteiro original do álbum
            'tipo_formato' => $formatoTipo,
            'quantidade' => $quantidadeComprada,
            'preco_unitario' => $precoUnitario,
        ];

        $totalPedido += $quantidadeComprada * $precoUnitario;
    }

    // 4. Criar o documento da nova compra para ser embutido
    $novaCompra = [
        '_id' => new MongoDB\BSON\ObjectId(), // Gera um ID único para a compra
        'data_compra' => new MongoDB\BSON\UTCDateTime(),
        'valor_total' => $totalPedido,
        'itens_comprados' => $itensPedido,
        'status' => 'processando' // Status inicial
    ];

    // 5. Adicionar a nova compra ao array 'compras' do cliente
    $clientesCollection->updateOne(
        ['_id' => $userId],
        ['$push' => ['compras' => $novaCompra]]
    );

    // 6. Limpar o carrinho do cliente
    $clientesCollection->updateOne(
        ['_id' => $userId],
        ['$set' => ['carrinho' => []]]
    );

    // Define a mensagem de sucesso na sessão para ser exibida na página principal.
    // 7. Se tudo deu certo, redireciona para a página de sucesso.
    $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Compra realizada com sucesso! O seu pedido número #{$novaCompra['_id']} está sendo processado."
    ];

    // Redireciona para a página principal.
    header("Location: /ekhos/index.php");
    exit;

} catch (Exception $e) {
    // Armazena a mensagem de erro na sessão e redireciona de volta para o carrinho
    $_SESSION['checkout_error'] = "Erro ao finalizar a compra: " . $e->getMessage();
    header('Location: carrinho.php');
    exit;
}