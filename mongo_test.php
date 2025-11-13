<?php

declare(strict_types=1);

echo '<h1>Teste de Conexão com MongoDB</h1>';

//inclui o autoloader do Composer para carregar a biblioteca do MongoDB
require_once __DIR__ . '/vendor/autoload.php';

//string de conexão padrão para um MongoDB local.
//altere se o seu MongoDB estiver em um host ou porta diferente.
$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";

try {
    //cria um novo cliente e conecta ao servidor
    $client = new MongoDB\Client($mongoUri);

    echo "<p style='color: green;'>Conexão com o servidor MongoDB estabelecida com sucesso!</p>";

    //seleciona o banco de dados
    $database = $client->selectDatabase($dbName);
    echo "<p>Banco de dados '<strong>" . htmlspecialchars($dbName) . "</strong>' selecionado.</p>";

    //lista as coleções para verificar o acesso
    $collections = $database->listCollectionNames();

    $collectionNames = iterator_to_array($collections);

    if (empty($collectionNames)) {
        echo "<p>Nenhuma coleção encontrada no banco de dados '<strong>" . htmlspecialchars($dbName) . "</strong>'.</p>";
    } else {
        echo "<h2>Coleções encontradas:</h2>";
        echo "<ul>";
        foreach ($collectionNames as $collectionName) {
            echo "<li>";
            echo "<strong>" . htmlspecialchars($collectionName) . "</strong>";
            
            //busca e exibe documentos de cada colecao
            $collection = $database->selectCollection($collectionName);
            $documents = $collection->find([], ['limit' => 5]);
            
            echo "<ul>";
            $docCount = 0;
            foreach ($documents as $document) {
                echo "<li><pre>" . htmlspecialchars(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre></li>";
                $docCount++;
            }
            if ($docCount === 0) {
                echo "<li><em>Nenhum documento nesta coleção.</em></li>";
            }
            echo "</ul>";
            echo "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Erro:</strong> Não foi possível conectar ou interagir com o MongoDB.</p>";
    echo "<p style='color: red;'><strong>Mensagem:</strong> " . $e->getMessage() . "</p>";
}