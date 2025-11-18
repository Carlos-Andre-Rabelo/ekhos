<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/sessao.php';

// Protege a página: apenas clientes logados podem cadastrar um endereço.
if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$errorMessage = $_GET['error'] ?? null;

// Processa o formulário quando enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_bairro = trim($_POST['nome_bairro'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');

    if (empty($nome_bairro) || empty($logradouro) || empty($numero) || !is_numeric($numero)) {
        header('Location: cadastrar_endereco.php?error=invalid_data');
        exit;
    }

    try {
        $collection = $database->selectCollection('clientes');
        $userId = (int)$_SESSION['user_id'];

        $collection->updateOne(
            ['_id' => $userId],
            ['$set' => [
                'endereco' => [
                    'nome_bairro' => $nome_bairro,
                    'logradouro' => $logradouro,
                    'numero' => (int)$numero,
                ]
            ]]
        );

        header('Location: checkout.php');
        exit;

    } catch (Exception $e) {
        header('Location: cadastrar_endereco.php?error=db_error');
        exit;
    }
}

// Se o usuário já tiver um endereço, redireciona para o checkout.
// (Esta é uma verificação adicional para evitar que acessem a página diretamente sem necessidade)
$action = $_GET['action'] ?? null;
try {
    $clientesCollection = $database->selectCollection('clientes');
    $userId = (int)$_SESSION['user_id'];
    $cliente = $clientesCollection->findOne(['_id' => $userId]);
    if ($action !== 'change' && $cliente && !empty((array)$cliente['endereco'])) {
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

        /* Estilos para Celular (abaixo de 450px) */
        @media (max-width: 450px) {
            .address-form {
                margin: 1rem auto;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <main class="cart-page-container">
        <h1>Cadastrar Endereço de Entrega</h1>
        <p>Você precisa cadastrar um endereço para continuar com a compra.</p>

        <div class="checkout-container">
            <form action="cadastrar_endereco.php" method="POST" class="address-form">
                <?php if ($errorMessage): ?>
                    <div class="message error" style="margin-bottom: 1rem; text-align: left;">
                        <?php if ($errorMessage === 'invalid_data'): ?>
                            Por favor, preencha todos os campos corretamente.
                        <?php elseif ($errorMessage === 'db_error'): ?>
                            Ocorreu um erro ao salvar seu endereço. Tente novamente.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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
