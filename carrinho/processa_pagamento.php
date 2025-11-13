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

// Apenas requisições POST são permitidas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastrar_pagamento.php');
    exit;
}

// Protege a página: apenas clientes logados podem processar um cartão.
if (!is_client()) {
    header('Location: /ekhos/login/login.php');
    exit;
}

$numero_cartao = $_POST['numero_cartao'] ?? '';
$nome_titular = $_POST['nome_titular'] ?? '';
$data_validade = $_POST['data_validade'] ?? '';
$cvv = $_POST['cvv'] ?? '';

// Validação básica dos dados
if (empty($numero_cartao) || !preg_match('/^\d{16}$/', $numero_cartao) || empty($nome_titular) || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $data_validade) || empty($cvv) || !preg_match('/^\d{3,4}$/', $cvv)) {
    header('Location: cadastrar_pagamento.php?error=invalid_data');
    exit;
}

// Criptografa dados sensíveis para armazenamento
try {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_CIPHER));
    
    // O IV é pré-anexado ao dado criptografado para poder ser recuperado na decriptografia
    $encrypted_numero = base64_encode($iv . openssl_encrypt($numero_cartao, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv));
    $encrypted_cvv = base64_encode($iv . openssl_encrypt($cvv, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv));

} catch (Exception $e) {
    header('Location: cadastrar_pagamento.php?error=encryption_failed');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$cartaoData = [
    'numero_cartao_encrypted' => $encrypted_numero,
    'nome_titular' => $nome_titular,
    'data_validade' => $data_validade,
    'cvv_encrypted' => $encrypted_cvv,
    'last_updated' => new MongoDB\BSON\UTCDateTime()
];

// Salva os dados no MongoDB
try {
    $client = new MongoDB\Client("mongodb://127.0.0.1:27017");
    $clientesCollection = $client->selectDatabase('CDs_&_vinil')->selectCollection('clientes');

    $updateResult = $clientesCollection->updateOne(
        ['_id' => $userId],
        ['$set' => ['cartao' => $cartaoData]]
    );

    if ($updateResult->getModifiedCount() > 0 || $updateResult->getMatchedCount() > 0) {
        // Redireciona para a próxima etapa do checkout
        header('Location: checkout.php');
        exit;
    } else {
        // O usuário não foi encontrado, o que não deveria acontecer se ele está logado
        header('Location: cadastrar_pagamento.php?error=user_not_found');
        exit;
    }

} catch (Exception $e) {
    // Em produção, seria ideal logar o erro em um arquivo de log
    // error_log('Erro ao salvar cartão: ' . $e->getMessage());
    header('Location: cadastrar_pagamento.php?error=db_error');
    exit;
}
