<?php
declare(strict_types=1);

require_once __DIR__ . '/login/sessao.php';

// Protege a página: apenas administradores podem adicionar álbuns.
if (!is_admin()) {
    header('Location: login/login.php');
    exit;
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$message = null;

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);

    // Buscar dados para preencher os selects do formulário
    // Esta parte agora sempre executa, pois a página só serve para adicionar álbuns
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') === 'add_album') {
        $gravadoras = $database->selectCollection('gravadoras')->find(
            [],
            ['sort' => ['nome_gravadora' => 1], 'projection' => ['_id' => 1, 'nome_gravadora' => 1]]
        )->toArray();
        $artistas = $database->selectCollection('artistas')->find([], ['sort' => ['nome_artista' => 1]])->toArray();
        $generos = $database->selectCollection('generos_musicais')->find([], ['sort' => ['nome_genero_musical' => 1]])->toArray();
    }

    // Roteamento de ações com base no método POST.
    // A requisição pode ser a submissão do formulário principal ou uma chamada AJAX dos modais.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Se for uma requisição AJAX, preparamos uma resposta JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
        }

        $postedAction = $_POST['action'] ?? 'add_album';

        if ($postedAction === 'add_album') {
            // --- Tratamento do Upload da Imagem ---
            $imagePath = null;
            if (isset($_FILES['imagem_capa']) && $_FILES['imagem_capa']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/imagens/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = uniqid() . '-' . basename($_FILES['imagem_capa']['name']);
                $targetFile = $uploadDir . $fileName;
                
                $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $targetFile)) {
                        $imagePath = 'imagens/' . $fileName;
                    } else {
                        throw new Exception("Falha ao mover o arquivo de imagem.");
                    }
                } else {
                    throw new Exception("Formato de imagem inválido. Use JPG, JPEG, PNG, GIF ou WEBP.");
                }
            }

            // --- Montagem dos Formatos (Exemplares) ---
            $formatos = [];
            if (isset($_POST['formatos']) && is_array($_POST['formatos'])) {
                foreach ($_POST['formatos'] as $formato) {
                    if (!empty($formato['tipo']) && !empty($formato['preco']) && !empty($formato['quantidade'])) {
                        $formatos[] = [
                            'tipo' => (string) $formato['tipo'],
                            'preco' => (float) $formato['preco'],
                            'quantidade_estoque' => (int) $formato['quantidade']
                        ];
                    }
                }
            }

            // --- Montagem do Documento do Álbum ---
            $maxAlbumId = 0;
            $lastAlbum = $database->selectCollection('albuns')->findOne([], ['sort' => ['_id' => -1]]);
            if ($lastAlbum) {
                $maxAlbumId = (int)$lastAlbum['_id'];
            }
            $newAlbumId = $maxAlbumId + 1;

            $newAlbum = [
                '_id' => $newAlbumId,
                'titulo_album' => (string) ($_POST['titulo_album'] ?? ''),
                'gravadora_id' => (int) $_POST['gravadora_id'],
                'data_lancamento' => new MongoDB\BSON\UTCDateTime(new DateTime($_POST['data_lancamento'])),
                'imagens_capas' => $imagePath ? [$imagePath] : [],
                'numero_faixas' => (int) ($_POST['numero_faixas'] ?? 0),
                'duracao' => (string) ($_POST['duracao'] ?? '00:00:00'),
                'artistas_ids' => [ (int) ($_POST['artista_id'] ?? 0) ],
                'generos_ids' => array_map('intval', $_POST['generos_ids'] ?? []),
                'formatos' => $formatos
            ];

            $insertResult = $database->selectCollection('albuns')->insertOne($newAlbum);

            if ($insertResult->getInsertedCount() > 0) {
                // Define a mensagem na sessão e redireciona para a página principal
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Álbum "' . htmlspecialchars($newAlbum['titulo_album']) . '" adicionado com sucesso!'];
                header('Location: index.php');
                exit;
            } else {
                throw new Exception("Não foi possível adicionar o álbum.");
            }

        } elseif ($postedAction === 'add_gravadora') {
            $nomeGravadora = $_POST['nome_gravadora'] ?? '';
            if (empty($nomeGravadora)) {
                throw new Exception("O nome da gravadora é obrigatório.");
            }
            
            // Gerar um novo _id inteiro para a gravadora
            $maxId = 0;
            $lastGravadora = $database->selectCollection('gravadoras')->findOne([], ['sort' => ['_id' => -1]]);
            if ($lastGravadora) {
                $maxId = (int)$lastGravadora['_id'];
            }
            $newId = $maxId + 1;

            $newGravadora = [
                '_id' => $newId,
                'nome_gravadora' => (string) $nomeGravadora,
                'email_gravadora' => (string) ($_POST['email_gravadora'] ?? '')
            ];
            $insertResult = $database->selectCollection('gravadoras')->insertOne($newGravadora);

            if ($insertResult->getInsertedCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Gravadora adicionada!', 'newItem' => ['id' => $newId, 'name' => $nomeGravadora]]);
            } else {
                throw new Exception("Falha ao adicionar gravadora.");
            }
            exit; // Termina a execução para requisições AJAX
        } elseif ($postedAction === 'add_artista') {
            $nomeArtista = $_POST['nome_artista'] ?? '';
            if (empty($nomeArtista)) {
                throw new Exception("O nome do artista é obrigatório.");
            }

            // Gerar um novo _id inteiro para o artista
            $maxId = 0;
            $lastArtista = $database->selectCollection('artistas')->findOne([], ['sort' => ['_id' => -1]]);
            if ($lastArtista) {
                $maxId = (int)$lastArtista['_id'];
            }
            $newId = $maxId + 1;

            $newArtista = [
                '_id' => $newId,
                'nome_artista' => (string) $nomeArtista,
                'data_nascimento' => !empty($_POST['data_nascimento']) ? new MongoDB\BSON\UTCDateTime(new DateTime($_POST['data_nascimento'])) : null,
                'albuns_ids' => []
            ];
            $insertResult = $database->selectCollection('artistas')->insertOne($newArtista);

            if ($insertResult->getInsertedCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Artista adicionado!', 'newItem' => ['id' => $newId, 'name' => $nomeArtista]]);
            } else {
                throw new Exception("Falha ao adicionar artista.");
            }
            exit; // Termina a execução para requisições AJAX

        } elseif ($postedAction === 'add_genero') {
            $nomeGenero = $_POST['nome_genero_musical'] ?? '';
            if (empty($nomeGenero)) {
                throw new Exception("O nome do gênero é obrigatório.");
            }

            // Gerar um novo _id inteiro para o gênero
            $maxId = 0;
            $lastGenero = $database->selectCollection('generos_musicais')->findOne([], ['sort' => ['_id' => -1]]);
            if ($lastGenero) {
                $maxId = (int)$lastGenero['_id'];
            }
            $newId = $maxId + 1;

            $newGenero = [
                '_id' => $newId,
                'nome_genero_musical' => (string) $nomeGenero,
            ];
            $insertResult = $database->selectCollection('generos_musicais')->insertOne($newGenero);

            if ($insertResult->getInsertedCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Gênero adicionado!', 'newItem' => ['id' => $newId, 'name' => $nomeGenero]]);
            } else {
                throw new Exception("Falha ao adicionar gênero.");
            }
            exit; // Termina a execução para requisições AJAX
        }
    }
} catch (Exception $e) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    $message = ['type' => 'error', 'text' => 'Erro: ' . $e->getMessage()]; // Para a página principal
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Novo Álbum - ēkhos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> <!-- Mantém o link para o CSS principal -->
</head>
<body>
    <div class="form-container">
        <?php if ($message): ?>
            <div class="message <?= $message['type'] ?>">
                <?= $message['text'] ?>
            </div>
        <?php endif; ?>

        <?php if (isset($gravadoras)): // Verifica se os dados do formulário principal foram carregados ?>
            <div class="form-header">
                <a href="index.php" class="back-link" title="Voltar para a Coleção">&larr;</a>
                <h1>Adicionar Novo Álbum</h1>
            </div>
            <form action="add_album.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_album">
                <div class="form-group">
                    <label for="titulo_album">Título do Álbum</label>
                    <input type="text" id="titulo_album" name="titulo_album" required>
                </div>

                <!--selecionar a gravadora-->
                <div class="form-group">
                    <label for="gravadora_id">Gravadora</label>
                    <div class="form-group-inline">
                        <div class="custom-select-container">
                            <input type="hidden" name="gravadora_id" id="gravadora_id_hidden" value="">
                            <div class="select-selected">Selecione a Gravadora</div>
                            <div class="select-items">
                                <?php foreach ($gravadoras as $gravadora): ?>
                                    <div data-value="<?= $gravadora['_id'] ?>"><?= htmlspecialchars((string)($gravadora['nome_gravadora'] ?? '')) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" class="btn-add-related open-sub-modal" data-modal-id="modal-add-gravadora" title="Adicionar Nova Gravadora">+</button>
                    </div>
                </div>

                <!--selecionar o artista-->                            
                <div class="form-group">
                    <label for="artista_id">Artista</label>
                    <div class="form-group-inline">
                        <div class="custom-select-container">
                            <input type="hidden" name="artista_id" id="artista_id_hidden" value="">
                            <div class="select-selected">Selecione o Artista</div>
                            <div class="select-items">
                                <?php foreach ($artistas as $artista): ?>
                                    <div data-value="<?= $artista['_id'] ?>"><?= htmlspecialchars($artista['nome_artista']) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" class="btn-add-related open-sub-modal" data-modal-id="modal-add-artista" title="Adicionar Novo Artista">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Gêneros</label>
                    <div class="checkbox-group-container">
                        <?php foreach ($generos as $genero): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="generos_ids[]" value="<?= $genero['_id'] ?>">
                                <span class="custom-checkbox"></span>
                                <span class="genre-name"><?= htmlspecialchars($genero['nome_genero_musical']) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <button type="button" class="btn-add-related open-sub-modal" data-modal-id="modal-add-genero" title="Adicionar Novo Gênero">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="data_lancamento">Data de Lançamento</label>
                    <input type="date" id="data_lancamento" name="data_lancamento" required>
                </div>

                <div class="form-group">
                    <label for="numero_faixas">Quantidade de Faixas</label>
                    <input type="number" id="numero_faixas" name="numero_faixas" min="1" required>
                </div>

                <div class="form-group">
                    <label for="duracao">Duração</label>
                    <input type="text" id="duracao" name="duracao" pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" placeholder="hh:mm:ss" required>
                </div>

                <div class="form-group">
                    <label for="imagem_capa">Capa do Álbum</label>
                    <input type="file" id="imagem_capa" name="imagem_capa" accept="image/*">
                    <!-- Contêiner para a pré-visualização da imagem -->
                    <img id="image-preview" src="#" alt="Pré-visualização da capa" class="image-preview">
                </div>

                <div class="form-group">
                    <div class="formatos-section" style="display: none;">
                        <h3>Formatos (Exemplares)</h3>
                        <div id="formatos-container"></div>
                    </div>
                    <button type="button" id="add-formato-btn" style="margin-top: 10px;">+ Adicionar Formato</button>
                </div>

                <button type="submit" class="btn-submit">Adicionar Álbum</button> 
            </form>
        <?php endif; ?>
    </div>

    <!-- Modais para Adicionar Itens -->
    <div id="modal-add-gravadora" class="modal-overlay add-modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-body">
                <h1>Adicionar Nova Gravadora</h1>
                <form class="sub-modal-form" action="add_album.php" method="post">
                    <input type="hidden" name="action" value="add_gravadora">
                    <div class="form-group">
                        <label for="nome_gravadora_modal">Nome da Gravadora</label>
                        <input type="text" id="nome_gravadora_modal" name="nome_gravadora" required>
                    </div>
                    <div class="form-group">
                        <label for="email_gravadora_modal">E-mail da Gravadora</label>
                        <input type="email" id="email_gravadora_modal" name="email_gravadora">
                    </div>
                    <button type="submit" class="btn-submit">Adicionar</button>
                    <div class="message" style="display: none;"></div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-add-artista" class="modal-overlay add-modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-body">
                <h1>Adicionar Novo Artista</h1>
                <form class="sub-modal-form" action="add_album.php" method="post">
                    <input type="hidden" name="action" value="add_artista">
                    <div class="form-group">
                        <label for="nome_artista_modal">Nome do Artista</label>
                        <input type="text" id="nome_artista_modal" name="nome_artista" required>
                    </div>
                    <div class="form-group">
                        <label for="data_nascimento_modal">Data de Nascimento (opcional)</label>
                        <input type="date" id="data_nascimento_modal" name="data_nascimento">
                    </div>
                    <button type="submit" class="btn-submit">Adicionar</button>
                    <div class="message" style="display: none;"></div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-add-genero" class="modal-overlay add-modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-body">
                <h1>Adicionar Novo Gênero</h1>
                <form class="sub-modal-form" action="add_album.php" method="post">
                    <input type="hidden" name="action" value="add_genero">
                    <div class="form-group">
                        <label for="nome_genero_musical_modal">Nome do Gênero</label>
                        <input type="text" id="nome_genero_musical_modal" name="nome_genero_musical" required>
                    </div>
                    <button type="submit" class="btn-submit">Adicionar</button>
                    <div class="message" style="display: none;"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Lógica para adicionar formatos dinamicamente ---
        const addFormatoBtn = document.getElementById('add-formato-btn');
        if (addFormatoBtn) { // Verifica se o botão existe na página
            const container = document.getElementById('formatos-container');
            const formatosSection = document.querySelector('.formatos-section');
            let formatoIndex = 0;

            addFormatoBtn.addEventListener('click', function() {
                if (formatosSection.style.display === 'none') {
                    formatosSection.style.display = 'block'; // Mostra a seção de formatos
                }
                const div = document.createElement('div');
                div.classList.add('formato-item');
                div.innerHTML = `
                    <div class="custom-select-container">
                        <input type="hidden" name="formatos[${formatoIndex}][tipo]" value="" required>
                        <div class="select-selected">Selecione o Tipo</div>
                        <div class="select-items">
                            <div data-value="cd">CD</div>
                            <div data-value="vinil_7">Vinil 7"</div>
                            <div data-value="vinil_10">Vinil 10"</div>
                            <div data-value="vinil_12">Vinil 12"</div>
                        </div>
                    </div>
                    <input type="number" name="formatos[${formatoIndex}][preco]" placeholder="Preço (ex: 99.90)" step="0.01" required>
                    <input type="number" name="formatos[${formatoIndex}][quantidade]" placeholder="Quantidade" min="0" required>
                    <button type="button" class="remove-formato-btn">X</button>
                `;
                container.appendChild(div);
                // Inicializa o novo custom select que acabamos de adicionar
                initializeCustomSelect(div.querySelector('.custom-select-container'));
                formatoIndex++;
            });

            container.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-formato-btn')) {
                    e.target.closest('.formato-item').remove();
                    // Se não houver mais formatos, esconde a seção novamente
                    if (container.children.length === 0 && formatosSection) {
                        formatosSection.style.display = 'none';
                    }
                }
            });
        }

        // --- Lógica para pré-visualização da imagem ---
        const imageInput = document.getElementById('imagem_capa');
        const imagePreview = document.getElementById('image-preview');

        if (imageInput && imagePreview) {
            imageInput.addEventListener('change', function(event) {
                if (event.target.files && event.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(event.target.files[0]);
                }
            });
        }

        // --- Lógica para o Novo Custom Select ---
        function initializeCustomSelect(container) {
            const selected = container.querySelector('.select-selected');
            const items = container.querySelector('.select-items');
            const hiddenInput = container.querySelector('input[type="hidden"]');

            selected.addEventListener('click', function(e) {
                e.stopPropagation();
                closeAllSelects(this); // Fecha outros selects abertos
                items.style.maxHeight = items.style.maxHeight ? null : items.scrollHeight + "px";
                items.style.opacity = items.style.opacity === '1' ? '0' : '1';
                this.classList.toggle('select-arrow-active');
            });

            items.querySelectorAll('div').forEach(item => {
                item.addEventListener('click', function() {
                    selected.textContent = this.textContent;
                    hiddenInput.value = this.getAttribute('data-value');
                    closeAllSelects();
                });
            });
        }

        function closeAllSelects(elmnt) {
            document.querySelectorAll('.custom-select-container').forEach(container => {
                const selected = container.querySelector('.select-selected');
                const items = container.querySelector('.select-items');
                if (selected !== elmnt) {
                    items.style.maxHeight = null;
                    items.style.opacity = '0';
                    selected.classList.remove('select-arrow-active');
                }
            });
        }

        document.querySelectorAll('.custom-select-container').forEach(initializeCustomSelect);

        // Fecha os selects se clicar fora
        document.addEventListener('click', closeAllSelects);


        // --- Lógica para os Modais de Adição (Gravadora, Artista, Gênero) ---
        const openModalButtons = document.querySelectorAll('.open-sub-modal');
        const subModals = document.querySelectorAll('.add-modal');

        function openSubModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                document.body.classList.add('modal-open');
                modal.style.display = 'flex';
                setTimeout(() => {
                    modal.style.opacity = '1';
                    modal.querySelector('.modal-content').style.transform = 'scale(1)';
                }, 10);
            }
        }

        function closeSubModal(modal) {
            if (modal) {
                modal.style.opacity = '0';
                modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
                setTimeout(() => {
                    modal.style.display = 'none';
                    // Limpa a mensagem de feedback ao fechar
                    const messageDiv = modal.querySelector('.message');
                    if (messageDiv) {
                        messageDiv.style.display = 'none';
                        messageDiv.textContent = '';
                        messageDiv.className = 'message';
                    }
                    // Só remove a classe do body se nenhum outro modal estiver aberto
                    if (document.querySelectorAll('.modal-overlay[style*="display: flex"]').length === 0) {
                        document.body.classList.remove('modal-open');
                    }
                }, 300);
            }
        }

        openModalButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modalId = this.getAttribute('data-modal-id');
                openSubModal(modalId);
            });
        });

        subModals.forEach(modal => {
            // Fechar ao clicar no 'X'
            modal.querySelector('.modal-close').addEventListener('click', () => closeSubModal(modal));
            // Fechar ao clicar no overlay
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeSubModal(modal);
                }
            });

            // Lidar com a submissão do formulário via AJAX
            const form = modal.querySelector('.sub-modal-form');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const messageDiv = form.querySelector('.message');

                fetch(form.getAttribute('action'), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    messageDiv.textContent = data.message;
                    messageDiv.className = `message ${data.status}`;
                    messageDiv.style.display = 'block';

                    // Se a adição foi um sucesso, atualiza a UI dinamicamente
                    if (data.status === 'success') {
                        const newItem = data.newItem;
                        const action = formData.get('action');

                        if (action === 'add_gravadora') {
                            const selectContainer = document.querySelector('input[name="gravadora_id"]').closest('.custom-select-container');
                            const selectItems = selectContainer.querySelector('.select-items');
                            const newOption = document.createElement('div');
                            newOption.dataset.value = newItem.id;
                            newOption.textContent = newItem.name;
                            selectItems.appendChild(newOption);
                            // Seleciona o novo item
                            selectContainer.querySelector('.select-selected').textContent = newItem.name;
                            selectContainer.querySelector('input[type="hidden"]').value = newItem.id;
                            // Adiciona o evento de clique ao novo item
                            newOption.addEventListener('click', function() {
                                selectContainer.querySelector('.select-selected').textContent = this.textContent;
                                selectContainer.querySelector('input[type="hidden"]').value = this.getAttribute('data-value');
                                closeAllSelects();
                            });
                        } else if (action === 'add_artista') {
                            const selectContainer = document.querySelector('input[name="artista_id"]').closest('.custom-select-container');
                            const selectItems = selectContainer.querySelector('.select-items');
                            const newOption = document.createElement('div');
                            newOption.dataset.value = newItem.id;
                            newOption.textContent = newItem.name;
                            selectItems.appendChild(newOption);
                            selectContainer.querySelector('.select-selected').textContent = newItem.name;
                            selectContainer.querySelector('input[type="hidden"]').value = newItem.id;
                            newOption.addEventListener('click', function() {
                                selectContainer.querySelector('.select-selected').textContent = this.textContent;
                                selectContainer.querySelector('input[type="hidden"]').value = this.getAttribute('data-value');
                                closeAllSelects();
                            });
                        } else if (action === 'add_genero') {
                            const checkboxContainer = document.querySelector('.checkbox-group-container');
                            const addButton = checkboxContainer.querySelector('.btn-add-related');
                            const newCheckbox = document.createElement('label');
                            newCheckbox.className = 'checkbox-item';
                            newCheckbox.innerHTML = `
                                <input type="checkbox" name="generos_ids[]" value="${newItem.id}" checked>
                                <span class="custom-checkbox"></span>
                                <span class="genre-name">${newItem.name}</span>
                            `;
                            // Insere o novo gênero antes do botão de adicionar
                            checkboxContainer.insertBefore(newCheckbox, addButton);
                        }

                        // Fecha o modal após um curto período
                        setTimeout(() => closeSubModal(modal), 1000);
                    }
                })
                .catch(error => console.error('Erro:', error));
            });
        });


    });
    </script>
</body>
</html>