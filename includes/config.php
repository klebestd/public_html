<?php
// Configurações do banco de dados
$dbHost = 'localhost';
$dbName = 'zfgwmhfz_natura';
$dbUser = 'zfgwmhfz_natura';
$dbPass = '122862asd';

// Configurações gerais
define('BASE_URL', 'https://webradiogratis.x10.mx');
define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('MUSICAS_DIR', UPLOADS_DIR . 'musicas/');
define('COMERCIAIS_DIR', UPLOADS_DIR . 'comerciais/');
define('PLAYLISTS_DIR', __DIR__ . '/../playlists/');
define('ACTIVE_M3U', PLAYLISTS_DIR . 'active.m3u');
define('STREAM_STATUS_FILE', __DIR__ . '/../stream_status.txt');
define('CURRENT_TRACK_FILE', __DIR__ . '/../current_track.txt');

// Configurações do PHP
ini_set('max_execution_time', 300); // Aumentado para evitar timeout
ini_set('memory_limit', '256M');
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('error_log', __DIR__ . '/../error_log.txt');
ini_set('log_errors', 1);
ini_set('output_buffering', 'off');

// Configurações de cache
define('CACHE_ENABLED', extension_loaded('apcu') && apcu_enabled());
define('CACHE_TTL', 300); // 5 minutos