<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';

require_once __DIR__ . '/stripe_config.php';
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$sessionId = $_GET['session_id'] ?? null;

if (!$sessionId) {
    header('Location: ../index.php');
    exit;
}

try {
    // 1. Verificar no Stripe se essa sessão realmente existe e foi paga
    $session = \Stripe\Checkout\Session::retrieve($sessionId);

    if ($session->payment_status !== 'paid') {
        throw new Exception("Pagamento não confirmado.");
    }

    // Recuperar ID do usuário dos metadados ou da sessão PHP
    $userId = (int)($session->metadata->user_id ?? $_SESSION['user_id']);
    if ($userId !== (int)$_SESSION['user_id']) {
        // Medida de segurança para garantir que o usuário logado é o dono da compra
        throw new Exception("Inconsistência de usuário.");
    }
    
    // --- AQUI ENTRA A LÓGICA DO SEU ANTIGO processa_compra.php ---
    
    $clientesCollection = $database->selectCollection('clientes');
    $albunsCollection = $database->selectCollection('albuns');

    $cliente = $clientesCollection->findOne(['_id' => $userId]);
    
    // Verifica se o carrinho já foi limpo (evita processar 2x se o usuário der F5)
    if (empty($cliente['carrinho'])) {
         $_SESSION['message'] = ['type' => 'success', 'text' => "Compra já processada!"];
         header("Location: ../index.php");
         exit;
    }

    $itensPedido = [];
    $totalPedido = $session->amount_total / 100; // Converte centavos de volta para reais

    // Processar Estoque e Pedido
    foreach ($cliente['carrinho'] as $itemCarrinho) {
        $albumId = (int)$itemCarrinho['album_id'];
        $formatoTipo = (string)$itemCarrinho['formato_tipo'];
        $quantidade = (int)$itemCarrinho['quantidade'];
        
        // --- CORREÇÃO: Buscar o preço unitário do item ---
        $album = $albunsCollection->findOne(['_id' => $albumId]);
        $precoUnitario = 0;
        if ($album) {
            foreach ($album['formatos'] as $formato) {
                if ($formato['tipo'] === $formatoTipo) {
                    $precoUnitario = (float)$formato['preco'];
                    break;
                }
            }
        }
        // --- FIM DA CORREÇÃO ---

        // Baixar Estoque
        $albunsCollection->updateOne(
            ['_id' => $albumId, 'formatos.tipo' => $formatoTipo],
            ['$inc' => ['formatos.$.quantidade_estoque' => -$quantidade]]
        );

        $itensPedido[] = [
            'album_id' => $albumId,
            'tipo_formato' => $formatoTipo,
            'quantidade' => $quantidade,
            'preco_unitario' => $precoUnitario // Adiciona o preço ao item do pedido
        ];
    }

    // Criar registro da compra
    $novaCompra = [
        '_id' => new MongoDB\BSON\ObjectId(),
        'stripe_session_id' => $sessionId, // Importante para rastreio
        'data_compra' => new MongoDB\BSON\UTCDateTime(),
        'valor_total' => $totalPedido,
        'itens_comprados' => $itensPedido,
        'status' => 'pago' 
    ];

    $clientesCollection->updateOne(
        ['_id' => $userId],
        ['$push' => ['compras' => $novaCompra]]
    );

    // Limpar Carrinho
    $clientesCollection->updateOne(
        ['_id' => $userId],
        ['$set' => ['carrinho' => []]]
    );

    $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Pagamento confirmado via Stripe! Pedido #{$novaCompra['_id']}."
    ];

    header("Location: ../index.php");
    exit;

} catch (Exception $e) {
    // Em um ambiente de produção, seria bom logar este erro.
    header('Location: ../carrinho/checkout.php?error=processing_error&message=' . urlencode($e->getMessage()));
    exit;
}