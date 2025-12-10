<?php
// cleanup_presence.php
// Borra ficheros de presence con ts > 30s (timeout configurable)
// Se recomienda ejecutar con cron cada 30s-1m
$timeout = 30; // segundos
$root = dirname(__DIR__);
$presenceDir = $root . '/presence';
if (!is_dir($presenceDir)) exit;
foreach (glob($presenceDir . "/*.json") as $f) {
    $d = json_decode(file_get_contents($f), true);
    if (!$d || !isset($d['ts']) || (time() - $d['ts']) > $timeout) {
        @unlink($f);
    }
}