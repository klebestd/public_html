<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();

// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar dependências
if (!file_exists(__DIR__ . '/includes/db.php') || !file_exists(__DIR__ . '/includes/functions.php')) {
    error_log("Erro: Arquivos db.php ou functions.php não encontrados em " . __DIR__ . "/includes/");
    http_response_code(500);
    echo "Erro interno: Arquivos de configuração ausentes.";
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Verificar constantes
if (!defined('PLAYLISTS_DIR') || !defined('BASE_URL')) {
    error_log("Erro: Constantes PLAYLISTS_DIR ou BASE_URL não definidas.");
    http_response_code(500);
    echo "Erro interno: Configuração incompleta.";
    exit;
}

// Verificar diretório de playlists
if (!is_dir(PLAYLISTS_DIR) || !is_writable(PLAYLISTS_DIR)) {
    error_log("Erro: Diretório PLAYLISTS_DIR (" . PLAYLISTS_DIR . ") não existe ou não tem permissão de escrita.");
    http_response_code(500);
    echo "Erro interno: Diretório de playlists inacessível.";
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

error_log("Iniciando edit_playlist.php");

$msg = null;
$playlist = null;
$arquivosAssociados = [];
$musicasDisponiveis = [];
$comerciaisDisponiveis = [];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $msg = ["type" => "danger", "text" => "ID da playlist inválido."];
} else {
    $playlistId = (int)$_GET['id'];
    try {
        // Carregar playlist
        $stmt = $pdo->prepare("SELECT * FROM playlists WHERE id = ?");
        $stmt->execute([$playlistId]);
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$playlist) {
            $msg = ["type" => "danger", "text" => "Playlist não encontrada."];
        } else {
            error_log("Playlist carregada: ID=$playlistId, Nome=" . $playlist['nome']);
        }

        // Carregar arquivos associados (músicas e comerciais) com ordem
        $stmt = $pdo->prepare("
            SELECT a.id, a.nome, a.tipo 
            FROM arquivos a 
            JOIN playlist_arquivos pa ON a.id = pa.arquivo_id 
            WHERE pa.playlist_id = ? 
            ORDER BY pa.ordem
        ");
        $stmt->execute([$playlistId]);
        $arquivosAssociados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Arquivos associados carregados: " . count($arquivosAssociados) . " registros");

        // Carregar músicas disponíveis
        $stmt = $pdo->prepare("SELECT id, nome, tipo FROM arquivos WHERE tipo = 'musica' ORDER BY nome");
        $stmt->execute();
        $musicasDisponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Músicas disponíveis carregadas: " . count($musicasDisponiveis) . " registros");

        // Carregar comerciais disponíveis
        $stmt = $pdo->prepare("SELECT id, nome, tipo FROM arquivos WHERE tipo = 'comercial' ORDER BY nome");
        $stmt->execute();
        $comerciaisDisponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Comerciais disponíveis carregados: " . count($comerciaisDisponiveis) . " registros");
    } catch (PDOException $e) {
        error_log("Erro ao carregar dados: " . $e->getMessage());
        $msg = ["type" => "danger", "text" => "Erro ao carregar dados: " . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $playlist) {
    $nome = trim($_POST['nome'] ?? '');
    $horarioInicio = $_POST['horario_inicio'] ?? '';
    $horarioFim = $_POST['horario_fim'] ?? '';
    $diasSemana = isset($_POST['dias_semana']) ? implode(',', $_POST['dias_semana']) : '';
    $arquivosSelecionados = json_decode($_POST['arquivos_ordenados'] ?? '[]', true);

    try {
        // Validar entradas
        if (empty($nome)) {
            throw new Exception("O nome da playlist é obrigatório.");
        }
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $horarioInicio)) {
            throw new Exception("Horário de início inválido.");
        }
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $horarioFim)) {
            throw new Exception("Horário de fim inválido.");
        }
        if (empty($arquivosSelecionados)) {
            throw new Exception("Selecione pelo menos um arquivo para a playlist.");
        }

        // Log para depuração
        error_log("Arquivos selecionados recebidos: " . json_encode($arquivosSelecionados));

        // Iniciar transação
        $pdo->beginTransaction();

        // Atualizar playlist
        $sanitizedNome = iconv('UTF-8', 'ASCII//TRANSLIT', $nome);
        $sanitizedNome = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $sanitizedNome);
        $sanitizedNome = substr($sanitizedNome, 0, 200);
        $newPlaylistPath = PLAYLISTS_DIR . $sanitizedNome . '_' . $playlistId . '.m3u';

        $stmt = $pdo->prepare("UPDATE playlists SET nome = ?, horario_inicio = ?, horario_fim = ?, dias_semana = ?, caminho = ? WHERE id = ?");
        $stmt->execute([$nome, $horarioInicio, $horarioFim, $diasSemana, $newPlaylistPath, $playlistId]);
        error_log("Playlist atualizada: ID=$playlistId, Nome=$nome");

        // Remover associações antigas
        $stmt = $pdo->prepare("DELETE FROM playlist_arquivos WHERE playlist_id = ?");
        $stmt->execute([$playlistId]);
        error_log("Associações antigas removidas para playlist ID=$playlistId");

        // Adicionar novas associações com ordem
        foreach ($arquivosSelecionados as $index => $arquivoId) {
            if (is_numeric($arquivoId)) {
                $stmt = $pdo->prepare("INSERT INTO playlist_arquivos (playlist_id, arquivo_id, ordem) VALUES (?, ?, ?)");
                $stmt->execute([$playlistId, $arquivoId, $index]);
            }
        }
        error_log("Novas associações adicionadas: " . count($arquivosSelecionados) . " arquivos");

        // Regenerar arquivo .m3u
        $arquivos = [];
        foreach ($arquivosSelecionados as $arquivoId) {
            $stmt = $pdo->prepare("SELECT caminho FROM arquivos WHERE id = ?");
            $stmt->execute([$arquivoId]);
            $caminho = $stmt->fetchColumn();
            if ($caminho) {
                $arquivos[] = $caminho;
            } else {
                error_log("Caminho não encontrado para arquivo_id=$arquivoId");
            }
        }
        error_log("Caminhos recuperados para .m3u: " . json_encode($arquivos));

        $m3uContent = "#EXTM3U\n";
        foreach ($arquivos as $caminho) {
            $caminho = str_replace('/Uploads/', '/uploads/', $caminho);
            $m3uContent .= BASE_URL . $caminho . "\n";
        }

        // Remover arquivo .m3u antigo, se diferente
        if ($playlist['caminho'] !== $newPlaylistPath && file_exists($playlist['caminho'])) {
            unlink($playlist['caminho']);
            error_log("Arquivo .m3u antigo removido: " . $playlist['caminho']);
        }

        if (!file_put_contents($newPlaylistPath, $m3uContent)) {
            throw new Exception("Erro ao atualizar o arquivo .m3u da playlist.");
        }
        error_log("Arquivo .m3u atualizado: $newPlaylistPath");

        // Forçar atualização de active.m3u
        $activeM3uFile = PLAYLISTS_DIR . 'active.m3u';
        if (file_exists($activeM3uFile)) {
            unlink($activeM3uFile);
            error_log("Arquivo active.m3u removido antes de atualizar: $activeM3uFile");
        }
        if (!file_put_contents($activeM3uFile, $m3uContent)) {
            error_log("Erro ao atualizar active.m3u: $activeM3uFile");
        } else {
            error_log("Arquivo active.m3u atualizado: $activeM3uFile");
        }

        // Invalidar cache, se aplicável
        if (defined('CACHE_ENABLED') && CACHE_ENABLED && function_exists('apcu_delete')) {
            apcu_delete('musicas_list');
            apcu_delete('active_playlist');
            error_log("Cache APCu invalidado após edição da playlist.");
        }

        // Confirmar transação
        $pdo->commit();
        $msg = ["type" => "success", "text" => "Playlist atualizada com sucesso!"];
        error_log("Edição da playlist concluída: ID=$playlistId");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao atualizar playlist: " . $e->getMessage());
        $msg = ["type" => "danger", "text" => "Erro: " . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Playlist - AutoDJ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .sortable-list {
            min-height: 100px;
            max-height: 300px;
            overflow-y: auto;
            border: 2px dashed #6c757d;
            padding: 10px;
            background-color: #2a2a2a;
            border-radius: 5px;
        }
        .sortable-item {
            padding: 8px;
            margin: 5px 0;
            background-color: #3a3a3a;
            border: 1px solid #6c757d;
            border-radius: 5px;
            cursor: move;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sortable-item:hover {
            background-color: #4a4a4a;
        }
        .sortable-ghost {
            opacity: 0.4;
        }
        .column-container {
            margin-bottom: 20px;
        }
        .column-container .col-md-4 {
            padding: 10px;
        }
        .delete-btn {
            cursor: pointer;
            color: #dc3545;
        }
        .delete-btn:hover {
            color: #c82333;
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
                        <a class="nav-link active" href="playlists.php"><i class="fas fa-list me-1"></i> Playlists</a>
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
        <h1 class="my-4 text-center text-white"><i class="fas fa-edit me-2"></i> Editar Playlist</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i> Detalhes da Playlist
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($msg['text']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($playlist): ?>
                    <form method="post" id="playlist-form">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome da Playlist:</label>
                            <input type="text" name="nome" id="nome" class="form-control" value="<?= htmlspecialchars($playlist['nome']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="horario_inicio" class="form-label">Horário de Início (HH:MM:SS):</label>
                            <input type="text" name="horario_inicio" id="horario_inicio" class="form-control" value="<?= htmlspecialchars($playlist['horario_inicio']) ?>" pattern="([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" placeholder="HH:MM:SS" required>
                        </div>
                        <div class="mb-3">
                            <label for="horario_fim" class="form-label">Horário de Fim (HH:MM:SS):</label>
                            <input type="text" name="horario_fim" id="horario_fim" class="form-control" value="<?= htmlspecialchars($playlist['horario_fim']) ?>" pattern="([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" placeholder="HH:MM:SS" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dias da Semana:</label>
                            <?php
                            $dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];
                            $diasSelecionados = explode(',', $playlist['dias_semana']);
                            foreach ($dias as $dia):
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="<?= htmlspecialchars($dia) ?>" id="dia_<?= htmlspecialchars($dia) ?>" <?= in_array($dia, $diasSelecionados) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="dia_<?= htmlspecialchars($dia) ?>">
                                        <?= htmlspecialchars(ucfirst($dia)) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="column-container">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Músicas Disponíveis:</label>
                                    <div id="musicas-disponiveis" class="sortable-list">
                                        <?php foreach ($musicasDisponiveis as $musica): ?>
                                            <div class="sortable-item" data-id="<?= htmlspecialchars($musica['id']) ?>">
                                                <i class="fas fa-music me-2"></i>
                                                <?= htmlspecialchars($musica['nome']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Comerciais Disponíveis:</label>
                                    <div id="comerciais-disponiveis" class="sortable-list">
                                        <?php foreach ($comerciaisDisponiveis as $comercial): ?>
                                            <div class="sortable-item" data-id="<?= htmlspecialchars($comercial['id']) ?>">
                                                <i class="fas fa-bullhorn me-2"></i>
                                                <?= htmlspecialchars($comercial['nome']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Arquivos da Playlist (arraste para ordenar):</label>
                                    <div id="arquivos-playlist" class="sortable-list">
                                        <?php foreach ($arquivosAssociados as $arquivo): ?>
                                            <div class="sortable-item" data-id="<?= htmlspecialchars($arquivo['id']) ?>">
                                                <span>
                                                    <i class="fas <?= $arquivo['tipo'] === 'musica' ? 'fa-music' : 'fa-bullhorn' ?> me-2"></i>
                                                    <?= htmlspecialchars($arquivo['nome']) ?>
                                                    (<?= $arquivo['tipo'] === 'musica' ? 'Música' : 'Comercial' ?>)
                                                </span>
                                                <i class="fas fa-trash delete-btn"></i>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="arquivos_ordenados" id="arquivos-ordenados">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Salvar Alterações</button>
                        <a href="lista_playlists.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Voltar</a>
                    </form>
                <?php else: ?>
                    <p class="text-muted">Nenhuma playlist selecionada para edição.</p>
                    <a href="lista_playlists.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Voltar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        // Inicializar SortableJS para Músicas Disponíveis
        new Sortable(document.getElementById('musicas-disponiveis'), {
            group: {
                name: 'shared',
                pull: 'clone',
                put: false
            },
            animation: 150,
            ghostClass: 'sortable-ghost',
            sort: false
        });

        // Inicializar SortableJS para Comerciais Disponíveis
        new Sortable(document.getElementById('comerciais-disponiveis'), {
            group: {
                name: 'shared',
                pull: 'clone',
                put: false
            },
            animation: 150,
            ghostClass: 'sortable-ghost',
            sort: false
        });

        // Inicializar SortableJS para Arquivos da Playlist
        new Sortable(document.getElementById('arquivos-playlist'), {
            group: 'shared',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onSort: updateArquivosOrdenados,
            onAdd: function(evt) {
                // Adicionar botão de exclusão aos itens recém-adicionados
                const item = evt.item;
                const deleteBtn = document.createElement('i');
                deleteBtn.className = 'fas fa-trash delete-btn';
                item.appendChild(deleteBtn);
                updateArquivosOrdenados();
            }
        });

        // Atualizar campo oculto com a ordem dos arquivos
        function updateArquivosOrdenados() {
            const arquivos = document.querySelectorAll('#arquivos-playlist .sortable-item');
            const arquivoIds = Array.from(arquivos).map(item => item.dataset.id);
            console.log('Ordem dos arquivos:', arquivoIds); // Log para depuração
            document.getElementById('arquivos-ordenados').value = JSON.stringify(arquivoIds);
        }

        // Adicionar evento de exclusão
        document.getElementById('arquivos-playlist').addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-btn')) {
                e.target.parentElement.remove();
                updateArquivosOrdenados();
            }
        });

        // Inicializar campo oculto com a ordem atual
        updateArquivosOrdenados();

        // Validar formulário antes de enviar
        document.getElementById('playlist-form').addEventListener('submit', function(e) {
            const arquivos = document.getElementById('arquivos-ordenados').value;
            if (!arquivos || JSON.parse(arquivos).length === 0) {
                e.preventDefault();
                alert('Selecione pelo menos um arquivo para a playlist.');
            }
        });
    </script>
</body>
</html>