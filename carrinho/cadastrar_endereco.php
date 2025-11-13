<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

// Protege a página: apenas clientes logados podem cadastrar um endereço.
if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

// Se o usuário já tiver um endereço, redireciona para o checkout.
// (Esta é uma verificação adicional para evitar que acessem a página diretamente sem necessidade)
require_once __DIR__ . '/../vendor/autoload.php';
$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
try {
    $client = new MongoDB\Client($mongoUri);
    $clientesCollection = $client->selectDatabase($dbName)->selectCollection('clientes');
    $userId = (int)$_SESSION['user_id'];
    $cliente = $clientesCollection->findOne(['_id' => $userId]);
    if ($cliente && !empty((array)$cliente['endereco'])) {
        header('Location: /ekhos/carrinho/checkout.php');
        exit;
    }
} catch (Exception $e) {
    // Não faz nada, apenas impede o redirecionamento em caso de erro de DB.
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Endereço - ēkhos</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="stylecarrinho.css">
    <style>
        .address-form {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #f9f9f9;
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
    </style>
</head>
<body>
    <main class="cart-page-container">
        <h1>Cadastrar Endereço de Entrega</h1>
        <p>Você precisa cadastrar um endereço para continuar com a compra.</p>

        <div class="checkout-container">
            <form action="processa_endereco.php" method="POST" class="address-form">
                <div class="form-group">
                    <label for="nome_bairro">Bairro</label>
                    <input type="text" id="nome_bairro" name="nome_bairro" required>
                </div>
                <div class="form-group">
                    <label for="logradouro">Logradouro (Rua/Avenida)</label>
                    <input type="text" id="logradouro" name="logradouro" required>
                </div>
                <div class="form-group">
                    <label for="numero">Número</label>
                    <input type="number" id="numero" name="numero" required>
                </div>
                <button type="submit" class="btn-checkout">Salvar Endereço e Continuar</button>
            </form>
        </div>
    </main>
</body>
</html>
