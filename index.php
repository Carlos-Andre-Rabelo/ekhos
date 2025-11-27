<?php
declare(strict_types=1);

// Inicia a sessão para poder ler as mensagens de feedback
session_start();
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
//controlador da sessao
require_once __DIR__ . '/login/sessao.php';

//mostra os erros
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Inclui o arquivo de conexão centralizado.
// A variável $database já estará disponível para uso.
require_once __DIR__ . '/db_connect.php';

$albuns = [];
$generos = [];
$errorMessage = null;
$cartCount = 0;

// Busca quantidade de itens no carrinho
if (is_client()) {
    try {
        $clientesCollection = $database->selectCollection('clientes');
        $userId = (int)$_SESSION['user_id'];
        $cliente = $clientesCollection->findOne(['_id' => $userId]);
        
        if ($cliente && isset($cliente['carrinho'])) {
            $cartCount = count($cliente['carrinho']);
        }
    } catch (Exception $e) {
        // Ignora erro ao buscar carrinho
    }
}

// Configuração de paginação
$itensPorPagina = 12;
$paginaAtual = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;
$totalAlbuns = 0;
$totalPaginas = 0;

try {
    //collection principal = albuns
    $albunsCollection = $database->selectCollection('albuns');
    $generosCollection = $database->selectCollection('generos_musicais');
    
    // Busca todos os gêneros para os filtros
    $generos = $generosCollection->find([], ['sort' => ['nome_genero_musical' => 1]])->toArray();
    
    // Conta o total de álbuns
    $totalAlbuns = $albunsCollection->countDocuments();
    $totalPaginas = ceil($totalAlbuns / $itensPorPagina);

    //agregacoes
    $pipeline = [
        //lookup eh equivalente a um left join
        //join com gravadoras
        [
            '$lookup' => [
                'from' => 'gravadora',
                'localField' => 'gravadora_id', //no album
                'foreignField' => '_id', //na gravadora
                'as' => 'gravadora_info'
            ]
        ],

        //join com artistas
        [
            '$lookup' => [
                'from' => 'artista',
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
        ['$sort' => ['artista' => 1, 'titulo' => 1]],
        
        // Paginação
        ['$skip' => $offset],
        ['$limit' => $itensPorPagina]
    ];

    //agregação na collection albuns
    $cursor = $albunsCollection->aggregate($pipeline);
    $albuns = $cursor->toArray();

} catch (Exception $e) {
    //exibicao de mensagem de erro
    $errorMessage = "<strong>Ocorreu um erro fatal:</strong><br>" . $e->getMessage() . "<br><br><strong>Arquivo:</strong> " . $e->getFile() . "<br><strong>Linha:</strong> " . $e->getLine();
}
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"> <!--aparece caracteres especiais e acentos-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!--ajusta a tela-->
    <title>ēkhos - Sua Coleção</title> <!--titulo da aba-->

    <!--fontes-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <!--estilos-->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="ekhos_features.css">
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos para Tablet (abaixo de 950px) */
        @media (max-width: 950px) {
            header {
                flex-direction: column;
                align-items: stretch;
                padding: 1rem;
            }

            .header-main, .header-user-actions {
                width: 100%;
                justify-content: center;
            }

            .header-user-actions {
                margin-top: 1rem;
                flex-wrap: wrap;
            }

            .modal-content {
                width: 90%;
                max-width: 600px;
            }
        }

        /* Estilos para Celular (abaixo de 450px) */
        @media (max-width: 450px) {
            .header-main {
                flex-direction: column;
            }
            .search-container {
                width: 100%;
                margin-top: 0.5rem;
            }
            .modal-body {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body data-user-role="<?php
    //controle de sessão
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
            <h1>ēkhos</h1> <!--nome do site-->
            <div class="search-container"> <!--barra de busca-->
                <input type="search" id="search-bar" placeholder="Buscar por álbum, artista ou gênero..." autocomplete="off">
                <div id="search-suggestions" class="search-suggestions"></div>
            </div>
            <button id="theme-toggle" class="theme-toggle" title="Alternar Tema">
                <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
        </div>
        <div class="header-user-actions">
            <?php if (is_logged_in()): ?>
                <?php if (is_admin()): ?>
                    <!--administrador-->
                    <a href="Pedidos/pedidos.php" class="btn-header">Gerenciar Pedidos</a>
                    <a href="add_album.php" class="btn-header">Adicionar Álbum</a>
                <?php else: ?>
                    <!--cliente -->
                    <a href="Pedidos/pedidos.php" class="btn-header">Meus Pedidos</a>
                    <a href="carrinho/carrinho.php" class="btn-header btn-cart">
                        Carrinho
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <!--info usuario e logout (para todos logados)-->
                <div class="user-session">
                    <span>Olá, <?= htmlspecialchars($_SESSION['user_name']) ?>!</span>
                    <a href="login/logout.php" class="btn-header btn-logout">Sair</a>
                </div>
            <?php else: ?>
                <!--login (nao logado)-->
                <a href="login/login.php" class="btn-header">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <!--verificação de mensagem-->
        <?php if ($message): ?>
            <div class="message-container">
                <div class="message <?= $message['type'] ?>"><?= htmlspecialchars($message['text']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Seção de Filtros e Controles -->
        <div class="filters-section">
            <div class="filters-header">
                <button id="toggle-filters" class="btn-toggle-filters">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    Filtros
                </button>
                
                <div class="view-controls">
                    <?php if (is_client()): ?>
                        <button id="show-favorites" class="view-btn" title="Mostrar Favoritos">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        </button>
                    <?php endif; ?>
                    <button id="view-grid" class="view-btn active" title="Visualização em Grade">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    </button>
                    <button id="view-list" class="view-btn" title="Visualização em Lista">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    </button>
                </div>
            </div>

            <div id="filters-panel" class="filters-panel">
                <div class="filters-grid">
                    <!-- Filtro por Gênero -->
                    <div class="filter-group">
                        <label>Gênero Musical</label>
                        <select id="filter-genero" class="filter-select">
                            <option value="">Todos os gêneros</option>
                            <?php foreach ($generos as $genero): ?>
                                <option value="<?= htmlspecialchars($genero['nome_genero_musical']) ?>">
                                    <?= htmlspecialchars($genero['nome_genero_musical']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro por Formato -->
                    <div class="filter-group">
                        <label>Formato</label>
                        <select id="filter-formato" class="filter-select">
                            <option value="">Todos os formatos</option>
                            <option value="cd">CD</option>
                            <option value="vinil">Vinil</option>
                        </select>
                    </div>

                    <!-- Filtro por Preço -->
                    <div class="filter-group">
                        <label>Faixa de Preço</label>
                        <select id="filter-preco" class="filter-select">
                            <option value="">Todos os preços</option>
                            <option value="0-50">Até R$ 50</option>
                            <option value="50-100">R$ 50 - R$ 100</option>
                            <option value="100-150">R$ 100 - R$ 150</option>
                            <option value="150-999">Acima de R$ 150</option>
                        </select>
                    </div>

                    <!-- Filtro por Ano -->
                    <div class="filter-group">
                        <label>Ano de Lançamento</label>
                        <select id="filter-ano" class="filter-select">
                            <option value="">Todos os anos</option>
                            <option value="2025">2025</option>
                            <option value="2024">2024</option>
                            <option value="2023">2023</option>
                            <option value="2022">2022</option>
                            <option value="2021">2021</option>
                            <option value="2020-antes">2020 ou anterior</option>
                        </select>
                    </div>

                    <!-- Ordenação -->
                    <div class="filter-group">
                        <label>Ordenar por</label>
                        <select id="filter-ordenacao" class="filter-select">
                            <option value="artista-asc">Artista (A-Z)</option>
                            <option value="artista-desc">Artista (Z-A)</option>
                            <option value="titulo-asc">Título (A-Z)</option>
                            <option value="titulo-desc">Título (Z-A)</option>
                            <option value="ano-desc">Mais recentes</option>
                            <option value="ano-asc">Mais antigos</option>
                            <option value="preco-asc">Menor preço</option>
                            <option value="preco-desc">Maior preço</option>
                        </select>
                    </div>

                    <!-- Botão Limpar Filtros -->
                    <div class="filter-group filter-actions">
                        <button id="clear-filters" class="btn-clear-filters">Limpar Filtros</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="album-grid">
            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <p><?= $errorMessage //mostra o erro ?></p>
                </div>

            <?php elseif (empty($albuns)): ?>
                <p class="info-message">Nenhum álbum encontrado no banco de dados.</p> <!--se nao houver nenhum album-->

            <?php else: ?> <!--card dos albuns-->
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
                        
                        <!--mostra a capa-->
                        <img src="<?= htmlspecialchars($album['url_capa'] ?? 'https://via.placeholder.com/300?text=Capa') ?>" alt="Capa do álbum <?= htmlspecialchars($album['titulo']) ?>">
                        
                        <?php if (is_client()): ?>
                            <button class="btn-favorite" data-album-id="<?= $album['_id'] ?>" title="Adicionar aos favoritos">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="heart-icon"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                            </button>
                        <?php endif; ?>
                        
                        <!--mostra titulo e artista-->
                        <div class="album-info">
                            <h3><?= htmlspecialchars($album['titulo'] ?? 'Título Desconhecido') ?></h3>
                            <p><?= htmlspecialchars($album['artista'] ?? 'Artista Desconhecido') ?></p>
                            <!--adm (botao de editar)-->
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

        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Mostrando <?= count($albuns) ?> de <?= $totalAlbuns ?> álbuns
                </div>
                <div class="pagination">
                    <?php if ($paginaAtual > 1): ?>
                        <a href="?page=1" class="pagination-btn" title="Primeira página">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"></polyline><polyline points="18 17 13 12 18 7"></polyline></svg>
                        </a>
                        <a href="?page=<?= $paginaAtual - 1 ?>" class="pagination-btn" title="Página anterior">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </a>
                    <?php endif; ?>

                    <?php
                    // Lógica para mostrar números de páginas
                    $range = 2; // Quantas páginas mostrar antes e depois da atual
                    $inicio = max(1, $paginaAtual - $range);
                    $fim = min($totalPaginas, $paginaAtual + $range);

                    // Mostra primeira página se não estiver no range
                    if ($inicio > 1) {
                        echo '<a href="?page=1" class="pagination-btn">1</a>';
                        if ($inicio > 2) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                    }

                    // Mostra páginas no range
                    for ($i = $inicio; $i <= $fim; $i++) {
                        if ($i == $paginaAtual) {
                            echo '<span class="pagination-btn active">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . '" class="pagination-btn">' . $i . '</a>';
                        }
                    }

                    // Mostra última página se não estiver no range
                    if ($fim < $totalPaginas) {
                        if ($fim < $totalPaginas - 1) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        echo '<a href="?page=' . $totalPaginas . '" class="pagination-btn">' . $totalPaginas . '</a>';
                    }
                    ?>

                    <?php if ($paginaAtual < $totalPaginas): ?>
                        <a href="?page=<?= $paginaAtual + 1 ?>" class="pagination-btn" title="Próxima página">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                        <a href="?page=<?= $totalPaginas ?>" class="pagination-btn" title="Última página">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!--modal dos albuns-->
    <!--pega as infos do card e coloca no modal-->
    <div id="album-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-body-scrollable">
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
    </div>

    <!-- Barra de Navegação para Mobile -->
    <nav class="mobile-nav">
        <?php if (is_logged_in()): ?>
            <?php if (is_admin()): ?>
                <a href="add_album.php" class="btn-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Adicionar</span>
                </a>
                <a href="Pedidos/pedidos.php" class="btn-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                    <span>Pedidos</span>
                </a>
            <?php else: ?>
                <a href="Pedidos/pedidos.php" class="btn-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    <span>Pedidos</span>
                </a>
                <a href="carrinho/carrinho.php" class="btn-header btn-cart">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    <span>Carrinho</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="login/logout.php" class="btn-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Sair</span>
            </a>
        <?php else: ?>
            <a href="login/login.php" class="btn-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                <span>Pedidos</span>
            </a>
            <a href="login/login.php" class="btn-header btn-cart">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                <span>Carrinho</span>
            </a>
            <a href="login/login.php" class="btn-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                <span>Login</span>
            </a>
        <?php endif; ?>
    </nav>

    <footer>
        <p>&copy; <?= date('Y') ?> ēkhos</p>
    </footer>

    <!-- Contêiner para as notificações flutuantes (toasts) -->
    <div id="toast-container"></div>

    <script src="script.js"></script>
    <script src="pagination.js"></script>
    
    <!-- Novos Scripts de Funcionalidades -->
    <script src="preview_player.js"></script>
    <script src="ekhos_features.js"></script>
    
    <script>
    // Variável global para o usuário logado
    const userLogado = <?= is_logged_in() ? $_SESSION['user_id'] : 'null' ?>;
    
    // Newsletter
    async function inscreverNewsletter(event) {
        event.preventDefault();
        const email = document.getElementById('email-newsletter').value;
        
        const formData = new FormData();
        formData.append('action', 'inscrever');
        formData.append('email', email);
        
        try {
            const response = await fetch('newsletter_actions.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                mostrarNotificacao(data.message, 'success');
                document.getElementById('form-newsletter').reset();
            } else {
                mostrarNotificacao(data.message, 'warning');
            }
        } catch (error) {
            mostrarNotificacao('Erro ao inscrever', 'error');
        }
    }
    
    // Wishlist
    async function abrirWishlist() {
        window.location.href = 'wishlist.php';
    }
    
    // Modal de Fidelidade
    async function abrirModalFidelidade() {
        try {
            const [statusRes, beneficiosRes] = await Promise.all([
                fetch('fidelidade_actions.php?action=status'),
                fetch('fidelidade_actions.php?action=beneficios_nivel')
            ]);
            
            const status = await statusRes.json();
            const beneficios = await beneficiosRes.json();
            
            if (!status.success || !beneficios.success) return;
            
            const modal = document.createElement('div');
            modal.className = 'modal-fidelidade';
            modal.innerHTML = `
                <div class="modal-content">
                    <span class="close-modal" onclick="this.parentElement.parentElement.remove()">&times;</span>
                    <h2>🏆 Programa de Fidelidade</h2>
                    
                    <div class="fidelidade-status">
                        <div class="status-header">
                            <span class="nivel-badge nivel-${status.nivel_atual}">${status.nivel_atual.toUpperCase()}</span>
                            <span class="pontos-display">${status.pontos} pontos</span>
                        </div>
                        
                        ${status.proximo_nivel !== 'máximo' ? `
                            <div class="progresso-container">
                                <div class="progresso-bar" style="width: ${(status.pontos / (status.pontos + status.pontos_para_proximo)) * 100}%"></div>
                            </div>
                            <p>Faltam ${status.pontos_para_proximo} pontos para ${status.proximo_nivel.toUpperCase()}</p>
                        ` : '<p class="nivel-max">🎉 Você atingiu o nível máximo!</p>'}
                    </div>
                    
                    <div class="beneficios-grid">
                        ${Object.entries(beneficios.beneficios).map(([nivel, info]) => `
                            <div class="beneficio-card ${nivel === status.nivel_atual ? 'atual' : ''}">
                                <h3 class="nivel-badge nivel-${nivel}">${nivel.toUpperCase()}</h3>
                                <ul>
                                    ${info.beneficios.map(b => `<li>${b}</li>`).join('')}
                                </ul>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="resgate-pontos">
                        <h3>Resgatar Pontos</h3>
                        <p>100 pontos = R$ 10,00 de desconto</p>
                        <input type="number" id="pontos-resgatar" min="100" step="100" max="${status.pontos}" placeholder="Quantidade de pontos">
                        <button class="btn-primary" onclick="resgatarPontos()">Resgatar</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        } catch (error) {
            console.error('Erro ao abrir modal de fidelidade:', error);
        }
    }
    
    async function resgatarPontos() {
        const pontos = parseInt(document.getElementById('pontos-resgatar').value);
        
        if (!pontos || pontos < 100) {
            mostrarNotificacao('Mínimo de 100 pontos', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'resgatar_pontos');
        formData.append('pontos', pontos);
        
        try {
            const response = await fetch('fidelidade_actions.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                mostrarNotificacao(data.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                mostrarNotificacao(data.message, 'error');
            }
        } catch (error) {
            mostrarNotificacao('Erro ao resgatar pontos', 'error');
        }
    }
    </script>

    <script>
    // Adiciona a lógica de clique para administradores em dispositivos móveis
    document.addEventListener('DOMContentLoaded', function() {
        const isAdmin = document.body.getAttribute('data-user-role') === 'admin';
        const isMobile = window.innerWidth < 768;

        if (isAdmin && isMobile) {
            const albumCards = document.querySelectorAll('.album-card');
            albumCards.forEach(card => {
                // Remove o listener que abre o modal para evitar conflito
                const cardClone = card.cloneNode(true);
                card.parentNode.replaceChild(cardClone, card);

                // Adiciona o novo listener para redirecionar
                cardClone.addEventListener('click', function(event) {
                    // Impede que o clique em um link dentro do card (se houver) seja interceptado
                    if (event.target.tagName.toLowerCase() === 'a') {
                        return;
                    }
                    
                    const albumId = this.dataset.id;
                    if (albumId) {
                        window.location.href = `edit_album.php?id=${albumId}`;
                    }
                });
            });
        }
    });
    </script>
</body>
</html>