<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('America/Sao_Paulo');

$currentTime = date('H:i:s');
$currentDay = strtolower(date('D'));
$daysMap = ['mon' => 'seg', 'tue' => 'ter', 'wed' => 'qua', 'thu' => 'qui', 'fri' => 'sex', 'sat' => 'sab', 'sun' => 'dom'];
$currentDayPt = $daysMap[$currentDay];

error_log("Scheduler: Executando em " . date('Y-m-d H:i:s') . ", dia: $currentDayPt, horário: $currentTime");

// Buscar playlist programada ativa
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

if ($activePlaylist && file_exists($activePlaylist['caminho'])) {
    copy($activePlaylist['caminho'], ACTIVE_M3U);
    file_put_contents(STREAM_STATUS_FILE, 'playing');
    error_log("Scheduler: Playlist ativa carregada: ID={$activePlaylist['id']}, Nome={$activePlaylist['nome']}, Caminho={$activePlaylist['caminho']}");
} else {
    // Logar por que nenhuma playlist foi encontrada
    error_log("Scheduler: Nenhuma playlist programada encontrada para horário=$currentTime, dia=$currentDayPt");
    // Carregar playlist padrão
    $stmt = $pdo->prepare("SELECT id, nome, caminho FROM playlists WHERE nome = 'default' LIMIT 1");
    $stmt->execute();
    $defaultPlaylist = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($defaultPlaylist && file_exists($defaultPlaylist['caminho'])) {
        copy($defaultPlaylist['caminho'], ACTIVE_M3U);
        file_put_contents(STREAM_STATUS_FILE, 'playing');
        error_log("Scheduler: Playlist padrão carregada: ID={$defaultPlaylist['id']}, Nome={$defaultPlaylist['nome']}, Caminho={$defaultPlaylist['caminho']}");
    } else {
        file_put_contents(ACTIVE_M3U, '');
        file_put_contents(STREAM_STATUS_FILE, 'stopped');
        error_log("Scheduler: Nenhuma playlist disponível (padrão não encontrada). Streaming parado.");
    }
}
?>