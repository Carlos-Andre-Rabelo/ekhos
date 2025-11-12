<?php
declare(strict_types=1);

//controlador da sessao
require_once __DIR__ . '/login/sessao.php';

//mostra os erros
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

//define uri e nome do banco
$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$albuns = [];
$errorMessage = null;

try {
    //carrega autoloader do composer
    require_once __DIR__ . '/vendor/autoload.php';

    //verifica se o cliente mongodb existe
    if (!class_exists('MongoDB\Client')) {
        throw new Exception("Classe MongoDB\Client não encontrada. Verifique a extensão do MongoDB e o autoload do Composer.");
    }

    //conecta no mongodb
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);

    //collection principal = albuns
    $albunsCollection = $database->selectCollection('albuns');

    //agregacoes
    $pipeline = [
        //lookup eh equivalente a um left join
        //join com gravadoras
        [
            '$lookup' => [
                'from' => 'gravadoras',
                'localField' => 'gravadora_id', //no album
                'foreignField' => '_id', //na gravadora
                'as' => 'gravadora_info'
            ]
        ],

        //join com artistas
        [
            '$lookup' => [
                'from' => 'artistas',
                'localField' => 'artistas_ids', //no album
                'foreignField' => '_id', //no artista
                'as' => 'artistas_info'
            ]
        ],

        //join com generos musicais
        [
            '$lookup' => [
                'from' => 'generos_musicais',
                'localField' => 'generos_ids', //no album
                'foreignField' => '_id', //no genero musical
                'as' => 'generos_info'
            ]
        ],

        //campos exibidos
        [
            '$project' => [
                '_id' => '$_id',
                'titulo' => '$titulo_album',
                'ano' => ['$year' => '$data_lancamento'],
                'duracao' => '$duracao',
                'formatos' => '$formatos', //cds e vinis 
                'gravadora' => ['$ifNull' => [['$arrayElemAt' => ['$gravadora_info.nome_gravadora', 0]], 'Gravadora Desconhecida']],
                'artista' => ['$ifNull' => [['$arrayElemAt' => ['$artistas_info.nome_artista', 0]], 'Artista Desconhecido']],
                'generos' => '$generos_info.nome_genero_musical',
                'url_capa' => [
                    '$ifNull' => [['$arrayElemAt' => ['$imagens_capas', 0]], 'https://via.placeholder.com/300/1e1e1e/bb86fc?text=Capa']
                ]
            ]
        ],

        //coloca em ordem alfabetica de artista e depois por titulo
        ['$sort' => ['artista' => 1, 'titulo' => 1]]
    ];

    // Executa a agregação na coleção 'albuns'
    $cursor = $albunsCollection->aggregate($pipeline);
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
<body data-user-role="<?php
    if (is_admin()) {
        echo 'admin';
    } elseif (is_client()) {
        echo 'client';
    } else {
        echo 'guest';
    }
?>">

    <header>
        <div class="header-main">
            <h1>ēkhos</h1>
            <div class="search-container">
                <input type="search" id="search-bar" placeholder="Buscar por álbum, artista ou gênero...">
            </div>
        </div>
        <div class="header-user-actions">
            <?php if (is_logged_in()): ?>
                <?php if (is_admin()): ?>
                    <!-- Ações para Administradores -->
                    <a href="add_album.php" class="btn-header">+ Adicionar Novo Álbum</a>
                <?php else: ?>
                    <!-- Ações para Clientes -->
                    <a href="carrinho.php" class="btn-header">Meu Carrinho</a>
                <?php endif; ?>

                <!-- Mostra informações do usuário e botão de logout -->
                <div class="user-session">
                    <span>Olá, <?= htmlspecialchars($_SESSION['user_name']) ?>!</span>
                    <a href="login/logout.php" class="btn-header btn-logout">Sair</a>
                </div>
            <?php else: ?>
                <!-- Mostra o botão de login se não houver sessão ativa -->
                <a href="login/login.php" class="btn-header">Login</a>
            <?php endif; ?>
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
                         data-id="<?= htmlspecialchars((string)($album['_id'] ?? '')) ?>"
                         data-titulo="<?= htmlspecialchars($album['titulo'] ?? 'N/A') ?>"
                         data-artista="<?= htmlspecialchars($album['artista'] ?? 'N/A') ?>"
                         data-ano="<?= htmlspecialchars((string)($album['ano'] ?? 'N/A')) ?>"
                         data-genero="<?= htmlspecialchars(implode(', ', (array)($album['generos'] ?? []))) ?>"
                         data-gravadora="<?= htmlspecialchars($album['gravadora'] ?? 'N/A') ?>"
                         data-duracao="<?= htmlspecialchars($album['duracao'] ?? 'N/A') ?>"
                         data-capa="<?= htmlspecialchars($album['url_capa'] ?? '') ?>"
                         data-formatos="<?= htmlspecialchars(json_encode($album['formatos'] ?? [])) ?>">
                        
                        <img src="<?= htmlspecialchars($album['url_capa'] ?? 'https://via.placeholder.com/300?text=Capa') ?>" alt="Capa do álbum <?= htmlspecialchars($album['titulo']) ?>">
                        <div class="album-info">
                            <h3><?= htmlspecialchars($album['titulo'] ?? 'Título Desconhecido') ?></h3>
                            <p><?= htmlspecialchars($album['artista'] ?? 'Artista Desconhecido') ?></p>
                            <?php if (is_admin()): ?>
                                <div class="card-actions">
                                    <a href="edit_album.php?id=<?= $album['_id'] ?>" class="edit-link">Editar</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

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
                        <span id="modal-genero-display"></span>
                        <span><strong>Gravadora:</strong> <span id="modal-gravadora"></span></span>
                        <span><strong>Duração:</strong> <span id="modal-duracao"></span></span>
                    </div>

                    <h3>Formatos Disponíveis:</h3>
                    <ul id="modal-formatos"></ul>
                    <?php if (is_client()): ?>
                        <div id="client-actions" class="client-actions-container"></div>
                    <?php endif; ?>
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