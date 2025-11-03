<?php
declare(strict_types=1);

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
    $gravadoras = $database->selectCollection('gravadoras')->find([], ['sort' => ['nome_gravadora' => 1]])->toArray();
    $artistas = $database->selectCollection('artistas')->find([], ['sort' => ['nome_artista' => 1]])->toArray();
    $generos = $database->selectCollection('generos_musicais')->find([], ['sort' => ['nome_genero_musical' => 1]])->toArray();

    // Processar o formulário quando for enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- Tratamento do Upload da Imagem ---
        $imagePath = null;
        if (isset($_FILES['imagem_capa']) && $_FILES['imagem_capa']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/imagens/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid() . '-' . basename($_FILES['imagem_capa']['name']);
            $targetFile = $uploadDir . $fileName;
            
            // Validação simples de tipo de arquivo
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $targetFile)) {
                    $imagePath = 'imagens/' . $fileName; // Caminho relativo para salvar no DB
                } else {
                    throw new Exception("Falha ao mover o arquivo de imagem.");
                }
            } else {
                throw new Exception("Formato de imagem inválido. Use JPG, JPEG, PNG, GIF ou WEBP.");
            }
        }

        // --- Montagem do Documento do Álbum ---
        $newAlbum = [
            '_id' => new MongoDB\BSON\ObjectId(), // Gera um ID único para o subdocumento
            'titulo_album' => (string) ($_POST['titulo_album'] ?? ''),
            'data_lancamento' => new MongoDB\BSON\UTCDateTime(new DateTime($_POST['data_lancamento'])),
            'imagens_capas' => $imagePath ? [$imagePath] : [],
            'numero_faixas' => (int) ($_POST['numero_faixas'] ?? 0),
            'duracao' => (string) ($_POST['duracao'] ?? '00:00:00'),
            'artistas_ids' => [ (int) ($_POST['artista_id'] ?? 0) ],
            'generos_ids' => array_map('intval', $_POST['generos_ids'] ?? []),
            'exemplares' => [] // Começa sem exemplares, podem ser adicionados depois
        ];

        // --- Inserção no Banco de Dados ---
        $gravadoraId = (int) $_POST['gravadora_id'];
        $updateResult = $database->selectCollection('gravadoras')->updateOne(
            ['_id' => $gravadoraId],
            ['$push' => ['albuns' => $newAlbum]]
        );

        if ($updateResult->getModifiedCount() > 0) {
            $message = ['type' => 'success', 'text' => 'Álbum "' . htmlspecialchars($newAlbum['titulo_album']) . '" adicionado com sucesso!'];
        } else {
            throw new Exception("Não foi possível adicionar o álbum à gravadora selecionada. Verifique se a gravadora existe.");
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
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container { max-width: 800px; margin: 2rem auto; padding: 2rem; background-color: var(--cor-superficie); border-radius: 8px; }
        .form-container h1 { text-align: center; color: var(--cor-primaria); margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 0.8rem; font-size: 1rem; border-radius: 4px; border: 1px solid var(--cor-borda); background-color: var(--cor-fundo); color: var(--cor-texto-principal); }
        .form-group select[multiple] { height: 150px; }
        .form-group input[type="file"] { padding: 0.5rem; }
        .btn-submit { display: block; width: 100%; padding: 1rem; font-size: 1.1rem; font-weight: 600; color: #fff; background-color: var(--cor-primaria); border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #2980b9; }
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; text-align: center; }
        .message.success { background-color: #27ae60; color: #fff; }
        .message.error { background-color: #c0392b; color: #fff; }
        nav a { color: var(--cor-primaria); text-decoration: none; }
    </style>
</head>
<body>
    <div class="form-container">
        <nav style="text-align: center; margin-bottom: 2rem;">
            <a href="index.php">&larr; Voltar para a Coleção</a>
        </nav>
        <h1>Adicionar Novo Álbum</h1>

        <?php if ($message): ?>
            <div class="message <?= $message['type'] ?>">
                <?= $message['text'] ?>
            </div>
        <?php endif; ?>

        <form action="add_album.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="titulo_album">Título do Álbum</label>
                <input type="text" id="titulo_album" name="titulo_album" required>
            </div>

            <div class="form-group">
                <label for="gravadora_id">Gravadora</label>
                <select id="gravadora_id" name="gravadora_id" required>
                    <option value="">-- Selecione uma gravadora --</option>
                    <?php foreach ($gravadoras as $gravadora): ?>
                        <option value="<?= $gravadora['_id'] ?>"><?= htmlspecialchars($gravadora['nome_gravadora']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="artista_id">Artista</label>
                <select id="artista_id" name="artista_id" required>
                    <option value="">-- Selecione um artista --</option>
                    <?php foreach ($artistas as $artista): ?>
                        <option value="<?= $artista['_id'] ?>"><?= htmlspecialchars($artista['nome_artista']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="generos_ids">Gêneros (segure Ctrl/Cmd para selecionar vários)</label>
                <select id="generos_ids" name="generos_ids[]" multiple required>
                    <?php foreach ($generos as $genero): ?>
                        <option value="<?= $genero['_id'] ?>"><?= htmlspecialchars($genero['nome_genero_musical']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="data_lancamento">Data de Lançamento</label>
                <input type="date" id="data_lancamento" name="data_lancamento" required>
            </div>

            <div class="form-group">
                <label for="numero_faixas">Número de Faixas</label>
                <input type="number" id="numero_faixas" name="numero_faixas" min="1" required>
            </div>

            <div class="form-group">
                <label for="duracao">Duração (HH:MM:SS)</label>
                <input type="text" id="duracao" name="duracao" pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" placeholder="ex: 00:45:33" required>
            </div>

            <div class="form-group">
                <label for="imagem_capa">Capa do Álbum</label>
                <input type="file" id="imagem_capa" name="imagem_capa" accept="image/*">
            </div>

            <button type="submit" class="btn-submit">Adicionar Álbum</button>
        </form>
    </div>
</body>
</html>