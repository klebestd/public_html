<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$messages = []; // Array para armazenar mensagens de sucesso/erro
define('UPLOAD_DIR', __DIR__ . '/uploads/'); // Pasta física (minúscula)
define('BASE_PATH', '/uploads/'); // Caminho no banco (minúscula)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivos'])) {
    $tipo = $_POST['tipo'] ?? '';
    if (!in_array($tipo, ['musica', 'comercial'])) {
        $messages[] = ["type" => "danger", "text" => "Tipo de arquivo inválido."];
    } else {
        $tipo_plural = $tipo === 'musica' ? 'musicas' : 'comerciais';
        $upload_dir = UPLOAD_DIR . $tipo_plural . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Processar múltiplos arquivos
        $files = $_FILES['arquivos'];
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $nome_arquivo = basename($files['name'][$i]);
                $ext = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
                
                if ($ext !== 'mp3') {
                    $messages[] = ["type" => "danger", "text" => "Arquivo '$nome_arquivo': Apenas arquivos MP3 são permitidos."];
                    continue;
                }

                $caminho_arquivo = $upload_dir . $nome_arquivo;
                if (move_uploaded_file($files['tmp_name'][$i], $caminho_arquivo)) {
                    // Caminho para o banco (minúscula)
                    $caminho_banco = "/uploads/$tipo_plural/$nome_arquivo";
                    try {
                        $stmt = $pdo->prepare("INSERT INTO arquivos (nome, tipo, caminho, data_upload) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$nome_arquivo, $tipo, $caminho_banco]);
                        $messages[] = ["type" => "success", "text" => "Arquivo '$nome_arquivo' enviado com sucesso!"];
                    } catch (PDOException $e) {
                        $messages[] = ["type" => "danger", "text" => "Erro ao salvar '$nome_arquivo' no banco: " . $e->getMessage()];
                    }
                } else {
                    $messages[] = ["type" => "danger", "text" => "Erro ao fazer upload do arquivo '$nome_arquivo'."];
                }
            } else {
                $messages[] = ["type" => "danger", "text" => "Erro no upload do arquivo '" . ($files['name'][$i] ?: "desconhecido") . "': " . $files['error'][$i]];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload - AutoDJ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-music me-2"></i> AutoDJ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="upload.php"><i class="fas fa-upload me-1"></i> Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="playlists.php"><i class="fas fa-list me-1"></i> Playlists</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="comerciais.php"><i class="fas fa-bullhorn me-1"></i> Comerciais</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="musicas.php"><i class="fas fa-music me-1"></i> Músicas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="lista_playlists.php"><i class="fas fa-list-alt me-1"></i> Listar Playlists</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="edit_user.php"><i class="fas fa-user-edit me-1"></i> Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="my-4 text-center text-white"><i class="fas fa-upload me-2"></i> Upload de Arquivos</h1>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-upload me-2"></i> Enviar Músicas ou Comerciais
            </div>
            <div class="card-body">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                            <?= $msg['text'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Tipo de Arquivo</label>
                        <select class="form-select" name="tipo" required>
                            <option value="">Selecione...</option>
                            <option value="musica">Música</option>
                            <option value="comercial">Comercial</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="arquivos" class="form-label">Arquivos (MP3) - Segure Ctrl para selecionar múltiplos arquivos</label>
                        <input type="file" class="form-control" id="arquivos" name="arquivos[]" accept=".mp3" multiple required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i> Enviar</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>