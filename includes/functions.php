<?php
date_default_timezone_set('America/Sao_Paulo');
// ... restante do código ...
require_once __DIR__ . '/config.php';

function isValidAudioFile($file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    return in_array($ext, ['mp3', 'wav']);
}

function cleanFilePath($path, $tipo) {
    $baseDir = $tipo === 'musica' ? MUSICAS_DIR : COMERCIAIS_DIR;
    return $baseDir . basename($path);
}

function getActivePlaylist($pdo) {
    $cacheKey = 'active_playlist';
    if (CACHE_ENABLED && function_exists('apcu_exists') && apcu_exists($cacheKey)) {
        return apcu_fetch($cacheKey);
    }

    $currentTime = date('H:i:s');
    $currentDay = strtolower(date('D'));
    $daysMap = [
        'mon' => 'seg', 'tue' => 'ter', 'wed' => 'qua', 'thu' => 'qui',
        'fri' => 'sex', 'sat' => 'sab', 'sun' => 'dom'
    ];
    $currentDayPt = $daysMap[$currentDay];

    try {
        $stmt = $pdo->prepare("SELECT * FROM playlists WHERE horario_inicio <= ? AND horario_fim >= ? AND FIND_IN_SET(?, dias_semana) LIMIT 1");
        $stmt->execute([$currentTime, $currentTime, $currentDayPt]);
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);

        if (CACHE_ENABLED && function_exists('apcu_store') && $playlist) {
            apcu_store($cacheKey, $playlist, CACHE_TTL);
        }
        return $playlist;
    } catch (PDOException $e) {
        error_log("Erro em getActivePlaylist: " . $e->getMessage());
        return null;
    }
}

function ensureDirectories() {
    foreach ([UPLOADS_DIR, MUSICAS_DIR, COMERCIAIS_DIR, PLAYLISTS_DIR] as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Erro: Não foi possível criar o diretório '$dir'");
                die("Erro: Não foi possível criar o diretório '$dir'.");
            }
        }
        if (!is_writable($dir)) {
            error_log("Erro: O diretório '$dir' não tem permissão de escrita");
            die("Erro: O diretório '$dir' não tem permissão de escrita.");
        }
    }
}

function getCurrentTrackIndex() {
    return (int)file_get_contents(CURRENT_TRACK_FILE) ?: 0;
}

function setCurrentTrackIndex($index) {
    file_put_contents(CURRENT_TRACK_FILE, $index);
}

function cacheMusicas($pdo) {
    $cacheKey = 'musicas_list';
    if (CACHE_ENABLED && function_exists('apcu_exists') && apcu_exists($cacheKey)) {
        $musicas = apcu_fetch($cacheKey);
        error_log("Músicas carregadas do cache: " . count($musicas) . " registros");
        return $musicas;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nome, caminho, tipo, data_upload FROM arquivos WHERE tipo = 'musica' ORDER BY data_upload DESC LIMIT 100");
        $stmt->execute();
        $musicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Músicas carregadas do banco: " . count($musicas) . " registros");

        if (CACHE_ENABLED && function_exists('apcu_store')) {
            apcu_store($cacheKey, $musicas, CACHE_TTL);
        }
        return $musicas;
    } catch (PDOException $e) {
        error_log("Erro em cacheMusicas: " . $e->getMessage());
        return [];
    }
}

function cacheComerciais($pdo) {
    $cacheKey = 'comerciais_list';
    if (CACHE_ENABLED && function_exists('apcu_exists') && apcu_exists($cacheKey)) {
        $comerciais = apcu_fetch($cacheKey);
        error_log("Comerciais carregados do cache: " . count($comerciais) . " registros");
        return $comerciais;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nome, caminho, tipo, data_upload FROM arquivos WHERE tipo = 'comercial' ORDER BY data_upload DESC LIMIT 50");
        $stmt->execute();
        $comerciais = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Comerciais carregados do banco: " . count($comerciais) . " registros");

        if (CACHE_ENABLED && function_exists('apcu_store')) {
            apcu_store($cacheKey, $comerciais, CACHE_TTL);
        }
        return $comerciais;
    } catch (PDOException $e) {
        error_log("Erro em cacheComerciais: " . $e->getMessage());
        return [];
    }
}

function cleanupOldData($pdo) {
    try {
        // Excluir playlists com mais de 30 dias
        $stmt = $pdo->prepare("SELECT id, caminho FROM playlists WHERE data_criacao < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $oldPlaylists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($oldPlaylists as $playlist) {
            if (file_exists($playlist['caminho'])) {
                unlink($playlist['caminho']);
            }
            $pdo->prepare("DELETE FROM playlist_arquivos WHERE playlist_id = ?")->execute([$playlist['id']]);
            $pdo->prepare("DELETE FROM playlists WHERE id = ?")->execute([$playlist['id']]);
        }

        // Excluir arquivos órfãos
        $pdo->prepare("DELETE FROM arquivos WHERE id NOT IN (SELECT arquivo_id FROM playlist_arquivos) AND tipo = 'musica'")->execute();

        if (CACHE_ENABLED && function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
            error_log("Cache APCu limpo após limpeza de dados.");
        }
        error_log("Limpeza de dados antigos realizada com sucesso.");
    } catch (PDOException $e) {
        error_log("Erro na limpeza de dados: " . $e->getMessage());
    }
}