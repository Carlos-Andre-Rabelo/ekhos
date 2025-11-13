<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

//confere se eh cliente
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
$cartItems = [];
$totalGeral = 0;
$errorMessage = null;

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);
    $clientesCollection = $database->selectCollection('clientes');
    $userId = (int)$_SESSION['user_id'];

    //agregacao pros itens do carrinho
    $pipeline = [
        //cliente atual
        ['$match' => ['_id' => $userId]],
        //desfaz array carrinho
        ['$unwind' => '$carrinho'],
        //join com albuns
        [
            '$lookup' => [
                'from' => 'albuns',
                'localField' => 'carrinho.album_id',
                'foreignField' => '_id',
                'as' => 'album_details'
            ]
        ],
        //desfaz result do lookup
        ['$unwind' => '$album_details'],
        //mostrar dados e calcular subtotal
        [
            '$project' => [
                '_id' => 0,
                'album_id' => '$carrinho.album_id',
                'formato_tipo' => '$carrinho.formato_tipo',
                'quantidade' => '$carrinho.quantidade', //quant
                'titulo' => '$album_details.titulo_album',
                'url_capa' => ['$ifNull' => [['$arrayElemAt' => ['$album_details.imagens_capas', 0]], 'https://via.placeholder.com/80']],
                'formato_info' => [
                    '$filter' => [
                        'input' => '$album_details.formatos',
                        'as' => 'formato',
                        'cond' => ['$eq' => ['$$formato.tipo', '$carrinho.formato_tipo']]
                    ]
                ]
            ]
        ],
        //primeira info do array
        ['$unwind' => '$formato_info'],
        
        //mostra final
        [
            '$project' => [
                'album_id' => 1,
                'formato_tipo' => 1,
                'quantidade' => 1,
                'titulo' => 1,
                'url_capa' => '$url_capa',
                'preco' => ['$toDouble' => '$formato_info.preco'],
                'estoque' => '$formato_info.quantidade_estoque',
                
                'subtotal' => [
                    '$multiply' => [
                        ['$toDouble' => '$quantidade'],
                        ['$toDouble' => '$formato_info.preco']
                    ]
                ]
            ]
        ]
    ];

    $cursor = $clientesCollection->aggregate($pipeline);
    $cartItems = $cursor->toArray();

    //calc total
    foreach ($cartItems as $item) {
        $totalGeral += (float)$item['subtotal'];
    }

} catch (Exception $e) {
    $errorMessage = "Erro ao carregar o carrinho: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho - ēkhos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="stylecarrinho.css">
</head>
<body data-user-role="client">

    <main class="cart-page-container">
        <h1>Meu Carrinho</h1>

        <?php if ($errorMessage): ?>
            <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
        <?php elseif (empty($cartItems)): ?>
            <div class="cart-empty">
                <p>Seu carrinho está vazio.</p>
                <a href="/ekhos/index.php" class="btn-header">Voltar para a loja</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items-list">
                    <table>
                        <thead>
                            <tr>
                                <th colspan="2">Produto</th>
                                <th>Preço</th>
                                <th>Quantidade</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr data-album-id="<?= $item['album_id'] ?>" data-formato-tipo="<?= htmlspecialchars($item['formato_tipo']) ?>">
                                    <td class="item-image">
                                        <img src="../<?= htmlspecialchars($item['url_capa']) ?>" alt="Capa do <?= htmlspecialchars($item['titulo']) ?>">
                                    </td>
                                    <td class="item-details">
                                        <div class="item-title"><?= htmlspecialchars($item['titulo']) ?></div>
                                        <div class="item-format"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['formato_tipo']))) ?></div>
                                    </td>
                                    <td class="item-price">
                                        <?= 'R$ ' . number_format((float)$item['preco'], 2, ',', '.') ?>
                                    </td>
                                    <td class="item-quantity">
                                        <div class="quantity-control">
                                            <button type="button" class="quantity-btn quantity-minus" aria-label="Diminuir">-</button>
                                            <input type="number" class="quantity-input" value="<?= $item['quantidade'] ?>" min="1" max="<?= $item['estoque'] ?>" data-album-id="<?= $item['album_id'] ?>" data-formato-tipo="<?= htmlspecialchars($item['formato_tipo']) ?>" readonly>
                                            <button type="button" class="quantity-btn quantity-plus" aria-label="Aumentar">+</button>
                                        </div>
                                    </td>
                                    <td class="item-subtotal">
                                        <?= 'R$ ' . number_format((float)$item['subtotal'], 2, ',', '.') ?>
                                    </td>
                                    <td class="item-remove">
                                        <button class="remove-btn" title="Remover item" data-album-id="<?= $item['album_id'] ?>" data-formato-tipo="<?= htmlspecialchars($item['formato_tipo']) ?>">&times;</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-summary">
                    <h2>Resumo do Pedido</h2>
                    <div class="summary-row">
                        <span>Subtotal dos produtos</span>
                        <span id="summary-subtotal"><?= 'R$ ' . number_format($totalGeral, 2, ',', '.') ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Frete</span>
                        <span>A calcular</span>
                    </div>
                    <div class="summary-total"> 
                        <span>Total</span>
                        <span id="summary-total"><?= 'R$ ' . number_format($totalGeral, 2, ',', '.') ?></span>
                    </div>
                    <a href="/ekhos/carrinho/cadastrar_pagamento.php" class="btn-checkout">Ir para o Pagamento</a>
                    <a href="/ekhos/index.php" class="continue-shopping-link">Continuar comprando</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ēkhos</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        const formatCurrency = (value) => {
            return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        };

        const updateTotals = () => {
            let newTotal = 0;
            document.querySelectorAll('.cart-items-list tbody tr').forEach(row => {
                const subtotalText = row.querySelector('.item-subtotal').textContent;
                const subtotalValue = parseFloat(subtotalText.replace('R$', '').replace(/\./g, '').replace(',', '.').trim());
                if (subtotalValue && !isNaN(subtotalValue)) {
                    newTotal += subtotalValue;
                }
            });

            const summarySubtotalEl = document.getElementById('summary-subtotal');
            const summaryTotalEl = document.getElementById('summary-total');

            if (summarySubtotalEl && summaryTotalEl) {
                summarySubtotalEl.textContent = formatCurrency(newTotal);
                summaryTotalEl.textContent = formatCurrency(newTotal);
            }
        };

        const updateRowSubtotal = (inputElement) => {
            const row = inputElement.closest('tr');
            if (!row) return; 

            const priceTextEl = row.querySelector('.item-price');
            const subtotalTextEl = row.querySelector('.item-subtotal');
            
            if (!priceTextEl || !subtotalTextEl) return; 

            const priceText = priceTextEl.textContent;
            const cleanedPriceText = priceText.replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
            const price = parseFloat(cleanedPriceText);
            const quantity = parseInt(inputElement.value, 10);

            if (!isNaN(price) && !isNaN(quantity)) {
                const subtotal = price * quantity;
                subtotalTextEl.textContent = formatCurrency(subtotal);
                updateTotals();
            }
        };

        //envio backend
        function handleCartAction(action, albumId, formatoTipo, quantidade = null) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('album_id', albumId);
            formData.append('formato_tipo', formatoTipo);
            if (quantidade !== null) {
                formData.append('quantidade', quantidade);
            }

            fetch('/ekhos/carrinho/cart_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log(data.message);
                } else {
                    alert('Erro: ' + data.message);
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro ao atualizar o carrinho.');
                window.location.reload();
            });
        }

        //listener botao quant
        document.querySelector('.cart-items-list')?.addEventListener('click', function(e) {
            const minusBtn = e.target.closest('.quantity-minus');
            const plusBtn = e.target.closest('.quantity-plus');

            if (!minusBtn && !plusBtn) return;

            const controlDiv = e.target.closest('.quantity-control');
            const input = controlDiv.querySelector('.quantity-input');
            let currentValue = parseInt(input.value, 10);
            const min = parseInt(input.min, 10);
            const max = parseInt(input.max, 10);

            if (minusBtn && currentValue > min) {
                input.value = currentValue - 1;
            }

            if (plusBtn && currentValue < max) {
                input.value = currentValue + 1;
            }

            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        //listener input quant
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const albumId = this.dataset.albumId;
                const formatoTipo = this.dataset.formatoTipo;
                const quantidade = this.value;

                updateRowSubtotal(this);
                handleCartAction('update_quantity', albumId, formatoTipo, quantidade);
            });
            updateRowSubtotal(input);
        });
        
        //listener remover
        document.querySelectorAll('.remove-btn').forEach(button => {
            button.addEventListener('click', function() {
                const albumId = this.dataset.albumId;
                const formatoTipo = this.dataset.formatoTipo;
                const row = this.closest('tr');
                    
                row.remove();
                updateTotals();
                handleCartAction('remove_item', albumId, formatoTipo);
            });
        });
    });
    </script>

</body>
</html>