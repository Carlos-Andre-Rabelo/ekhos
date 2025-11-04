<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

$mongoUri = "mongodb://127.0.0.1:27017";
$dbName = "CDs_&_vinil";
$message = null;
$action = $_GET['action'] ?? 'add_album'; // Define a ação a ser executada

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase($dbName);

    // Buscar dados para preencher os selects do formulário
    // Projeta os campos necessários e converte o _id para string para uso no HTML
    if ($action === 'add_album') {
        $gravadoras = $database->selectCollection('gravadoras')->find(
            [],
            ['sort' => ['nome_gravadora' => 1], 'projection' => ['_id' => 1, 'nome_gravadora' => 1]]
        )->toArray();
        $artistas = $database->selectCollection('artistas')->find([], ['sort' => ['nome_artista' => 1]])->toArray();
        $generos = $database->selectCollection('generos_musicais')->find([], ['sort' => ['nome_genero_musical' => 1]])->toArray();
    }

    // Roteamento de ações com base no método POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $message = ['type' => 'success', 'text' => 'Álbum "' . htmlspecialchars($newAlbum['titulo_album']) . '" adicionado com sucesso!'];
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
                $message = ['type' => 'success', 'text' => 'Gravadora "' . htmlspecialchars($nomeGravadora) . '" adicionada com sucesso! Você pode fechar esta aba e atualizar a página anterior.'];
            } else {
                throw new Exception("Não foi possível adicionar a gravadora.");
            }

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
                $message = ['type' => 'success', 'text' => 'Artista "' . htmlspecialchars($nomeArtista) . '" adicionado com sucesso! Você pode fechar esta aba e atualizar a página anterior.'];
            } else {
                throw new Exception("Não foi possível adicionar o artista.");
            }
        }
        elseif ($postedAction === 'add_genero') {
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
                $message = ['type' => 'success', 'text' => 'Gênero "' . htmlspecialchars($nomeGenero) . '" adicionado com sucesso! Você pode fechar esta aba e atualizar a página anterior.'];
            } else {
                throw new Exception("Não foi possível adicionar o gênero.");
            }
        }
    }
} catch (Exception $e) {
    $message = ['type' => 'error', 'text' => 'Erro: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        <?php if ($action === 'add_album'): ?>
            <nav style="text-align: center; margin-bottom: 2rem;">
                <a href="index.php">&larr; Voltar para a Coleção</a>
            </nav> 
            <h1>Adicionar Novo Álbum</h1>
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
                        <select id="gravadora_id" name="gravadora_id" required>
                            <option value="">Gravadora</option>
                            <?php foreach ($gravadoras as $gravadora): ?>
                                <option value="<?= $gravadora['_id'] ?>"><?= htmlspecialchars((string)($gravadora['nome_gravadora'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a href="add_album.php?action=add_gravadora" target="_blank" class="btn-add-related" title="Adicionar Nova Gravadora">+</a>
                    </div>
                </div>

                <!--selecionar o artista-->                            
                <div class="form-group">
                    <label for="artista_id">Artista</label>
                    <div class="form-group-inline">
                        <select id="artista_id" name="artista_id" required>
                            <option value="">Artista</option>
                            <?php foreach ($artistas as $artista): ?>
                                <option value="<?= $artista['_id'] ?>"><?= htmlspecialchars($artista['nome_artista']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a href="add_album.php?action=add_artista" target="_blank" class="btn-add-related" title="Adicionar Novo Artista">+</a>
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
                        <a href="add_album.php?action=add_genero" target="_blank" class="btn-add-related" title="Adicionar Novo Gênero">+</a>
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
                </div>

                <div class="form-group">
                    <div class="formatos-section">
                        <h3>Formatos (Exemplares)</h3>
                        <div id="formatos-container"></div>
                    </div>
                    <button type="button" id="add-formato-btn" style="margin-top: 10px;">+ Adicionar Formato</button>
                </div>

                <button type="submit" class="btn-submit">Adicionar Álbum</button> 
            </form>

        <?php elseif ($action === 'add_gravadora'): ?>
            <h1>Adicionar Nova Gravadora</h1>
            <form action="add_album.php?action=add_gravadora" method="post">
                <input type="hidden" name="action" value="add_gravadora">
                <div class="form-group">
                    <label for="nome_gravadora">Nome da Gravadora</label>
                    <input type="text" id="nome_gravadora" name="nome_gravadora" required>
                </div>
                <div class="form-group">
                    <label for="email_gravadora">E-mail da Gravadora</label>
                    <input type="email" id="email_gravadora" name="email_gravadora">
                </div>
                <button type="submit" class="btn-submit">Adicionar Gravadora</button>
            </form>

        <?php elseif ($action === 'add_artista'): ?>
            <h1>Adicionar Novo Artista</h1>
            <form action="add_album.php?action=add_artista" method="post">
                <input type="hidden" name="action" value="add_artista">
                <div class="form-group">
                    <label for="nome_artista">Nome do Artista</label>
                    <input type="text" id="nome_artista" name="nome_artista" required>
                </div>
                <div class="form-group">
                    <label for="data_nascimento">Data de Nascimento (opcional)</label>
                    <input type="date" id="data_nascimento" name="data_nascimento">
                </div>
                <button type="submit" class="btn-submit">Adicionar Artista</button>
            </form>

        <?php elseif ($action === 'add_genero'): ?>
            <h1>Adicionar Novo Gênero</h1>
            <form action="add_album.php?action=add_genero" method="post">
                <input type="hidden" name="action" value="add_genero">
                <div class="form-group">
                    <label for="nome_genero_musical">Nome do Gênero</label>
                    <input type="text" id="nome_genero_musical" name="nome_genero_musical" required>
                </div>
                <button type="submit" class="btn-submit">Adicionar Gênero</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addFormatoBtn = document.getElementById('add-formato-btn');
        if (addFormatoBtn) {
            const container = document.getElementById('formatos-container');
            let formatoIndex = 0;

            addFormatoBtn.addEventListener('click', function() { // Adiciona um novo item de formato
                const div = document.createElement('div');
                div.classList.add('formato-item');
                div.innerHTML = `
                    <select name="formatos[${formatoIndex}][tipo]" required>
                        <option value="">-- Tipo --</option>
                        <option value="cd">CD</option>
                        <option value="vinil_7">Vinil 7"</option>
                        <option value="vinil_10">Vinil 10"</option>
                        <option value="vinil_12">Vinil 12"</option>
                    </select>
                    <input type="number" name="formatos[${formatoIndex}][preco]" placeholder="Preço (ex: 99.90)" step="0.01" required>
                    <input type="number" name="formatos[${formatoIndex}][quantidade]" placeholder="Quantidade" min="0" required>
                    <button type="button" class="remove-formato-btn">X</button>
                `;
                container.appendChild(div);
                formatoIndex++;
            });

            container.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-formato-btn')) {
                    e.target.closest('.formato-item').remove();
                }
            });
        }
    });
    </script>
</body>
</html>