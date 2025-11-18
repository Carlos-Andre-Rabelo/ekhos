<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';
require_once __DIR__ . '/../vendor/autoload.php';

/*
 * AVISO DE SEGURANÇA:
 * Armazenar chaves de criptografia diretamente no código não é seguro para produção.
 * Em um ambiente real, use variáveis de ambiente ou um serviço de gerenciamento de segredos.
 */
define('ENCRYPTION_KEY', 'e0d1f2c3b4a5968778695a4b3c2d1e0f1f2e3d4c5b6a798897a6b5c4d3e2f10e');
define('ENCRYPTION_CIPHER', 'aes-256-cbc');

// Protege a página: apenas clientes logados podem cadastrar um cartão.
if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";

$action = $_GET['action'] ?? null;
// Verifica se o cliente já possui um cartão para pular esta etapa
try {
    $client = new MongoDB\Client($mongoUri);
    $clientesCollection = $client->selectDatabase($dbName)->selectCollection('clientes');
    $userId = (int)$_SESSION['user_id'];
    $cliente = $clientesCollection->findOne(['_id' => $userId], ['projection' => ['cartao' => 1]]);

    // Se o cliente tem um cartão e não está tentando alterá-lo, vai direto para o checkout
    if ($action !== 'change' && $cliente && !empty((array)$cliente['cartao'])) {
        header('Location: /ekhos/carrinho/checkout.php');
        exit;
    }
} catch (Exception $e) {
    // Em caso de erro no DB, não faz nada e permite que a página carregue normalmente.
}

// Processa o formulário quando enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_cartao = $_POST['numero_cartao'] ?? '';
    $nome_titular = $_POST['nome_titular'] ?? '';
    $data_validade = $_POST['data_validade'] ?? '';
    $cvv = $_POST['cvv'] ?? '';

    if (empty($numero_cartao) || !preg_match('/^\d{16}$/', $numero_cartao) || empty($nome_titular) || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $data_validade) || empty($cvv) || !preg_match('/^\d{3,4}$/', $cvv)) {
        header('Location: cadastrar_pagamento.php?error=invalid_data');
        exit;
    }

    try {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_CIPHER));
        $encrypted_numero = base64_encode($iv . openssl_encrypt($numero_cartao, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv));
        $encrypted_cvv = base64_encode($iv . openssl_encrypt($cvv, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv));

        $userId = (int)$_SESSION['user_id'];
        $cartaoData = [
            'numero_cartao_encrypted' => $encrypted_numero,
            'nome_titular' => $nome_titular,
            'data_validade' => $data_validade,
            'cvv_encrypted' => $encrypted_cvv,
            'last_updated' => new MongoDB\BSON\UTCDateTime()
        ];

        $client = new MongoDB\Client("mongodb://127.0.0.1:27017");
        $clientesCollection = $client->selectDatabase('CDs_&_vinil')->selectCollection('clientes');

        $clientesCollection->updateOne(
            ['_id' => $userId],
            ['$set' => ['cartao' => $cartaoData]]
        );

        header('Location: checkout.php');
        exit;

    } catch (Exception $e) {
        header('Location: cadastrar_pagamento.php?error=db_error');
        exit;
    }
}

require_once __DIR__ . '/../login/sessao.php';

// Protege a página: apenas clientes logados podem cadastrar um cartão.
if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

$errorMessage = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Cartão - ēkhos</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="stylecarrinho.css">
    <style>
        .card-form {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #3c3c3c;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .expiry-cvv-group {
            display: flex;
            gap: 1rem;
        }
        .error-message-form {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border-radius: .25rem;
        }

        /* Estilos para Celular (abaixo de 450px) */
        @media (max-width: 450px) {
            .card-form {
                margin: 1rem auto;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <main class="cart-page-container">
        <h1>Cadastrar Cartão</h1>
        <p>Você precisa cadastrar um método de pagamento para continuar.</p>

        <div class="checkout-container">
            <form action="cadastrar_pagamento.php" method="POST" class="card-form">
                <?php if ($errorMessage === 'invalid_data'): ?>
                    <div class="error-message-form">
                        Por favor, preencha todos os campos corretamente. O número do cartão deve ter 16 dígitos.
                    </div>
                <?php elseif ($errorMessage === 'db_error'): ?>
                     <div class="error-message-form">
                        Ocorreu um erro ao salvar seu cartão. Tente novamente.
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="numero_cartao">Número do Cartão</label>
                    <input type="text" id="numero_cartao" name="numero_cartao" required maxlength="16" pattern="\d{16}" title="Digite os 16 dígitos do seu cartão.">
                </div>
                <div class="form-group">
                    <label for="nome_titular">Nome do Titular</label>
                    <input type="text" id="nome_titular" name="nome_titular" required>
                </div>
                <div class="expiry-cvv-group">
                    <div class="form-group" style="flex: 1;">
                        <label for="data_validade">Validade (MM/AA)</label>
                        <input type="text" id="data_validade" name="data_validade" required placeholder="MM/AA" pattern="(0[1-9]|1[0-2])\/\d{2}">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="cvv">CVV</label>
                        <input type="text" id="cvv" name="cvv" required maxlength="4">
                    </div>
                </div>
                <p style="font-size: 0.8em; color: #666; margin-top: -1rem; margin-bottom: 1.5rem;">
                    <strong>Aviso:</strong> Este é um ambiente de simulação. Não insira dados de cartão de crédito reais.
                </p>
                <button type="submit" class="btn-checkout">Salvar Cartão e Continuar</button>
            </form>
        </div>
    </main>
</body>
</html>
