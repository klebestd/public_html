<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

error_log("Iniciando playlist_arquivos.php");

$msg = null;
$playlist = null;
$arquivos = [];
$playlistId = (int)($_GET['id'] ?? 0);

if ($playlistId <= 0) {
    $msg = ["type" => "danger", "text" => "ID da playlist inválido."];
} else {
    try {
        // Buscar informações da playlist
        $stmt = $pdo->prepare("SELECT nome, horario_inicio, horario_fim, dias_semana FROM playlists WHERE id = ?");
        $stmt->execute([$playlistId]);
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$playlist) {
            $msg = ["type" => "danger", "text" => "Playlist não encontrada."];
        } else {
            // Buscar arquivos da playlist
            $stmt = $pdo->prepare("
                SELECT a.id, a.nome, a.tipo, a.caminho
                FROM playlist_arquivos pa
                JOIN arquivos a ON pa.arquivo_id = a.id
                WHERE pa.playlist_id = ?
                ORDER BY pa.ordem ASC
            ");
            $stmt->execute([$playlistId]);
            $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Arquivos carregados para playlist ID=$playlistId: " . count($arquivos) . " registros");
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar playlist ou arquivos: " . $e->getMessage());
        $msg = ["type" => "danger", "text" => "Erro ao carregar dados: " . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arquivos da Playlist - AutoDJ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        body {
            overflow: auto !important;
        }
    </style>
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
                        <a class="nav-link active" href="lista_playlists.php"><i class="fas fa-list-alt me-1"></i> Listar Playlists</a>
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
        <h1 class="my-4 text-center text-white"><i class="fas fa-list me-2"></i> Arquivos da Playlist</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i> Playlist: <?= $playlist ? htmlspecialchars($playlist['nome']) : 'Desconhecida' ?>
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                        <?= $msg['text'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($playlist): ?>
                    <p><strong>Horário:</strong> <?= $playlist['horario_inicio'] ?> - <?= $playlist['horario_fim'] ?></p>
                    <p><strong>Dias:</strong>
                        <?php
                        $dias = explode(',', $playlist['dias_semana']);
                        $diasMap = [
                            'seg' => 'Seg', 'ter' => 'Ter', 'qua' => 'Qua',
                            'qui' => 'Qui', 'sex' => 'Sex', 'sab' => 'Sáb', 'dom' => 'Dom'
                        ];
                        $diasNomes = array_map(fn($dia) => $diasMap[$dia] ?? $dia, $dias);
                        echo implode(', ', $diasNomes);
                        ?>
                    </p>
                    <?php if (empty($arquivos)): ?>
                        <p class="text-muted">Nenhum arquivo associado a esta playlist.</p>
                    <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome do Arquivo</th>
                                    <th>Tipo</th>
                                    <th>Caminho</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($arquivos as $arquivo): ?>
                                    <tr>
                                        <td>
                                            <i class="fas <?= $arquivo['tipo'] === 'musica' ? 'fa-music' : 'fa-bullhorn' ?> me-1"></i>
                                            <?= htmlspecialchars($arquivo['nome']) ?>
                                        </td>
                                        <td><?= $arquivo['tipo'] === 'musica' ? 'Música' : 'Comercial' ?></td>
                                        <td><?= htmlspecialchars($arquivo['caminho']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="lista_playlists.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
</body>
</html>