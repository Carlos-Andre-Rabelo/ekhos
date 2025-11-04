<?php
declare(strict_types=1);

// Habilitar a exibição de todos os erros para diagnóstico completo.
// Em um ambiente de produção, isso deve ser desativado.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$albuns = [];
$errorMessage = null;

try {
    // 1. Incluir o autoloader do Composer
    require_once __DIR__ . '/vendor/autoload.php';

    // 2. Verificar se a classe do cliente MongoDB existe
    if (!class_exists('MongoDB\Client')) {
        throw new Exception("Classe MongoDB\Client não encontrada. Verifique a extensão do MongoDB e o autoload do Composer.");
    }

    // 3. Conectar ao MongoDB
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);
    $gravadorasCollection = $database->selectCollection('gravadoras');

    // 4. Pipeline de Agregação Robusto
    $pipeline = [
        // Desmembra o array de álbuns para processar um por um
        ['$unwind' => '$albuns'],

        // Substitui a raiz do documento pelo subdocumento do álbum, mantendo o nome da gravadora
        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$albuns', ['nome_gravadora' => '$nome_gravadora']]]]],

        // Garante que 'artistas_ids' seja um array para o $lookup não falhar
        ['$addFields' => ['artistas_ids' => ['$ifNull' => ['$artistas_ids', []]]]],

        // Junta com a coleção de artistas
        [
            '$lookup' => [
                'from' => 'artistas',
                'localField' => 'artistas_ids',
                'foreignField' => '_id',
                'as' => 'artistas_info'
            ]
        ],

        // Garante que 'generos_ids' seja um array
        ['$addFields' => ['generos_ids' => ['$ifNull' => ['$generos_ids', []]]]],

        // Junta com a coleção de gêneros
        [
            '$lookup' => [
                'from' => 'generos_musicais',
                'localField' => 'generos_ids',
                'foreignField' => '_id',
                'as' => 'generos_info'
            ]
        ],

        // Formata o documento final para exibição
        [
            '$project' => [
                '_id' => 1,
                'titulo' => '$titulo_album',
                'ano' => ['$year' => '$data_lancamento'],
                'duracao' => '$duracao',
                'exemplares' => '$exemplares',
                'gravadora' => '$nome_gravadora',
                // Pega o nome do primeiro artista, ou "Desconhecido" se não houver
                'artista' => ['$ifNull' => [['$arrayElemAt' => ['$artistas_info.nome_artista', 0]], 'Artista Desconhecido']],
                // Pega a lista de nomes de gêneros
                'generos' => '$generos_info.nome_genero_musical',                
                // Pega a primeira imagem do array, ou usa um placeholder se o array estiver vazio/nulo
                'url_capa' => [
                    '$ifNull' => [['$arrayElemAt' => ['$imagens_capas', 0]], 'https://via.placeholder.com/300/1e1e1e/bb86fc?text=Capa']
                ]
            ]
        ],

        // Ordena o resultado final
        ['$sort' => ['artista' => 1, 'titulo' => 1]]
    ];

    $cursor = $gravadorasCollection->aggregate($pipeline);
    $albuns = $cursor->toArray();

} catch (Exception $e) {
    // Captura qualquer erro e o prepara para exibição
    $errorMessage = "<strong>Ocorreu um erro fatal:</strong><br>" . $e->getMessage() . "<br><br><strong>Arquivo:</strong> " . $e->getFile() . "<br><strong>Linha:</strong> " . $e->getLine();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ēkhos - Sua Coleção</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <h1>ēkhos</h1>
        <div class="search-container">
            <input type="search" id="search-bar" placeholder="Buscar por álbum, artista ou gênero...">
        </div>
        <div class="header-actions" style="margin-top: 1.5rem;">
            <a href="add_album.php" class="edit-link" style="padding: 0.8rem 1.5rem; font-size: 1rem;">+ Adicionar Novo Álbum</a>
        </div>
    </header>

    <main>
        <div id="album-grid">
            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <p><?= $errorMessage // Exibe o erro formatado, sem escapar HTML ?></p>
                </div>
            <?php elseif (empty($albuns)): ?>
                <p class="info-message">Nenhum álbum encontrado no banco de dados.</p>
            <?php else: ?>
                <?php foreach ($albuns as $album): ?>
                    <div class="album-card" 
                         data-titulo="<?= htmlspecialchars($album['titulo'] ?? 'N/A') ?>"
                         data-artista="<?= htmlspecialchars($album['artista'] ?? 'N/A') ?>"
                         data-ano="<?= htmlspecialchars((string)($album['ano'] ?? 'N/A')) ?>"
                         data-genero="<?= htmlspecialchars(implode(', ', (array)($album['generos'] ?? []))) ?>"
                         data-gravadora="<?= htmlspecialchars($album['gravadora'] ?? 'N/A') ?>"
                         data-duracao="<?= htmlspecialchars($album['duracao'] ?? 'N/A') ?>"
                         data-capa="<?= htmlspecialchars($album['url_capa'] ?? '') ?>"
                         data-exemplares="<?= htmlspecialchars(json_encode($album['exemplares'] ?? [])) ?>">
                        
                        <img src="<?= htmlspecialchars($album['url_capa'] ?? 'https://via.placeholder.com/300?text=Capa') ?>" alt="Capa do álbum <?= htmlspecialchars($album['titulo']) ?>">
                        <div class="album-info">
                            <h3><?= htmlspecialchars($album['titulo'] ?? 'Título Desconhecido') ?></h3>
                            <p><?= htmlspecialchars($album['artista'] ?? 'Artista Desconhecido') ?></p>
                            <div class="card-actions">
                                <a href="edit_album.php?id=<?= $album['_id'] ?>" class="edit-link">Editar</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal (Popup) -->
    <div id="album-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-body">
                <img id="modal-capa" src="" alt="Capa do álbum">
                <div class="modal-details">
                    <h2 id="modal-titulo"></h2>
                    <p id="modal-artista"></p>
                    <div class="modal-meta">
                        <span><strong>Ano:</strong> <span id="modal-ano"></span></span>
                        <span><strong>Gênero:</strong> <span id="modal-genero"></span></span>
                        <span><strong>Gravadora:</strong> <span id="modal-gravadora"></span></span>
                        <span><strong>Duração:</strong> <span id="modal-duracao"></span></span>
                    </div>
                    <h3>Exemplares Disponíveis:</h3>
                    <ul id="modal-exemplares"></ul>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> ēkhos</p>
    </footer>

    <script src="script.js"></script>

</body>
</html>