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
                <input type="search" id="search-bar" placeholder="Buscar por álbum, artista ou gênero...">
            </div>
        </div>
        <div class="header-user-actions">
            <?php if (is_logged_in()): ?>
                <?php if (is_admin()): ?>
                    <!--administrador-->
                    <a href="add_album.php" class="btn-header">+ Adicionar Novo Álbum</a>
                <?php else: ?>
                    <!--cliente -->
                    <a href="carrinho/carrinho.php" class="btn-header">Meu Carrinho</a>
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
    </main>

    <!--modal dos albuns-->
    <!--pega as infos do card e coloca no modal-->
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('album-modal');
        const modalClose = modal.querySelector('.modal-close');
        const albumGrid = document.getElementById('album-grid');
        const body = document.body;
        const userRole = body.dataset.userRole;

        // Função para abrir o modal
        const openModal = (card) => {
            // Preenche os dados básicos do modal
            document.getElementById('modal-titulo').textContent = card.dataset.titulo;
            document.getElementById('modal-artista').textContent = card.dataset.artista;
            document.getElementById('modal-ano').textContent = card.dataset.ano;
            document.getElementById('modal-gravadora').textContent = card.dataset.gravadora;
            document.getElementById('modal-duracao').textContent = card.dataset.duracao;
            document.getElementById('modal-capa').src = card.dataset.capa;
            
            // Preenche os gêneros
            const generos = card.dataset.genero.split(',').map(g => g.trim());
            const generoDisplay = document.getElementById('modal-genero-display');
            generoDisplay.innerHTML = `<strong>Gêneros:</strong> ${generos.join(', ')}`;

            // Limpa e preenche os formatos disponíveis
            const formatosList = document.getElementById('modal-formatos');
            formatosList.innerHTML = '';
            const formatos = JSON.parse(card.dataset.formatos);

            formatos.forEach(formato => {
                const li = document.createElement('li');
                
                const isOutOfStock = parseInt(formato.quantidade_estoque, 10) <= 0;
                const precoFormatado = parseFloat(formato.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

                let actionHtml = '';
                if (userRole === 'guest') {
                    actionHtml = `<a href="/ekhos/login/login.php" class="btn-add-cart-login">Login para Comprar</a>`;
                } else if (userRole === 'client') {
                    if (isOutOfStock) {
                        actionHtml = `<span class="out-of-stock">Esgotado</span>`;
                    } else {
                        actionHtml = `
                            <div class="formato-acao-cliente">
                                <input type="number" class="quantidade-input" value="1" min="1" max="${formato.quantidade_estoque}" aria-label="Quantidade">
                                <button class="btn-add-cart-icon" data-album-id="${card.dataset.id}" data-formato-tipo="${formato.tipo}" title="Adicionar ao carrinho">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                </button>
                            </div>
                        `;
                    }
                }

                li.innerHTML = `
                    <div class="formato-info">
                        <span class="formato-tipo">${formato.tipo.charAt(0).toUpperCase() + formato.tipo.slice(1).replace('_', ' ')}</span>
                        <span class="formato-preco">${precoFormatado}</span>
                    </div>
                    ${actionHtml}
                `;
                formatosList.appendChild(li);
            });

            modal.style.display = 'flex';
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'scale(1)';
                body.classList.add('modal-open');
            }, 10);
        };

        // Função para fechar o modal
        const closeModal = () => {
            modal.style.opacity = '0';
            modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
            body.classList.remove('modal-open');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        };

        // Event listener para abrir o modal
        albumGrid.addEventListener('click', function(e) {
            const card = e.target.closest('.album-card');
            // Não abre o modal se o clique foi no link de editar do admin
            if (card && !e.target.closest('.edit-link')) {
                openModal(card);
            }
        });

        // Event listener para fechar o modal
        modalClose.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Event listener para adicionar ao carrinho (delegação de evento)
        modal.addEventListener('click', function(e) {
            const addButton = e.target.closest('.btn-add-cart-icon');
            if (addButton) {
                const albumId = addButton.dataset.albumId;
                const formatoTipo = addButton.dataset.formatoTipo;
                const quantidadeInput = addButton.parentElement.querySelector('.quantidade-input');
                const quantidade = quantidadeInput.value;

                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('album_id', albumId);
                formData.append('formato_tipo', formatoTipo);
                formData.append('quantidade', quantidade);

                // **A CHAMADA CORRIGIDA**
                fetch('/ekhos/carrinho/cart_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message); // Feedback simples para o usuário
                        closeModal();
                    } else {
                        alert('Erro: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    alert('Ocorreu um erro ao adicionar o item ao carrinho.');
                });
            }
        });

        // Fechar modal com a tecla Esc
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });
    });
    </script>

</body>
</html>