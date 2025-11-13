<?php
declare(strict_types=1);

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
    </style>
</head>
<body>
    <main class="cart-page-container">
        <h1>Cadastrar Cartão</h1>
        <p>Você precisa cadastrar um método de pagamento para continuar.</p>

        <div class="checkout-container">
            <form action="processa_pagamento.php" method="POST" class="card-form">
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
