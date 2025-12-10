<?php
// presence_list.php
// Devuelve lista de presencia para la sala solicitada
$root = dirname(__DIR__);
$presenceDir = $root . '/presence';
header('Content-Type: application/json; charset=utf-8');
$room = isset($_GET['room']) ? intval($_GET['room']) : 1;
$list = [];
if (is_dir($presenceDir)) {
    foreach (glob($presenceDir . "/room{$room}_*.json") as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d) $list[] = $d;
    }
}
echo json_encode($list);