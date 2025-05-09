<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

ensureDirectories();
$streamStatus = file_exists(STREAM_STATUS_FILE) ? file_get_contents(STREAM_STATUS_FILE) : 'stopped';

// Determinar a playlist ativa
$currentTime = date('H:i:s');
$currentDay = strtolower(date('D'));
$daysMap = ['mon' => 'seg', 'tue' => 'ter', 'wed' => 'qua', 'thu' => 'qui', 'fri' => 'sex', 'sat' => 'sab', 'sun' => 'dom'];
$currentDayPt = $daysMap[$currentDay];

$stmt = $pdo->prepare("
    SELECT id, nome, horario_inicio, horario_fim, dias_semana
    FROM playlists
    WHERE horario_inicio <= ? 
    AND horario_fim >= ? 
    AND FIND_IN_SET(?, REPLACE(LOWER(dias_semana), ' ', '')) 
    AND nome != 'default'
    ORDER BY horario_inicio ASC
    LIMIT 1
");
$stmt->execute([$currentTime, $currentTime, $currentDayPt]);
$activePlaylist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activePlaylist) {
    // Tentar playlist padrão
    $stmt = $pdo->prepare("SELECT id, nome, horario_inicio, horario_fim, dias_semana FROM playlists WHERE nome = 'default' LIMIT 1");
    $stmt->execute();
    $activePlaylist = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($activePlaylist) {
        error_log("Index: Playlist padrão carregada: ID={$activePlaylist['id']}, Nome={$activePlaylist['nome']}");
    } else {
        error_log("Index: Nenhuma playlist ativa ou padrão encontrada");
    }
} else {
    error_log("Index: Playlist ativa encontrada: ID={$activePlaylist['id']}, Nome={$activePlaylist['nome']}");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDJ - Painel</title>
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
        <h1 class="my-4 text-center text-white"><i class="fas fa-broadcast-tower me-2"></i> AutoDJ - Painel Principal</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-play me-2"></i> Controle do Streaming
            </div>
            <div class="card-body">
                <?php if ($activePlaylist): ?>
                    <p>Playlist ativa: <strong><?= htmlspecialchars($activePlaylist['nome']) ?></strong> (<?= $activePlaylist['horario_inicio'] ?> - <?= $activePlaylist['horario_fim'] ?>, Dias: <?= $activePlaylist['dias_semana'] ?>)</p>
                    <p>Status: <strong id="stream-status-text"><?= $streamStatus === 'playing' ? 'Tocando' : 'Parada' ?></strong></p>
                    <div class="stream-link-container">
                        <input type="text" class="form-control" id="stream-link" value="<?= BASE_URL ?>/stream.php" readonly>
                        <button class="btn btn-copy" onClick="copyStreamLink()"><i class="fas fa-copy me-1"></i> Copiar</button>
                    </div>
                    <div class="audio-player-container">
                        <span class="player-status <?= $streamStatus ?>" id="player-status">
                            <i class="fas fa-<?= $streamStatus === 'playing' ? 'play' : 'pause' ?>"></i>
                            <?= $streamStatus === 'playing' ? 'Tocando' : 'Parado' ?>
                        </span>
                        <audio controls id="audio-player">
                            <source src="stream.php" type="audio/mpeg">
                            Seu navegador não suporta o elemento de áudio.
                        </audio>
                        <button class="btn btn-skip" onClick="skipTrack()" title="Pular música"><i class="fas fa-forward"></i></button>
                    </div>
                    <div class="mt-3">
                        <button id="stop-btn" class="btn btn-danger action-btn">
                            <i class="fas fa-stop me-1"></i> Parar
                            <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true"></span>
                        </button>
                        <button id="play-btn" class="btn btn-primary action-btn">
                            <i class="fas fa-play me-1"></i> Tocar
                            <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Nenhuma playlist ativa no momento.</p>
                    <div class="stream-link-container">
                        <input type="text" class="form-control" id="stream-link" value="<?= BASE_URL ?>/stream.php" readonly>
                        <button class="btn btn-copy" onClick="copyStreamLink()"><i class="fas fa-copy me-1"></i> Copiar</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-tools me-2"></i> Navegação Rápida
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <a href="upload.php" class="nav-button upload"><i class="fas fa-upload"></i> Upload de Arquivos</a>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <a href="playlists.php" class="nav-button playlists"><i class="fas fa-list"></i> Gerenciar Playlists</a>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <a href="lista_playlists.php" class="nav-button edit-playlists"><i class="fas fa-edit"></i> Editar Playlists</a>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <a href="comerciais.php" class="nav-button comerciais"><i class="fas fa-bullhorn"></i> Comerciais Enviados</a>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <a href="musicas.php" class="nav-button musicas"><i class="fas fa-music"></i> Músicas Enviadas</a>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <a href="lista_playlists.php" class="nav-button lista-playlists"><i class="fas fa-list-alt"></i> Listar Playlists</a>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <a href="edit_user.php" class="nav-button edit-user"><i class="fas fa-user-edit"></i> Editar Perfil</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Notificação -->
    <div class="modal fade" id="notification-modal" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Notificação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-message">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        const audioPlayer = document.getElementById('audio-player');
        const playerStatus = document.getElementById('player-status');
        const streamStatusText = document.getElementById('stream-status-text');
        const playBtn = document.getElementById('play-btn');
        const stopBtn = document.getElementById('stop-btn');
        const playSpinner = playBtn ? playBtn.querySelector('.spinner') : null;
        const stopSpinner = stopBtn ? stopBtn.querySelector('.spinner') : null;

        function showModal(type, message) {
            const modal = new bootstrap.Modal(document.getElementById('notification-modal'));
            const modalMessage = document.getElementById('modal-message');
            modalMessage.className = `alert alert-${type}`;
            modalMessage.textContent = message;
            modal.show();
        }

        function copyStreamLink() {
            const streamLink = document.getElementById('stream-link');
            streamLink.select();
            document.execCommand('copy');
            showModal('success', 'Link do streaming copiado!');
        }

        async function sendRequest(action) {
            const spinner = action === 'play' ? playSpinner : stopSpinner;
            const btn = action === 'play' ? playBtn : stopBtn;
            if (spinner) spinner.classList.add('active');
            if (btn) btn.disabled = true;

            try {
                const response = await fetch(`stream.php?action=${action}`, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: AbortSignal.timeout(2000)
                });
                if (!response.ok) {
                    throw new Error(`Erro na ação ${action}: ${response.statusText}`);
                }
                const data = await response.json();
                if (data.status !== 'success') {
                    throw new Error(data.message || `Falha na ação ${action}`);
                }
                return data;
            } catch (error) {
                console.error(error);
                showModal('danger', `Erro: ${error.message}`);
                throw error;
            } finally {
                if (spinner) spinner.classList.remove('active');
                if (btn) btn.disabled = false;
            }
        }

        function reloadStream() {
            if (audioPlayer) {
                audioPlayer.src = 'stream.php?t=' + new Date().getTime();
                audioPlayer.play();
                playerStatus.className = 'player-status playing';
                playerStatus.innerHTML = '<i class="fas fa-play"></i> Tocando';
                streamStatusText.textContent = 'Tocando';
            }
        }

        function stopAudio() {
            sendRequest('stop').then(() => {
                if (audioPlayer) {
                    audioPlayer.pause();
                    audioPlayer.src = '';
                    playerStatus.className = 'player-status stopped';
                    playerStatus.innerHTML = '<i class="fas fa-pause"></i> Parado';
                    streamStatusText.textContent = 'Parada';
                }
                showModal('success', 'Streaming parado com sucesso!');
            }).catch(() => {});
        }

        function skipTrack() {
            fetch('stream.php?action=skip', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(response => response.json()).then(data => {
                if (data.status === 'success') {
                    reloadStream();
                    showModal('success', 'Música pulada com sucesso!');
                } else {
                    showModal('danger', 'Erro ao pular música.');
                }
            }).catch(() => showModal('danger', 'Erro ao pular música.'));
        }

        if (playBtn) {
            playBtn.addEventListener('click', () => {
                sendRequest('play').then(() => {
                    reloadStream();
                    showModal('success', 'Streaming iniciado com sucesso!');
                });
            });
        }

        if (stopBtn) {
            stopBtn.addEventListener('click', stopAudio);
        }
    </script>
</body>
</html>