<?php
// c:/xampp/htdocs/ekhos/db_connect.php

// Inclui o autoloader do Composer para carregar a biblioteca do MongoDB.
// O __DIR__ garante que o caminho será sempre relativo ao arquivo atual.
require_once __DIR__ . '/vendor/autoload.php';

// Tenta pegar a variável de ambiente MONGODB_URI (usada em servidores como o Railway).
// Se a variável não existir, usa a string de conexão para o banco de dados local.
$uri = getenv('MONGODB_URI') ?: "mongodb://localhost:27017";

try {
    // Cria uma nova instância do cliente MongoDB.
    $client = new MongoDB\Client($uri);
    
    // Seleciona o banco de dados. 
    // O nome 'CDs_&_vinil' deve ser exatamente o mesmo do seu banco no MongoDB Atlas.
    $database = $client->selectDatabase('CDs_&_vinil'); 
    
} catch (Exception $e) {
    // Se a conexão falhar, o script é interrompido e uma mensagem de erro é exibida.
    header('HTTP/1.1 500 Internal Server Error');
    die("Erro: Não foi possível conectar ao banco de dados. Detalhes: " . $e->getMessage());
}