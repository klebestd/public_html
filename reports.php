<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
// Conexão com o banco de dados
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Função para ler o status do streaming
function getStreamStatus(): string {
    if (defined('STREAM_STATUS_FILE') && file_exists(STREAM_STATUS_FILE) && is_readable(STREAM_STATUS_FILE)) {
        return file_get_contents(STREAM_STATUS_FILE) ?: "Status desconhecido";
    }
    return "Arquivo de status não encontrado ou não legível";
}

// Função para ler a faixa atual
function getCurrentTrack(): string {
    if (defined('CURRENT_TRACK_FILE') && file_exists(CURRENT_TRACK_FILE) && is_readable(CURRENT_TRACK_FILE)) {
        return file_get_contents(CURRENT_TRACK_FILE) ?: "Nenhuma faixa em reprodução";
    }
    return "Arquivo de faixa atual não encontrado ou não legível";
}

// Obter todas as playlists
try {
    $stmt = $pdo->query("SELECT * FROM playlists ORDER BY nome");
    $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao consultar playlists: " . $e->getMessage());
}

// Filtrar por playlist (se selecionada)
$selected_playlist_id = filter_input(INPUT_GET, 'playlist_id', FILTER_VALIDATE_INT) ?: 0;
$playlist_files = [];
if ($selected_playlist_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT pa.*, a.nome, a.tipo, a.caminho, a.data_upload
            FROM playlist_arquivos pa
            JOIN arquivos a ON pa.arquivo_id = a.id
            WHERE pa.playlist_id = ?
            ORDER BY pa.ordem
        ");
        $stmt->execute([$selected_playlist_id]);
        $playlist_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erro ao consultar arquivos da playlist: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - AutoDJ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">AutoDJ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><i class="fas fa-home me-1"></i> Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php"><i class="fas fa-upload me-1"></i> Upload</a>
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
                        <a class="nav-link" href="reports.php"><i class="fas fa-list-alt me-1"></i> Reports</a>
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
        <h1 class="mb-4">Relatórios do AutoDJ</h1>

        <!-- Status do Streaming -->
        <div class="card mb-4">
            <div class="card-header">Status Atual</div>
            <div class="card-body">
                <p><strong>Streaming:</strong> <span class="status-text"><?= htmlspecialchars(getStreamStatus(), ENT_QUOTES, 'UTF-8') ?></span></p>
                <p><strong>Faixa Atual:</strong> <span class="status-text"><?= htmlspecialchars(getCurrentTrack(), ENT_QUOTES, 'UTF-8') ?></span></p>
            </div>
        </div>

        <!-- Filtro de Playlist -->
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <label for="playlist_id" class="form-label">Selecionar Playlist</label>
                    <select name="playlist_id" id="playlist_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Todas as Playlists</option>
                        <?php foreach ($playlists as $playlist): ?>
                            <option value="<?= (int)$playlist['id'] ?>" <?= $selected_playlist_id === $playlist['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($playlist['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <!-- Lista de Playlists -->
        <h2>Playlists</h2>
        <?php if (empty($playlists)): ?>
            <p class="error">Nenhuma playlist encontrada.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Horário</th>
                            <th>Dias da Semana</th>
                            <th>Data de Criação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($playlists as $playlist): ?>
                            <tr>
                                <td><?= htmlspecialchars($playlist['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= htmlspecialchars($playlist['horario_inicio'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?> - 
                                    <?= htmlspecialchars($playlist['horario_fim'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars($playlist['dias_semana'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= $playlist['data_criacao'] 
                                        ? date('d/m/Y H:i', strtotime($playlist['data_criacao'])) 
                                        : 'N/D' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Arquivos da Playlist Selecionada -->
        <?php if ($selected_playlist_id && !empty($playlist_files)): ?>
            <h2>Arquivos da Playlist: 
                <?= htmlspecialchars(
                    $playlists[array_search($selected_playlist_id, array_column($playlists, 'id'))]['nome'] ?? 'Desconhecida',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h2>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Caminho</th>
                            <th>Data de Upload</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($playlist_files as $file): ?>
                            <tr>
                                <td><?= (int)$file['ordem'] ?></td>
                                <td><?= htmlspecialchars($file['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $file['tipo'] === 'musica' ? 'Música' : 'Comercial' ?></td>
                                <td><?= htmlspecialchars($file['caminho'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= $file['data_upload'] 
                                        ? date('d/m/Y H:i', strtotime($file['data_upload'])) 
                                        : 'N/D' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($selected_playlist_id): ?>
            <p class="error">Nenhum arquivo encontrado para esta playlist.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>