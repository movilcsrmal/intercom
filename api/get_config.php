<?php
// get_config.php - devuelve config.json (solo lectura)
// Uso por JS: fetch('api/get_config.php')
$root = dirname(__DIR__);
$configPath = $root . '/config.txt';
header('Content-Type: application/json; charset=utf-8');
if (!file_exists($configPath)) {
  echo json_encode(['error'=>'missing_config']);
  exit;
}
echo file_get_contents($configPath);