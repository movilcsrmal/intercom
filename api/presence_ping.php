<?php
// presence_ping.php
// Actualiza fichero presence/<room>_<ip>.json con timestamp y username
// POST params: room, ip (opcional — lo tomamos de REMOTE_ADDR), name (opcional)
$root = dirname(__DIR__);
$presenceDir = $root . '/presence';
if (!is_dir($presenceDir)) mkdir($presenceDir, 0750, true);

$room = isset($_POST['room']) ? intval($_POST['room']) : (isset($_GET['room'])?intval($_GET['room']):1);
$ip = $_SERVER['REMOTE_ADDR'];
$name = $_POST['name'] ?? '';

$data = ['ip'=>$ip,'room'=>$room,'name'=>$name,'ts'=>time()];
$file = $presenceDir . "/room{$room}_" . str_replace(':','_', $ip) . '.json';
file_put_contents($file, json_encode($data));
echo json_encode(['ok'=>true]);