<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('America/Sao_Paulo');

ensureDirectories();

// Fechar sessão para evitar bloqueios
session_write_close();

// Controle de ações (play/stop/skip)
$action = $_GET['action'] ?? '';
if ($action) {
    header('Content-Type: application/json');
}

if ($action === 'play') {
    $currentTime = date('H:i:s');
    $currentDay = strtolower(date('D'));
    $daysMap = [
        'mon' => 'seg', 'tue' => 'ter', 'wed' => 'qua', 'thu' => 'qui',
        'fri' => 'sex', 'sat' => 'sab', 'sun' => 'dom'
    ];
    $currentDayPt = $daysMap[$currentDay];

    try {
        error_log("Stream: Iniciando play em " . date('Y-m-d H:i:s') . ", dia: $currentDayPt, horário: $currentTime");
        $stmt = $pdo->prepare("
            SELECT id, nome, caminho 
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

        if ($activePlaylist) {
            error_log("Stream: Playlist programada encontrada: ID={$activePlaylist['id']}, Nome={$activePlaylist['nome']}, Caminho={$activePlaylist['caminho']}");
            if (!file_exists($activePlaylist['caminho'])) {
                error_log("Stream: Arquivo da playlist não encontrado: {$activePlaylist['caminho']}");
                echo json_encode(['status' => 'error', 'message' => 'Arquivo da playlist não encontrado']);
                exit;
            }
            // Verificar se a playlist contém arquivos válidos
            $lines = file($activePlaylist['caminho'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $validFiles = [];
            foreach ($lines as $line) {
                if (empty($line) || strpos($line, '#') === 0) {
                    error_log("Stream: Ignorando linha inválida em {$activePlaylist['caminho']}: $line");
                    continue;
                }
                $filePath = str_replace(BASE_URL . '/', __DIR__ . '/', $line);
                if (file_exists($filePath)) {
                    $validFiles[] = $filePath;
                    error_log("Stream: Arquivo válido encontrado em playlist: $filePath");
                } else {
                    error_log("Stream: Arquivo não encontrado em playlist: $filePath");
                }
            }
            if (empty($validFiles)) {
                error_log("Stream: Nenhuma música válida encontrada na playlist: {$activePlaylist['caminho']}");
                echo json_encode(['status' => 'error', 'message' => 'Nenhuma música válida na playlist']);
                exit;
            }
            copy($activePlaylist['caminho'], ACTIVE_M3U);
            file_put_contents(STREAM_STATUS_FILE, 'playing');
            setCurrentTrackIndex(0);
            error_log("Stream: Streaming iniciado com playlist: ID={$activePlaylist['id']}, Nome={$activePlaylist['nome']}, Caminho={$activePlaylist['caminho']}");
            echo json_encode(['status' => 'success', 'message' => 'Streaming iniciado']);
        } else {
            error_log("Stream: Nenhuma playlist programada encontrada para horário=$currentTime, dia=$currentDayPt");
            $stmt = $pdo->prepare("SELECT id, nome, caminho FROM playlists WHERE nome = 'default' LIMIT 1");
            $stmt->execute();
            $defaultPlaylist = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($defaultPlaylist) {
                error_log("Stream: Tentando carregar playlist padrão: ID={$defaultPlaylist['id']}, Nome={$defaultPlaylist['nome']}, Caminho={$defaultPlaylist['caminho']}");
                if (!file_exists($defaultPlaylist['caminho'])) {
                    error_log("Stream: Arquivo da playlist padrão não encontrado: {$defaultPlaylist['caminho']}");
                    echo json_encode(['status' => 'error', 'message' => 'Arquivo da playlist padrão não encontrado']);
                    exit;
                }
                // Verificar se a playlist padrão contém arquivos válidos
                $lines = file($defaultPlaylist['caminho'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $validFiles = [];
                foreach ($lines as $line) {
                    if (empty($line) || strpos($line, '#') === 0) {
                        error_log("Stream: Ignorando linha inválida em {$defaultPlaylist['caminho']}: $line");
                        continue;
                    }
                    $filePath = str_replace(BASE_URL . '/', __DIR__ . '/', $line);
                    if (file_exists($filePath)) {
                        $validFiles[] = $filePath;
                        error_log("Stream: Arquivo válido encontrado em playlist padrão: $filePath");
                    } else {
                        error_log("Stream: Arquivo não encontrado em playlist padrão: $filePath");
                    }
                }
                if (empty($validFiles)) {
                    error_log("Stream: Nenhuma música válida encontrada na playlist padrão: {$defaultPlaylist['caminho']}");
                    echo json_encode(['status' => 'error', 'message' => 'Nenhuma música válida na playlist padrão']);
                    exit;
                }
                copy($defaultPlaylist['caminho'], ACTIVE_M3U);
                file_put_contents(STREAM_STATUS_FILE, 'playing');
                setCurrentTrackIndex(0);
                error_log("Stream: Streaming iniciado com playlist padrão: ID={$defaultPlaylist['id']}, Nome={$defaultPlaylist['nome']}, Caminho={$defaultPlaylist['caminho']}");
                echo json_encode(['status' => 'success', 'message' => 'Streaming iniciado com playlist padrão']);
            } else {
                error_log("Stream: Nenhuma playlist ativa ou padrão encontrada.");
                echo json_encode(['status' => 'error', 'message' => 'Nenhuma playlist disponível']);
            }
        }
    } catch (Exception $e) {
        error_log("Stream: Erro em stream.php?action=play: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erro ao iniciar streaming: ' . $e->getMessage()]);
    }
    exit;
} elseif ($action === 'stop') {
    try {
        file_put_contents(ACTIVE_M3U, '');
        file_put_contents(STREAM_STATUS_FILE, 'stopped');
        setCurrentTrackIndex(0);
        error_log("Stream: Streaming parado.");
        echo json_encode(['status' => 'success', 'message' => 'Streaming parado']);
    } catch (Exception $e) {
        error_log("Stream: Erro em stream.php?action=stop: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erro ao parar streaming']);
    }
    exit;
} elseif ($action === 'skip' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $currentIndex = getCurrentTrackIndex();
        setCurrentTrackIndex($currentIndex + 1);
        error_log("Stream: Música pulada. Novo índice: " . ($currentIndex + 1));
        echo json_encode(['status' => 'success', 'message' => 'Música pulada']);
    } catch (Exception $e) {
        error_log("Stream: Erro em stream.php?action=skip: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erro ao pular música']);
    }
    exit;
}

// Streaming
$playlistFile = ACTIVE_M3U;
if (!file_exists($playlistFile) || filesize($playlistFile) === 0) {
    error_log("Stream: Arquivo de playlist não encontrado ou vazio: $playlistFile");
    file_put_contents(STREAM_STATUS_FILE, 'stopped');
    http_response_code(404);
    exit;
}

header('Content-Type: audio/mpeg');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Accept-Ranges: none');

$lines = file($playlistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$files = [];
foreach ($lines as $line) {
    // Ignorar cabeçalhos M3U e linhas vazias
    if (empty($line) || strpos($line, '#') === 0) {
        error_log("Stream: Ignorando linha inválida em $playlistFile: $line");
        continue;
    }
    $filePath = str_replace(BASE_URL . '/', __DIR__ . '/', $line);
    if (file_exists($filePath)) {
        $files[] = $filePath;
        error_log("Stream: Arquivo de áudio válido adicionado: $filePath");
    } else {
        error_log("Stream: Arquivo de áudio não encontrado: $filePath");
    }
}

if (empty($files)) {
    error_log("Stream: Nenhum arquivo de áudio válido na playlist: $playlistFile");
    file_put_contents(STREAM_STATUS_FILE, 'stopped');
    http_response_code(404);
    exit;
}

set_time_limit(30);
$currentIndex = getCurrentTrackIndex();
$totalFiles = count($files);

while ($currentIndex < $totalFiles) {
    $filePath = $files[$currentIndex];
    $currentTime = date('H:i:s');
    $currentDay = strtolower(date('D'));
    $daysMap = ['mon' => 'seg', 'tue' => 'ter', 'wed' => 'qua', 'thu' => 'qui', 'fri' => 'sex', 'sat' => 'sab', 'sun' => 'dom'];
    $currentDayPt = $daysMap[$currentDay];

    try {
        $stmt = $pdo->prepare("
            SELECT id, nome
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
            error_log("Stream: Nenhuma playlist programada ativa para horário=$currentTime, dia=$currentDayPt. Tentando carregar playlist padrão.");
            $stmt = $pdo->prepare("SELECT id, nome, caminho FROM playlists WHERE nome = 'default' LIMIT 1");
            $stmt->execute();
            $defaultPlaylist = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($defaultPlaylist && file_exists($defaultPlaylist['caminho'])) {
                copy($defaultPlaylist['caminho'], ACTIVE_M3U);
                error_log("Stream: Playlist padrão carregada: ID={$defaultPlaylist['id']}, Nome={$defaultPlaylist['nome']}, Caminho={$defaultPlaylist['caminho']}");
                $lines = file(ACTIVE_M3U, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $files = [];
                foreach ($lines as $line) {
                    if (empty($line) || strpos($line, '#') === 0) {
                        error_log("Stream: Ignorando linha inválida ao recarregar padrão: $line");
                        continue;
                    }
                    $filePath = str_replace(BASE_URL . '/', __DIR__ . '/', $line);
                    if (file_exists($filePath)) {
                        $files[] = $filePath;
                        error_log("Stream: Arquivo de áudio válido adicionado ao recarregar padrão: $filePath");
                    } else {
                        error_log("Stream: Arquivo de áudio não encontrado ao recarregar padrão: $filePath");
                    }
                }
                $currentIndex = 0;
                $totalFiles = count($files);
                setCurrentTrackIndex(0);
                if (empty($files)) {
                    error_log("Stream: Nenhum arquivo de áudio válido na playlist padrão: " . $defaultPlaylist['caminho']);
                    file_put_contents(STREAM_STATUS_FILE, 'stopped');
                    http_response_code(404);
                    exit;
                }
                continue;
            } else {
                error_log("Stream: Nenhuma playlist padrão disponível. Encerrando stream em " . date('Y-m-d H:i:s'));
                file_put_contents(STREAM_STATUS_FILE, 'stopped');
                http_response_code(404);
                exit;
            }
        } else {
            error_log("Stream: Playlist ativa encontrada: ID={$activePlaylist['id']}, Nome={$activePlaylist['nome']}");
        }

        error_log("Stream: Tentando transmitir arquivo: $filePath");
        $handle = @fopen($filePath, 'rb');
        if ($handle) {
            error_log("Stream: Arquivo aberto com sucesso: $filePath");
            while (!feof($handle)) {
                $buffer = fread($handle, 2048);
                echo $buffer;
                flush();
                if (connection_aborted()) {
                    fclose($handle);
                    error_log("Stream: Conexão abortada pelo cliente.");
                    exit;
                }
                usleep(20000);
            }
            fclose($handle);
            error_log("Stream: Arquivo transmitido com sucesso: $filePath");
        } else {
            error_log("Stream: Não foi possível abrir o arquivo: $filePath");
        }

        $currentIndex++;
        setCurrentTrackIndex($currentIndex);

        if ($currentIndex >= $totalFiles) {
            $currentIndex = 0;
            setCurrentTrackIndex(0);
        }

        // Verificar se o índice foi alterado externamente (pular música)
        $newIndex = getCurrentTrackIndex();
        if ($newIndex !== $currentIndex) {
            $currentIndex = $newIndex;
        }
    } catch (Exception $e) {
        error_log("Stream: Erro durante streaming: " . $e->getMessage());
        file_put_contents(STREAM_STATUS_FILE, 'stopped');
        http_response_code(500);
        exit;
    }
}

exit;