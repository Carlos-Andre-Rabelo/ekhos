<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';

// Configurar a chave secreta do Stripe
require_once __DIR__ . '/stripe_config.php';
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

if (!is_client()) {
    header('Location: login/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// PEGA O ENDEREÇO AUTOMATICAMENTE (Seja localhost, IP ou Cloudflare)
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
// Constrói a URL base do projeto de forma dinâmica.
// dirname($_SERVER['SCRIPT_NAME']) retorna o diretório web do script atual (ex: /ekhos/carrinho)
// Isso torna o código portável para qualquer ambiente (localhost, produção, etc.)
$baseUrl = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

try {
    $clientesCollection = $database->selectCollection('clientes');
    $albunsCollection = $database->selectCollection('albuns');

    // 1. Busca Carrinho
    $cliente = $clientesCollection->findOne(['_id' => $userId]);
    
    if (!$cliente || empty($cliente['carrinho'])) {
        throw new Exception("Carrinho vazio.");
    }

    $lineItems = [];

    // 2. Montar os itens para o Stripe
    foreach ($cliente['carrinho'] as $itemCarrinho) {
        $albumId = (int)$itemCarrinho['album_id'];
        $formatoTipo = (string)$itemCarrinho['formato_tipo'];
        $quantidade = (int)$itemCarrinho['quantidade'];

        $album = $albunsCollection->findOne(['_id' => $albumId]);
        
        // Encontrar preço e nome do formato
        $precoUnitario = 0;
        foreach ($album['formatos'] as $formato) {
            if ($formato['tipo'] === $formatoTipo) {
                $precoUnitario = (float)$formato['preco'];
                break;
            }
        }

        // Stripe trabalha com centavos (inteiros). R$ 10,00 vira 1000.
        $precoCentavos = (int)($precoUnitario * 100);

        $lineItems[] = [
            'price_data' => [
                'currency' => 'brl',
                'product_data' => [
                    'name' => $album['titulo_album'] . ' (' . ucfirst($formatoTipo) . ')',
                    // Opcional: Adicionar imagem
                    // 'images' => [$url_da_imagem], 
                ],
                'unit_amount' => $precoCentavos,
            ],
            'quantity' => $quantidade,
        ];
    }

    // 3. Criar a Sessão de Checkout no Stripe
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $lineItems,
        'mode' => 'payment',
        // URLs para onde o usuário volta depois de pagar
        'success_url' => $baseUrl . '/sucesso_stripe.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . '/carrinho.php?error=cancelado',
        'metadata' => [
            'user_id' => $userId // Guardamos o ID do usuário para saber de quem é o pagamento na volta
        ]
    ]);

    // 4. Redirecionar o usuário para o Stripe
    header("HTTP/1.1 303 See Other");
    header("Location: " . $checkout_session->url);
} catch (Exception $e) {
    // Em um ambiente de produção, seria bom logar este erro.
    header('Location: /carrinho/checkout.php?error=stripe_error&message=' . urlencode($e->getMessage()));
    exit;
}