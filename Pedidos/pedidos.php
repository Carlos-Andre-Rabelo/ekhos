<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$pedidos = [];
$errorMessage = null;
$isAdminView = is_admin();

// Protege a página: apenas clientes ou administradores logados podem ver.
if (!$isAdminView && !is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);
    $clientesCollection = $database->selectCollection('clientes');

    if ($isAdminView) {
        // Admin: Busca todas as compras de todos os clientes
        $pipeline = [
            ['$unwind' => '$compras'], // 1. Desmembra o array de compras
            ['$unwind' => '$compras.itens_comprados'], // 2. Desmembra o array de itens
            [
                // 3. Busca o título do álbum para cada item
                '$lookup' => [
                    'from' => 'albuns',
                    'localField' => 'compras.itens_comprados.album_id',
                    'foreignField' => '_id',
                    'as' => 'album_info'
                ]
            ],
            [
                // 4. Adiciona o título ao item e agrupa os itens de volta no pedido
                '$group' => [
                    '_id' => '$compras._id', // Agrupa por ID da compra
                    'data_pedido' => ['$first' => '$compras.data_compra'],
                    'total_pedido' => ['$first' => '$compras.valor_total'],
                    'status' => ['$first' => ['$ifNull' => ['$compras.status', 'processando']]],
                    'endereco_entrega' => ['$first' => '$endereco'],
                    'cliente_info' => ['$first' => ['_id' => '$_id', 'nome_cliente' => '$nome_cliente']],
                    'itens' => [
                        '$push' => [
                            // Adiciona o título do álbum ao item
                            'titulo' => ['$ifNull' => [['$arrayElemAt' => ['$album_info.titulo_album', 0]], 'Álbum não encontrado']],
                            'album_id' => '$compras.itens_comprados.album_id',
                            'tipo_formato' => '$compras.itens_comprados.tipo_formato',
                            'quantidade' => '$compras.itens_comprados.quantidade',
                            'preco_unitario' => '$compras.itens_comprados.preco_unitario'
                        ]
                    ]
                ]
            ],
            ['$sort' => ['data_pedido' => -1]]
        ];
        $cursor = $clientesCollection->aggregate($pipeline);
    } else {
        // Cliente: Busca as compras do cliente logado
        $userId = (int)$_SESSION['user_id'];
        $pipeline = [
            ['$match' => ['_id' => $userId]], // Encontra o cliente logado
            ['$unwind' => '$compras'], // 1. Desmembra o array de compras
            ['$unwind' => '$compras.itens_comprados'], // 2. Desmembra o array de itens
            [
                // 3. Busca o título do álbum para cada item
                '$lookup' => [
                    'from' => 'albuns',
                    'localField' => 'compras.itens_comprados.album_id',
                    'foreignField' => '_id',
                    'as' => 'album_info'
                ]
            ],
            [
                // 4. Adiciona o título ao item e agrupa os itens de volta no pedido
                '$group' => [
                    '_id' => '$compras._id', // Agrupa por ID da compra
                    'data_pedido' => ['$first' => '$compras.data_compra'],
                    'total_pedido' => ['$first' => '$compras.valor_total'],
                    'status' => ['$first' => ['$ifNull' => ['$compras.status', 'processando']]],
                    'endereco_entrega' => ['$first' => '$endereco'],
                    'itens' => [
                        '$push' => [
                            'titulo' => ['$ifNull' => [['$arrayElemAt' => ['$album_info.titulo_album', 0]], 'Álbum não encontrado']],
                            'album_id' => '$compras.itens_comprados.album_id',
                            'tipo_formato' => '$compras.itens_comprados.tipo_formato',
                            'quantidade' => '$compras.itens_comprados.quantidade',
                            'preco_unitario' => '$compras.itens_comprados.preco_unitario'
                        ]
                    ]
                ]
            ],
            ['$sort' => ['data_pedido' => -1]]
        ];
        $cursor = $clientesCollection->aggregate($pipeline);
    }

    $pedidos = $cursor->toArray();
} catch (Exception $e) {
    $errorMessage = "Erro ao carregar seus pedidos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isAdminView ? 'Gerenciar Pedidos' : 'Meus Pedidos' ?> - ēkhos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="stylepedidos.css">
    <?php if ($isAdminView): ?>
    <style>
        /* Estilos específicos para a página de admin */
        .order-card-header {
            align-items: flex-start;
        }
        .status-form {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        .status-form select {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--cor-borda);
            background-color: var(--cor-fundo);
            color: var(--cor-texto-principal);
            font-size: 0.9rem;
        }
        .status-form .update-status-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            background-color: var(--cor-primaria);
            color: var(--cor-fundo);
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .status-form .update-status-btn:hover {
            background-color: rgba(var(--cor-primaria-rgb), 0.8);
        }
        .customer-info {
            font-size: 0.9rem;
            color: var(--cor-texto-secundario);
            margin-top: 0.5rem;
            background-color: rgba(0,0,0,0.2);
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
        }

        /* Estilos para Tablet (abaixo de 950px) */
        @media (max-width: 950px) {
            .orders-page-container {
                padding: 1rem;
            }
        }

        /* Estilos para Celular (abaixo de 450px) */
        @media (max-width: 450px) {
            .order-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .status-form, .order-status {
                margin-top: 1rem;
                align-self: flex-end;
            }
        }
    </style>
    <?php endif; ?>
</head>
<body>

    <main class="orders-page-container">
        <div class="orders-header">
            <a href="/ekhos/index.php" class="back-link" title="Voltar para a Loja"></a>
            <h1><?= $isAdminView ? 'Gerenciar Pedidos' : 'Meus Pedidos' ?></h1>
        </div>

        <div id="toast-container"></div>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php elseif (empty($pedidos)): ?>
            <div class="orders-empty">
                <p><?= $isAdminView ? 'Nenhum pedido foi realizado ainda.' : 'Você ainda não fez nenhum pedido.' ?></p>
                <?php if (!$isAdminView): ?><a href="/ekhos/index.php" class="btn-header">Começar a comprar</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="order-card" id="pedido-<?= htmlspecialchars((string)$pedido['_id']) ?>">
                        <div class="order-card-header">
                            <div>
                                <h3>Pedido #<?= htmlspecialchars((string)$pedido['_id']) ?></h3>
                                <p>Realizado em: <?= htmlspecialchars($pedido['data_pedido']->toDateTime()->format('d/m/Y H:i')) ?></p>
                                <?php if ($isAdminView): ?>
                                <div class="customer-info">
                                    <strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_info']['nome_cliente']) ?> (ID: <?= htmlspecialchars((string)$pedido['cliente_info']['_id']) ?>)
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($isAdminView): ?>
                            <form class="status-form" onsubmit="updateStatus(event)">
                                <input type="hidden" name="pedido_id" value="<?= htmlspecialchars((string)$pedido['_id']) ?>">
                                <select name="status">
                                    <option value="processando" <?= $pedido['status'] === 'processando' ? 'selected' : '' ?>>Processando</option>
                                    <option value="em preparação" <?= $pedido['status'] === 'em preparação' ? 'selected' : '' ?>>Em preparação</option>
                                    <option value="pedido enviado" <?= $pedido['status'] === 'pedido enviado' ? 'selected' : '' ?>>Pedido Enviado</option>
                                </select>
                                <button type="submit" class="update-status-btn">Atualizar Status</button>
                            </form>
                            <?php else: ?>
                            <div class="order-status">
                                <strong>Status:</strong> <?= htmlspecialchars(ucfirst($pedido['status'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="order-card-body">
                            <h4>Itens do Pedido</h4>
                            <ul class="order-items-list">
                                <?php foreach ($pedido['itens'] as $item): ?>
                                    <?php
                                        // Calcula o subtotal dinamicamente, pois ele não existe no banco de dados.
                                        $subtotal = (float)($item['preco_unitario'] ?? 0) * (int)($item['quantidade'] ?? 0);
                                    ?>
                                    <li>
                                        <span class="item-info" title="ID do Álbum: <?= htmlspecialchars((string)($item['album_id'] ?? '')) ?>">
                                            <?= htmlspecialchars($item['titulo'] ?? 'Título não encontrado') ?> - <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['tipo_formato']))) ?> x <?= htmlspecialchars((string)$item['quantidade']) ?>
                                        </span>
                                        <span class="item-subtotal"><?= 'R$ ' . number_format($subtotal, 2, ',', '.') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="order-card-footer">
                            <div class="order-address">
                                <strong>Endereço de Entrega:</strong>
                                <p><?= htmlspecialchars($pedido['endereco_entrega']['logradouro'] . ', ' . $pedido['endereco_entrega']['numero'] . ' - ' . $pedido['endereco_entrega']['nome_bairro']) ?></p>
                            </div>
                            <div class="order-total">
                                <strong>Total:</strong> <?= 'R$ ' . number_format((float)$pedido['total_pedido'], 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

<?php if ($isAdminView): ?>
<script src="../script.js"></script> <!-- Reutiliza o script.js para a função de toast -->
<script>
    function updateStatus(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);

        fetch('update_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
            } else {
                showToast('Erro: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            showToast('Ocorreu um erro de comunicação.', 'error');
        });
    }
</script>
<?php endif; ?>
</body>
</html>