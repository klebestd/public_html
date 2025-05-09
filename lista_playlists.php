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

error_log("Iniciando lista_playlists.php");

$msg = null;
$playlists = [];

try {
    $stmt = $pdo->query("SELECT * FROM playlists ORDER BY data_criacao DESC");
    $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Playlists carregadas: " . count($playlists) . " registros");
} catch (PDOException $e) {
    error_log("Erro ao carregar playlists: " . $e->getMessage());
    $msg = ["type" => "danger", "text" => "Erro ao carregar playlists: " . $e->getMessage()];
}

// Processar exclusão de playlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    try {
        $playlistId = (int)($_POST['playlist_id'] ?? 0);
        if ($playlistId <= 0) {
            throw new Exception("ID da playlist inválido.");
        }

        $pdo->beginTransaction();

        // Verificar se a playlist existe e obter o caminho do arquivo
        $stmt = $pdo->prepare("SELECT caminho FROM playlists WHERE id = ?");
        $stmt->execute([$playlistId]);
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$playlist) {
            throw new Exception("Playlist não encontrada.");
        }

        // Deletar associações na tabela playlist_arquivos
        $stmt = $pdo->prepare("DELETE FROM playlist_arquivos WHERE playlist_id = ?");
        $stmt->execute([$playlistId]);

        // Deletar a playlist do banco
        $stmt = $pdo->prepare("DELETE FROM playlists WHERE id = ?");
        $stmt->execute([$playlistId]);
        error_log("Playlist deletada: ID=$playlistId");

        // Remover arquivo .m3u, se existir
        $m3uFile = $playlist['caminho'];
        if (!empty($m3uFile) && file_exists($m3uFile) && is_writable($m3uFile)) {
            if (!unlink($m3uFile)) {
                error_log("Erro ao remover arquivo da playlist: " . $m3uFile);
            } else {
                error_log("Arquivo removido: " . $m3uFile);
            }
        } else {
            error_log("Arquivo .m3u não encontrado ou não gravável: " . ($m3uFile ?: 'caminho vazio'));
        }

        // Invalidar cache
        if (CACHE_ENABLED && function_exists('apcu_delete')) {
            apcu_delete('active_playlist');
            error_log("Cache APCu (active_playlist) invalidado após exclusão.");
        }

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Playlist excluída com sucesso!"]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao excluir playlist: " . $e->getMessage());
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
    <title>Listar Playlists - AutoDJ</title>
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
        .btn-primary, .btn-danger {
            min-width: 80px;
            text-align: center;
        }
        body {
            overflow: auto !important;
        }
        .modal-backdrop {
            transition: opacity 0.15s linear;
        }
        .modal-backdrop.fade {
            opacity: 0 !important;
            display: none !important;
        }
        .modal-open {
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
        <h1 class="my-4 text-center text-white"><i class="fas fa-list-alt me-2"></i> Playlists Criadas</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i> Lista de Playlists
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                        <?= $msg['text'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (empty($playlists)): ?>
                    <p class="text-muted">Nenhuma playlist encontrada.</p>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Horário</th>
                                <th>Dias</th>
                                <th>Arquivos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playlists as $playlist): ?>
                                <tr id="playlist-<?= $playlist['id'] ?>">
                                    <td><?= htmlspecialchars($playlist['nome']) ?></td>
                                    <td><?= $playlist['horario_inicio'] ?> - <?= $playlist['horario_fim'] ?></td>
                                    <td>
                                        <?php
                                        $dias = explode(',', $playlist['dias_semana']);
                                        $diasMap = [
                                            'seg' => 'Seg', 'ter' => 'Ter', 'qua' => 'Qua',
                                            'qui' => 'Qui', 'sex' => 'Sex', 'sab' => 'Sáb', 'dom' => 'Dom'
                                        ];
                                        $diasNomes = array_map(fn($dia) => $diasMap[$dia] ?? $dia, $dias);
                                        echo implode(', ', $diasNomes);
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM playlist_arquivos WHERE playlist_id = ?");
                                        $stmt->execute([$playlist['id']]);
                                        $totalArquivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                        echo $totalArquivos > 0 ? "$totalArquivos arquivos" : '<span class="text-muted">Nenhum arquivo</span>';
                                        ?>
                                        <?php if ($totalArquivos > 0): ?>
                                            <br>
                                            <a href="playlist_arquivos.php?id=<?= $playlist['id'] ?>" class="btn btn-link btn-sm">
                                                <i class="fas fa-eye me-1"></i> Ver Arquivos
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit_playlist.php?id=<?= $playlist['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Editar</a>
                                        <button class="btn btn-danger btn-sm delete-playlist" data-id="<?= $playlist['id'] ?>" data-nome="<?= htmlspecialchars($playlist['nome']) ?>"><i class="fas fa-trash"></i> Excluir</button>
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
                    Tem certeza que deseja excluir a playlist "<span id="playlist-nome"></span>"? Esta ação não pode ser desfeita.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete">Excluir</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        console.log('Carregando JavaScript de lista_playlists.php');

        document.querySelectorAll('.delete-playlist').forEach(button => {
            button.removeEventListener('click', handleDeleteClick);
            button.addEventListener('click', handleDeleteClick);
        });

        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        let isProcessing = false;

        function handleDeleteClick() {
            if (isProcessing) return;
            isProcessing = true;
            const button = this;
            button.disabled = true;
            const playlistId = button.dataset.id;
            const playlistNome = button.dataset.nome;
            document.getElementById('playlist-nome').textContent = playlistNome;
            document.getElementById('confirm-delete').dataset.id = playlistId;
            deleteModal.show();
        }

        const confirmButton = document.getElementById('confirm-delete');
        confirmButton.removeEventListener('click', handleConfirmDelete);
        confirmButton.addEventListener('click', handleConfirmDelete);

        async function handleConfirmDelete() {
            if (isProcessing) return;
            isProcessing = true;
            const button = this;
            button.disabled = true;
            const playlistId = button.dataset.id;

            document.body.focus();
            deleteModal.hide();
            resetModalState();

            try {
                const response = await fetch('lista_playlists.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete&playlist_id=${encodeURIComponent(playlistId)}`
                });

                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }

                const data = await response.json();
                console.log('Resposta do servidor:', data);

                if (data.status === 'success') {
                    const row = document.getElementById(`playlist-${playlistId}`);
                    if (row) row.remove();
                    showAlert('success', data.message);
                } else {
                    showAlert('danger', data.message);
                }
            } catch (error) {
                console.error('Erro na exclusão:', error);
                deleteModal.hide();
                resetModalState();
                showAlert('danger', `Erro ao excluir playlist: ${error.message}`);
            } finally {
                isProcessing = false;
                button.disabled = false;
                document.querySelectorAll('.delete-playlist').forEach(btn => {
                    if (btn.dataset.id !== playlistId) btn.disabled = false;
                });
                deleteModal.dispose();
                document.getElementById('deleteModal').classList.add('fade');
                const newModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                Object.assign(deleteModal, newModal);
            }
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.setAttribute('role', 'alert');
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('.card-body').prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 3000);
        }

        function resetModalState() {
            console.log('Limpando estado do modal');
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.classList.remove('show');
                backdrop.classList.add('fade');
                setTimeout(() => backdrop.remove(), 150);
            }
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            const modal = document.getElementById('deleteModal');
            if (modal) {
                modal.removeAttribute('aria-hidden');
                modal.setAttribute('inert', '');
            }
        }

        ['.btn-secondary[data-bs-dismiss="modal"]', '.btn-close'].forEach(selector => {
            document.querySelector(selector).addEventListener('click', () => {
                document.body.focus();
                deleteModal.hide();
                resetModalState();
                isProcessing = false;
                document.querySelectorAll('.delete-playlist').forEach(btn => btn.disabled = false);
            });
        });
    </script>
</body>
</html>