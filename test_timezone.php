<?php
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/includes/db.php';
echo "Fuso horário do PHP: " . date_default_timezone_get() . "<br>";
echo "Horário atual do PHP: " . date('Y-m-d H:i:s') . "<br>";
$stmt = $pdo->query("SELECT @@global.time_zone, @@session.time_zone, NOW() AS now");
$tz = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Fuso horário global do MySQL: " . $tz['@@global.time_zone'] . "<br>";
echo "Fuso horário da sessão MySQL: " . $tz['@@session.time_zone'] . "<br>";
echo "Horário atual do MySQL (NOW()): " . $tz['now'] . "<br>";
?>