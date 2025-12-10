<?php
// signaling_receive.php
// Devuelve y borra los mensajes destinados a REMOTE_ADDR
$root = dirname(__DIR__);
$signDir = $root . '/signaling';
header('Content-Type: application/json; charset=utf-8');
$ip = $_SERVER['REMOTE_ADDR'];
$queueFile = $signDir . '/' . str_replace(':','_',$ip) . '.queue';
$arr = [];
if (file_exists($queueFile)) {
    $lock = fopen($queueFile, 'c+');
    if ($lock) {
        flock($lock, LOCK_EX);
        $contents = stream_get_contents($lock);
        $arr = $contents ? json_decode($contents, true) : [];
        // clear file
        ftruncate($lock,0);
        fflush($lock);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
echo json_encode($arr);