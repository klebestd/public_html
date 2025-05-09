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

error_log("Iniciando comerciais.php");

$msg = null;
$comerciais = [];

try {
    $stmt = $pdo->query("SELECT * FROM arquivos WHERE tipo = 'comercial' ORDER BY data_upload DESC");
    $comerciais = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Comerciais carregados: " . count($comerciais) . " registros");
} catch (PDOException $e) {
    error_log("Erro ao carregar comerciais: " . $e->getMessage());
    $msg = ["type" => "danger", "text" => "Erro ao carregar comerciais: " . $e->getMessage()];
}

// Processar exclusão de comercial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    try {
        $comercialId = (int)($_POST['comercial_id'] ?? 0);
        if ($comercialId <= 0) {
            throw new Exception("ID do comercial inválido.");
        }

        $pdo->beginTransaction();

        // Verificar se o comercial existe e obter o caminho do arquivo
        $stmt = $pdo->prepare("SELECT caminho, nome FROM arquivos WHERE id = ? AND tipo = 'comercial'");
        $stmt->execute([$comercialId]);
        $comercial = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$comercial) {
            throw new Exception("Comercial não encontrado.");
        }

        // Verificar se o comercial está associado a alguma playlist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM playlist_arquivos WHERE arquivo_id = ?");
        $stmt->execute([$comercialId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Comercial está associado a uma playlist. Remova-o das playlists antes de excluir.");
        }

        // Tentar excluir o arquivo físico
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $comercial['caminho'];
        $alternativePath = COMERCIAIS_DIR . basename($comercial['caminho']);
        error_log("Tentando excluir arquivo. Caminho principal: $filePath");
        error_log("Caminho alternativo: $alternativePath");

        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                error_log("Falha ao excluir arquivo: $filePath. Verifique permissões.");
                // Continuar mesmo com falha na exclusão do arquivo
            } else {
                error_log("Arquivo removido com sucesso: $filePath");
            }
        } elseif (file_exists($alternativePath)) {
            if (!unlink($alternativePath)) {
                error_log("Falha ao excluir arquivo alternativo: $alternativePath. Verifique permissões.");
            } else {
                error_log("Arquivo alternativo removido com sucesso: $alternativePath");
            }
        } else {
            error_log("Arquivo não encontrado nos caminhos: $filePath ou $alternativePath");
        }

        // Deletar registro do banco
        $stmt = $pdo->prepare("DELETE FROM arquivos WHERE id = ?");
        $stmt->execute([$comercialId]);
        error_log("Comercial deletado do banco: ID=$comercialId, Nome={$comercial['nome']}");

        // Invalidar cache
        if (CACHE_ENABLED && function_exists('apcu_delete')) {
            apcu_delete('comerciais_list');
            error_log("Cache APCu (comerciais_list) invalidado após exclusão.");
        }

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Comercial excluído com sucesso!"]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao excluir comercial: " . $e->getMessage());
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
    <title>Comerciais Enviados - AutoDJ</title>
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
                        <a class="nav-link active" href="comerciais.php"><i class="fas fa-bullhorn me-1"></i> Comerciais</a>
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
        <h1 class="my-4 text-center text-white"><i class="fas fa-bullhorn me-2"></i> Comerciais Enviados</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-bullhorn me-2"></i> Lista de Comerciais
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                        <?= $msg['text'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (empty($comerciais)): ?>
                    <p class="text-muted">Nenhum comercial encontrado.</p>
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
                            <?php foreach ($comerciais as $comercial): ?>
                                <tr id="comercial-<?= $comercial['id'] ?>">
                                    <td><?= htmlspecialchars($comercial['nome']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($comercial['data_upload'])) ?></td>
                                    <td>
                                        <button class="btn btn-danger action-btn delete-comercial" data-id="<?= $comercial['id'] ?>" data-nome="<?= htmlspecialchars($comercial['nome']) ?>"><i class="fas fa-trash"></i> Excluir</button>
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
                    Tem certeza que deseja excluir o comercial "<span id="comercial-nome"></span>"? Esta ação não pode ser desfeita.
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
        // Manipular exclusão de comercial
        const deleteButtons = document.querySelectorAll('.delete-comercial');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        let currentComercialId = null;

        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                currentComercialId = this.dataset.id;
                const comercialNome = this.dataset.nome;
                document.getElementById('comercial-nome').textContent = comercialNome;
                deleteModal.show();
            });
        });

        document.getElementById('confirm-delete').addEventListener('click', async function() {
            try {
                const response = await fetch('comerciais.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete&comercial_id=${currentComercialId}`
                });
                const data = await response.json();
                if (data.status === 'success') {
                    document.getElementById(`comercial-${currentComercialId}`).remove();
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.setAttribute('role', 'alert');
                    alertDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('.card-body').prepend(alertDiv);
                    setTimeout(() => alertDiv.remove(), 3000);
                    deleteModal.hide();
                } else {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.setAttribute('role', 'alert');
                    alertDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('.card-body').prepend(alertDiv);
                    setTimeout(() => alertDiv.remove(), 3000);
                }
            } catch (error) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    Erro ao excluir comercial: ${error.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.card-body').prepend(alertDiv);
                setTimeout(() => alertDiv.remove(), 3000);
            }
        });
    </script>
</body>
</html>