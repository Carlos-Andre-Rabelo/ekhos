<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

// Protege a página: apenas clientes logados podem acessar o checkout.
if (!is_client()) {
    header('Location: ../login/login.php');
    exit;
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../db_connect.php';
$errorMessage = null;
$cliente = null;
$cartItems = [];
$totalGeral = 0;

try {
    $clientesCollection = $database->selectCollection('clientes');
    $userId = (int)$_SESSION['user_id'];

    $cliente = $clientesCollection->findOne(['_id' => $userId]);

    if (!$cliente) {
        throw new Exception("Usuário não encontrado.");
    }

    // Verifica se o endereço está cadastrado
    $endereco = $cliente['endereco'] ?? null;
    if (!$endereco || empty((array)$endereco)) {
        header('Location: ../carrinho/cadastrar_endereco.php');
        exit;
    }


    // Se o carrinho estiver vazio, redireciona para o carrinho que mostrará a mensagem.
    if (empty($cliente['carrinho'])) {
        header('Location: ../carrinho/carrinho.php');
        exit;
    }

    // Busca os itens do carrinho para exibir o resumo (lógica de agregação do carrinho.php)
    $pipeline = [
        ['$match' => ['_id' => $userId]],
        ['$unwind' => '$carrinho'],
        ['$lookup' => [
            'from' => 'albuns',
            'localField' => 'carrinho.album_id',
            'foreignField' => '_id',
            'as' => 'album_details'
        ]],
        ['$unwind' => '$album_details'],
        ['$project' => [
            '_id' => 0,
            'album_id' => '$carrinho.album_id',
            'formato_tipo' => '$carrinho.formato_tipo',
            'quantidade' => '$carrinho.quantidade',
            'titulo' => '$album_details.titulo_album',
            'url_capa' => ['$ifNull' => [['$arrayElemAt' => ['$album_details.imagens_capas', 0]], 'https://via.placeholder.com/80']],
            'formato_info' => ['$filter' => [
                'input' => '$album_details.formatos',
                'as' => 'formato',
                'cond' => ['$eq' => ['$$formato.tipo', '$carrinho.formato_tipo']]
            ]]
        ]],
        ['$unwind' => '$formato_info'],
        ['$project' => [
            'album_id' => 1,
            'formato_tipo' => 1,
            'quantidade' => 1,
            'titulo' => 1,
            'url_capa' => '$url_capa',
            'preco' => ['$toDouble' => '$formato_info.preco'],
            'subtotal' => ['$multiply' => [
                ['$toDouble' => '$quantidade'],
                ['$toDouble' => '$formato_info.preco']
            ]]
        ]]
    ];

    $cursor = $clientesCollection->aggregate($pipeline);
    $cartItems = $cursor->toArray();

    foreach ($cartItems as $item) {
        $totalGeral += (float)$item['subtotal'];
    }

} catch (Exception $e) {
    $errorMessage = "Erro ao processar o checkout: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - ēkhos</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="stylecarrinho.css">
    <style>
        .checkout-layout { display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start; }
        .checkout-details { flex: 2; min-width: 300px; }
        .order-summary-checkout { flex: 1; min-width: 300px; background-color: #3c3c3c; padding: 1.5rem; border-radius: 8px; }
        .order-summary-checkout h2 { margin-top: 0; }
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 1rem; align-items: center; }
        .summary-item-details { display: flex; gap: 1rem; align-items: center; }
        .summary-item-details img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px;}
        .summary-item-info .item-title { font-weight: 600; }
        .summary-item-info .item-format { font-size: 0.9em; color: #666; }
        .summary-item-price { font-weight: 600; text-align: right; }
        .address-details { margin-bottom: 1rem; }
        .change-link {
            font-size: 0.85em;
            color: var(--cor-primaria);
            text-decoration: none;
            display: inline-block;
            margin-top: 0.3rem;
            border: 1px solid var(--cor-primaria);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            transition: background-color 0.3s, color 0.3s;
        }
        .change-link:hover { background-color: var(--cor-primaria); color: var(--cor-fundo); }
        .checkout-details h2 { margin-top: 1.5rem; }

        /* Estilos para Tablet e Celular (abaixo de 950px) */
        @media (max-width: 950px) {
            .checkout-layout {
                flex-direction: column-reverse; /* Coloca o resumo do pedido no topo em telas menores */
                align-items: stretch;
            }

            .checkout-details, .order-summary-checkout {
                width: 100%;
                flex: none;
            }
            .order-summary-checkout {
                margin-bottom: 2rem;
            }
        }
        @media (max-width: 450px) {
            .cart-page-container { padding: 1rem; }
            .cart-page-container h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <main class="cart-page-container">
        <h1>Finalizar Compra</h1>

        <?php if ($errorMessage): ?>
            <div class="error-message" style="text-align:center;"><?= htmlspecialchars($errorMessage) ?></div>
        <?php else: ?>
            <div class="checkout-layout">
                <div class="checkout-details">
                    <h2>Confirme seu Endereço de Entrega</h2>
                    <div class="address-details">
                        <p><strong>Bairro:</strong> <?= htmlspecialchars($cliente['endereco']['nome_bairro'] ?? 'Não informado') ?></p>
                        <p><strong>Logradouro:</strong> <?= htmlspecialchars($cliente['endereco']['logradouro'] ?? 'Não informado') ?></p>
                        <p><strong>Número:</strong> <?= htmlspecialchars((string)($cliente['endereco']['numero'] ?? 'Não informado')) ?></p>
                        <a href="cadastrar_endereco.php?action=change" class="change-link">Alterar endereço</a>
                    </div>

                    <form action="criar_checkout_stripe.php" method="POST" style="margin-top: 2rem;">
                        <button type="submit" class="btn-checkout">Pagar com Stripe</button>
                    </form>
                    <p style="font-size: 0.8em; margin-top: 10px;">Você será redirecionado para o ambiente seguro do Stripe.</p>
                    
                    <a href="../carrinho/carrinho.php" class="continue-shopping-link" style="margin-top: 1rem; display: inline-block;">Voltar ao carrinho</a>
                </div>

                <div class="order-summary-checkout">
                    <h2>Resumo do Pedido</h2>
                    <?php foreach ($cartItems as $item): ?>
                        <div class="summary-item">
                            <div class="summary-item-details">
                                <img src="../<?= htmlspecialchars($item['url_capa']) ?>" alt="Capa do álbum">
                                <div class="summary-item-info">
                                    <div class="item-title"><?= htmlspecialchars($item['titulo']) ?></div>
                                    <div class="item-format">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['formato_tipo']))) ?> (x<?= $item['quantidade'] ?>)
                                    </div>
                                </div>
                            </div>
                            <div class="summary-item-price">
                                <?= 'R$ ' . number_format((float)$item['subtotal'], 2, ',', '.') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="summary-total">
                        <span>Total</span>
                        <span id="summary-total"><?= 'R$ ' . number_format($totalGeral, 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
