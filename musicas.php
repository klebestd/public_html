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

error_log("Iniciando musicas.php");

$msg = null;
$musicas = [];

try {
    $stmt = $pdo->query("SELECT * FROM arquivos WHERE tipo = 'musica' ORDER BY data_upload DESC");
    $musicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Músicas carregadas: " . count($musicas) . " registros");
} catch (PDOException $e) {
    error_log("Erro ao carregar músicas: " . $e->getMessage());
    $msg = ["type" => "danger", "text" => "Erro ao carregar músicas: " . $e->getMessage()];
}

// Processar exclusão de música
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    try {
        $musicaId = (int)($_POST['musica_id'] ?? 0);
        if ($musicaId <= 0) {
            throw new Exception("ID da música inválido.");
        }

        $pdo->beginTransaction();

        // Verificar se a música existe e obter o caminho do arquivo
        $stmt = $pdo->prepare("SELECT caminho FROM arquivos WHERE id = ? AND tipo = 'musica'");
        $stmt->execute([$musicaId]);
        $musica = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$musica) {
            throw new Exception("Música não encontrada.");
        }

        // Verificar se a música está associada a alguma playlist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM playlist_arquivos WHERE arquivo_id = ?");
        $stmt->execute([$musicaId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Música está associada a uma playlist. Remova-a das playlists antes de excluir.");
        }

        // Deletar registro do banco
        $stmt = $pdo->prepare("DELETE FROM arquivos WHERE id = ?");
        $stmt->execute([$musicaId]);
        error_log("Música deletada: ID=$musicaId");

        // Remover arquivo físico
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $musica['caminho'])) {
            if (!unlink($_SERVER['DOCUMENT_ROOT'] . $musica['caminho'])) {
                throw new Exception("Erro ao remover o arquivo da música.");
            }
            error_log("Arquivo removido: " . $musica['caminho']);
        }

        // Invalidar cache
        if (CACHE_ENABLED && function_exists('apcu_delete')) {
            apcu_delete('musicas_list');
            error_log("Cache APCu (musicas_list) invalidado após exclusão.");
        }

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Música excluída com sucesso!"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao excluir música: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Erro: " . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Músicas Enviadas - AutoDJ</title>
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
        <a class="nav-link" href="upload.php"><i class="fas fa-upload me-1"></i> Upload</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="playlists.php"><i class="fas fa-list me-1"></i> Playlists</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="comerciais.php"><i class="fas fa-bullhorn me-1"></i> Comerciais</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="musicas.php"><i class="fas fa-music me-1"></i> Músicas</a>
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
        <h1 class="my-4 text-center text-white"><i class="fas fa-music me-2"></i> Músicas Enviadas</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-music me-2"></i> Lista de Músicas
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                        <?= $msg['text'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (empty($musicas)): ?>
                    <p class="text-muted">Nenhuma música encontrada.</p>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Data de Upload</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($musicas as $musica): ?>
                                <tr id="musica-<?= $musica['id'] ?>">
                                    <td><?= htmlspecialchars($musica['nome']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($musica['data_upload'])) ?></td>
                                    <td>
                                        <button class="btn btn-danger action-btn delete-musica" data-id="<?= $musica['id'] ?>" data-nome="<?= htmlspecialchars($musica['nome']) ?>"><i class="fas fa-trash"></i> Excluir</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Tem certeza que deseja excluir a música "<span id="musica-nome"></span>"? Esta ação não pode ser desfeita.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete">Excluir</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manipular exclusão de música
        const deleteButtons = document.querySelectorAll('.delete-musica');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        let currentMusicaId = null;

        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                currentMusicaId = this.dataset.id;
                const musicaNome = this.dataset.nome;
                document.getElementById('musica-nome').textContent = musicaNome;
                deleteModal.show();
            });
        });

        document.getElementById('confirm-delete').addEventListener('click', async function() {
            try {
                const response = await fetch('musicas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete&musica_id=${currentMusicaId}`
                });
                const data = await response.json();
                if (data.status === 'success') {
                    document.getElementById(`musica-${currentMusicaId}`).remove();
                    alert(data.message);
                    deleteModal.hide();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Erro ao excluir música: ' + error.message);
            }
        });
    </script>
</body>
</html>