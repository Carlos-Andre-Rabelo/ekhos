<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

// Protege a página: apenas administradores podem ver esta página.
if (!is_admin()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$pedidos = [];
$errorMessage = null;

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);
    $pedidosCollection = $database->selectCollection('pedidos');

    // Pipeline para buscar todos os pedidos e juntar com os dados do cliente
    $pipeline = [
        [
            '$lookup' => [
                'from' => 'clientes',
                'localField' => 'cliente_id',
                'foreignField' => '_id',
                'as' => 'cliente_info'
            ]
        ],
        ['$unwind' => '$cliente_info'],
        ['$sort' => ['data_pedido' => -1]] // Ordena pelos mais recentes
    ];

    $cursor = $pedidosCollection->aggregate($pipeline);
    $pedidos = $cursor->toArray();

} catch (Exception $e) {
    $errorMessage = "Erro ao carregar os pedidos: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - ēkhos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../pedidos/stylepedidos.css"> <!-- Reutiliza o estilo da página de pedidos do cliente -->
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
    </style>
</head>
<body>

    <main class="orders-page-container">
        <div class="orders-header">
            <a href="/ekhos/index.php" class="back-link" title="Voltar para a Loja"></a>
            <h1>Gerenciar Pedidos</h1>
        </div>

        <div id="toast-container"></div>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php elseif (empty($pedidos)): ?>
            <div class="orders-empty">
                <p>Nenhum pedido foi realizado ainda.</p>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="order-card" id="pedido-<?= htmlspecialchars((string)$pedido['_id']) ?>">
                        <div class="order-card-header">
                            <div>
                                <h3>Pedido #<?= htmlspecialchars((string)$pedido['_id']) ?></h3>
                                <p>Realizado em: <?= htmlspecialchars($pedido['data_pedido']->toDateTime()->format('d/m/Y H:i')) ?></p>
                                <div class="customer-info">
                                    <strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_info']['nome_cliente']) ?> (ID: <?= htmlspecialchars((string)$pedido['cliente_id']) ?>)
                                </div>
                            </div>
                            <form class="status-form" onsubmit="updateStatus(event)">
                                <input type="hidden" name="pedido_id" value="<?= htmlspecialchars((string)$pedido['_id']) ?>">
                                <select name="status">
                                    <option value="processando" <?= $pedido['status'] === 'processando' ? 'selected' : '' ?>>Processando</option>
                                    <option value="em preparação" <?= $pedido['status'] === 'em preparação' ? 'selected' : '' ?>>Em preparação</option>
                                    <option value="pedido enviado" <?= $pedido['status'] === 'pedido enviado' ? 'selected' : '' ?>>Pedido Enviado</option>
                                </select>
                                <button type="submit" class="update-status-btn">Atualizar Status</button>
                            </form>
                        </div>
                        <div class="order-card-body">
                            <h4>Itens do Pedido</h4>
                            <ul class="order-items-list">
                                <?php foreach ($pedido['itens'] as $item): ?>
                                    <li>
                                        <span class="item-info"><?= htmlspecialchars($item['titulo']) ?> (<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['formato_tipo']))) ?>) x <?= htmlspecialchars((string)$item['quantidade']) ?></span>
                                        <span class="item-subtotal"><?= 'R$ ' . number_format((float)$item['subtotal'], 2, ',', '.') ?></span>
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

</body>
</html>