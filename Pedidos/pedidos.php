<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../db_connect.php';

$pedidos = [];
$errorMessage = null;
$isAdminView = is_admin();

// Arrays para organizar os pedidos por status na visão do admin
$pedidos_processando = [];
$pedidos_em_preparacao = [];
$pedidos_enviados = [];

// Protege a página: apenas clientes ou administradores logados podem ver.
if (!$isAdminView && !is_client()) {
    header('Location: ../login/login.php');
    exit;
}

try {
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
                    'codigo_rastreio' => ['$first' => '$compras.codigo_rastreio'],
                    'status' => ['$first' => ['$ifNull' => ['$compras.status', 'processando']]], // 'processando' é o status inicial padrão
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
                    'codigo_rastreio' => ['$first' => '$compras.codigo_rastreio'],
                    'status' => ['$first' => ['$ifNull' => ['$compras.status', 'processando']]], // 'processando' é o status inicial padrão
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

    $todos_pedidos = $cursor->toArray();

    if ($isAdminView) {
        foreach ($todos_pedidos as $pedido) {
            if ($pedido['status'] === 'pedido enviado') $pedidos_enviados[] = $pedido;
            elseif ($pedido['status'] === 'em preparação') $pedidos_em_preparacao[] = $pedido;
            else $pedidos_processando[] = $pedido;
        }
    } else {
        $pedidos = $todos_pedidos;
    }
} catch (Exception $e) {
    $errorMessage = "Erro ao carregar seus pedidos: " . $e->getMessage();
}

