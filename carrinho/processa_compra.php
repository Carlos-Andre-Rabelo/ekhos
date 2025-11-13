<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$client = new MongoDB\Client($mongoUri);

$userId = (int)$_SESSION['user_id'];
$errorMessage = null;
$successMessage = null;
$purchasedItemsDetails = []; // Para guardar os detalhes dos itens para exibição
$valorTotal = 0.0;

try {
    $clientesCollection = $client->selectDatabase($dbName)->selectCollection('clientes');
    $albunsCollection = $client->selectDatabase($dbName)->selectCollection('albuns');

    // 1. Buscar o cliente e seu carrinho
    $cliente = $clientesCollection->findOne(['_id' => $userId]);
    if (!$cliente || empty($cliente['carrinho'])) {
        throw new Exception("Seu carrinho está vazio ou usuário não foi encontrado.");
    }
    $carrinho = $cliente['carrinho']->bsonSerialize();

    // --- PRE-CHECK DE ESTOQUE ---
    $cartDetailsForPurchase = [];
    
    foreach ($carrinho as $item) {
        $albumId = (int)$item->album_id;
        $formatoTipo = $item->formato_tipo;
        $quantidadeComprada = (int)$item->quantidade;

        $album = $albunsCollection->findOne(['_id' => $albumId]);
        if (!$album) {
            throw new Exception("Álbum com ID $albumId não encontrado.");
        }

        $estoqueDisponivel = 0;
        $precoItem = 0;
        $formatoEncontrado = false;
        foreach ($album['formatos'] as $formato) {
            if ($formato['tipo'] === $formatoTipo) {
                $estoqueDisponivel = (int)$formato['quantidade_estoque'];
                $precoItem = (float)$formato['preco'];
                $formatoEncontrado = true;
                break;
            }
        }

        if (!$formatoEncontrado) {
            throw new Exception("Formato '$formatoTipo' não encontrado para o álbum '{$album['titulo_album']}'.");
        }

        if ($estoqueDisponivel < $quantidadeComprada) {
            throw new Exception("Estoque insuficiente para o item: '{$album['titulo_album']}' ($formatoTipo). Disponível: $estoqueDisponivel, Pedido: $quantidadeComprada.");
        }
        
        $subtotal = $precoItem * $quantidadeComprada;
        $valorTotal += $subtotal;

        // Guarda os detalhes para usar depois
        $cartDetailsForPurchase[] = [
            'album_id' => $albumId,
            'formato_tipo' => $formatoTipo,
            'quantidade' => $quantidadeComprada,
            'preco_unitario' => $precoItem,
            'titulo' => $album['titulo_album'],
            'url_capa' => $album['imagens_capas'][0] ?? 'https://via.placeholder.com/80',
            'subtotal' => $subtotal
        ];
    }

    // --- FIM DO PRE-CHECK ---

    // Se chegou aqui, há estoque para todos. Agora, processar a compra.
    $itensCompradosParaSalvar = [];

    // 2. Iterar sobre os itens do carrinho para decrementar o estoque
    foreach ($cartDetailsForPurchase as $item) {
        $updateResult = $albunsCollection->updateOne(
            [
                '_id' => $item['album_id'],
                'formatos.tipo' => $item['formato_tipo']
            ],
            ['$inc' => ['formatos.$.quantidade_estoque' => -$item['quantidade']]]
        );

        if ($updateResult->getModifiedCount() === 0) {
            // Esta exceção não deve ocorrer por causa do pre-check, mas é uma segurança.
            throw new Exception("Falha ao atualizar o estoque para o item: " . $item['titulo']);
        }

        // Prepara o array para salvar no histórico de compras
        $itensCompradosParaSalvar[] = [
            'album_id' => $item['album_id'],
            'tipo_formato' => $item['formato_tipo'],
            'quantidade' => $item['quantidade'],
            'preco_unitario' => $item['preco_unitario']
        ];
    }
    
    // Atribui os detalhes para a view
    $purchasedItemsDetails = $cartDetailsForPurchase;

    // 3. Criar o novo registro de compra
    $compraId = new MongoDB\BSON\ObjectId();
    $novaCompra = [
        '_id' => $compraId,
        'data_compra' => new MongoDB\BSON\UTCDateTime(),
        'valor_total' => $valorTotal,
        'itens_comprados' => $itensCompradosParaSalvar
    ];

    // 4. Adicionar a compra ao histórico do cliente e limpar o carrinho
    $clientesCollection->updateOne(
        ['_id' => $userId],
        [
            '$push' => ['compras' => $novaCompra],
            '$set' => ['carrinho' => []]
        ]
    );

    // 5. Sucesso
    $successMessage = "Compra #" . (string)$compraId . " realizada com sucesso! Obrigado por comprar na ēkhos.";

} catch (Exception $e) {
    $errorMessage = "Falha ao processar a compra: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status da Compra - ēkhos</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="stylecarrinho.css">
    <style>
        .success-message, .error-message {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 5px;
            text-align: center;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .order-summary-table {
            width: 100%;
            margin-top: 2rem;
            border-collapse: collapse;
        }
        .order-summary-table th, .order-summary-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .order-summary-table th {
            background-color: #f2f2f2;
        }
        .item-image img {
            width: 60px;
            height: 60px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <main class="cart-page-container">
        <h1>Status da sua Compra</h1>

        <?php if ($successMessage): ?>
            <div class="success-message"><?= htmlspecialchars($successMessage) ?></div>
            
            <h2>Detalhes do Pedido</h2>
            <table class="order-summary-table">
                <thead>
                    <tr>
                        <th colspan="2">Produto</th>
                        <th>Preço Unitário</th>
                        <th>Quantidade</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchasedItemsDetails as $item): ?>
                        <tr>
                            <td class="item-image">
                                <img src="../<?= htmlspecialchars($item['url_capa']) ?>" alt="Capa do <?= htmlspecialchars($item['titulo']) ?>">
                            </td>
                            <td>
                                <div class="item-title"><?= htmlspecialchars($item['titulo']) ?></div>
                                <div class="item-format"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['formato_tipo']))) ?></div>
                            </td>
                            <td><?= 'R$ ' . number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                            <td><?= $item['quantidade'] ?></td>
                            <td><?= 'R$ ' . number_format($item['subtotal'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="summary-total" style="text-align: right; margin-top: 1rem;">
                <strong>Total:</strong> <?= 'R$ ' . number_format($valorTotal, 2, ',', '.') ?>
            </div>

            <a href="/ekhos/index.php" class="btn-header" style="margin-top: 2rem;">Voltar para a Loja</a>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
            <a href="/ekhos/carrinho/carrinho.php" class="continue-shopping-link">Voltar ao Carrinho</a>
        <?php endif; ?>
    </main>
</body>
</html>