// Função para renderizar um card de pedido. Centraliza o HTML e evita repetição.
function renderOrderCard($pedido, bool $isAdminView): void {
    $pedidoId = htmlspecialchars((string)$pedido['_id']);
    $status = htmlspecialchars($pedido['status']);
    $dataPedido = htmlspecialchars($pedido['data_pedido']->toDateTime()->format('d/m/Y H:i'));
    $nomeCliente = htmlspecialchars($pedido['cliente_info']['nome_cliente'] ?? 'Não encontrado');
    $clienteId = htmlspecialchars((string)($pedido['cliente_info']['_id'] ?? ''));
    $endereco = htmlspecialchars($pedido['endereco_entrega']['logradouro'] . ', ' . $pedido['endereco_entrega']['numero'] . ' - ' . $pedido['endereco_entrega']['nome_bairro']);
    $totalPedido = 'R$ ' . number_format((float)$pedido['total_pedido'], 2, ',', '.');

    echo "<div class='order-card' id='pedido-{$pedidoId}' data-status='{$status}'>";
    echo "<div class='order-card-header'>";
    echo "<div>";
    echo "<h3>Pedido #{$pedidoId}</h3>";
    echo "<p>Realizado em: {$dataPedido}</p>";
    if ($isAdminView) {
        echo "<div class='customer-info'><strong>Cliente:</strong> {$nomeCliente} (ID: {$clienteId})</div>";
    }
    echo "</div>";
    
    // Lógica de ação/status
    echo "<div class='status-action-form'>";
    if ($isAdminView) {
        // Botões de ação para o Admin
        if ($status === 'em preparação') {
            echo "<form onsubmit=\"moveOrder(event, '{$pedidoId}', 'pedido enviado')\"><input type='text' name='codigo_rastreio' placeholder='Código de Rastreio' required><button type='submit' class='action-btn'>Enviar Pedido</button></form>";
        } elseif ($status === 'pedido enviado' && !empty($pedido['codigo_rastreio'])) {
            echo "<div class='order-status'><strong>Rastreio:</strong> " . htmlspecialchars($pedido['codigo_rastreio']) . "</div>";
        } else { // Padrão para 'processando'
            echo "<form onsubmit=\"moveOrder(event, '{$pedidoId}', 'em preparação')\"><button type='submit' class='action-btn'>Preparar Pedido</button></form>";
        }
    } else {
        // Display de status para o Cliente
        if ($status === 'pedido enviado' && !empty($pedido['codigo_rastreio'])) {
            // Se o pedido foi enviado e tem código, mostra o código com destaque.
            echo "<div class='order-status client-status-highlight'><strong>Status:</strong> Pedido Enviado<br><strong>Rastreio:</strong> " . htmlspecialchars($pedido['codigo_rastreio']) . "</div>";
        } else {
            // Mostra o status com destaque.
            echo "<div class='order-status client-status-highlight'><strong>Status:</strong> " . htmlspecialchars(ucfirst($status)) . "</div>";
        }
    }
    echo "</div>"; // Fim de status-action-form

    echo "</div>"; // Fim de order-card-header
    echo "<div class='order-card-body'><h4>Itens do Pedido</h4><ul class='order-items-list'>";
    foreach ($pedido['itens'] as $item) {
        $subtotal = (float)($item['preco_unitario'] ?? 0) * (int)($item['quantidade'] ?? 0);
        $tituloItem = htmlspecialchars($item['titulo'] ?? 'Título não encontrado');
        $formatoItem = htmlspecialchars(ucfirst(str_replace('_', ' ', $item['tipo_formato'])));
        echo "<li><span class='item-info' title='ID do Álbum: " . htmlspecialchars((string)($item['album_id'] ?? '')) . "'>{$tituloItem} - {$formatoItem} x " . htmlspecialchars((string)$item['quantidade']) . "</span><span class='item-subtotal'>" . 'R$ ' . number_format($subtotal, 2, ',', '.') . "</span></li>";
    }
    echo "</ul></div>"; // Fim de order-card-body
    echo "<div class='order-card-footer'><div class='order-address'><strong>Endereço de Entrega:</strong><p>{$endereco}</p></div><div class='order-total'><strong>Total:</strong> {$totalPedido}</div></div>";
    echo "</div>"; // Fim de order-card
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
</head>
<body>

    <main class="orders-page-container">
        <div class="orders-header">
            <a href="../index.php" class="back-link" title="Voltar para a Loja"></a>
            <h1><?= $isAdminView ? 'Gerenciar Pedidos' : 'Meus Pedidos' ?></h1>
        </div>

        <div id="toast-container"></div>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php elseif ($isAdminView && empty($pedidos_processando) && empty($pedidos_em_preparacao) && empty($pedidos_enviados)): ?>
            <div class="orders-empty">
                <p>Nenhum pedido foi realizado ainda.</p>
            </div>
        <?php elseif (!$isAdminView && empty($pedidos)): ?>
            <div class="orders-empty">
                <p>Você ainda não fez nenhum pedido.</p>
                <a href="../index.php" class="btn-header">Começar a comprar</a>
            </div>
        <?php elseif ($isAdminView): ?>
            <!-- Navegação por Abas -->
            <nav class="tab-nav">
                <button class="tab-btn active" data-tab="processando">Novos Pedidos</button>
                <button class="tab-btn" data-tab="em_preparação">Em Preparação</button>
                <button class="tab-btn" data-tab="pedido_enviado">Enviados</button>
            </nav>

            <!-- Container dos Painéis de Abas -->
            <div class="admin-orders-container">
                <!-- Painel Novos Pedidos -->
                <div id="tab-processando" class="tab-panel active">
                    <div class="orders-list">
                        <?php if (empty($pedidos_processando)): ?>
                            <p class="empty-column-msg">Nenhum pedido novo.</p>
                        <?php else: ?>
                            <?php foreach ($pedidos_processando as $pedido): ?>
                                <?php
                                    renderOrderCard($pedido, $isAdminView);
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Painel Em Preparação -->
                <div id="tab-em_preparação" class="tab-panel">
                    <div class="orders-list">
                        <?php if (empty($pedidos_em_preparacao)): ?>
                            <p class="empty-column-msg">Nenhum pedido em preparação.</p>
                        <?php else: ?>
                            <?php foreach ($pedidos_em_preparacao as $pedido): ?>
                                <?php
                                    renderOrderCard($pedido, $isAdminView);
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Painel Enviados -->
                <div id="tab-pedido_enviado" class="tab-panel">
                    <div class="orders-list">
                        <?php if (empty($pedidos_enviados)): ?>
                            <p class="empty-column-msg">Nenhum pedido enviado.</p>
                        <?php else: ?>
                            <?php foreach ($pedidos_enviados as $pedido): ?>
                                <?php
                                    renderOrderCard($pedido, $isAdminView);
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- O template para JS não é mais necessário, pois o JS agora move os elementos existentes do DOM em vez de criar novos. -->

        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($pedidos as $pedido): 
                    renderOrderCard($pedido, $isAdminView);
                ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

<?php if ($isAdminView): ?>
<script src="../script.js"></script> <!-- Reutiliza o script.js para a função de toast -->
<script>
    // Define o caminho base para a API de forma relativa.
    // Como 'scriptpedidos.js' e 'update_status.php' estão no mesmo diretório,
    // um caminho relativo simples ou vazio funciona perfeitamente.
    const API_BASE_URL = './';
</script>
<!-- O script foi movido para um arquivo externo -->
<script src="scriptpedidos.js"></script>
<?php endif; ?>
</body>
</html>