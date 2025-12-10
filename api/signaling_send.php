<?php
// signaling_send.php
// Envía un mensaje de señalización a un target IP (guarda en signaling/<target_ip>.queue)
// POST params: target_ip, message (JSON string). El servidor añadirá from=REMOTE_ADDR
$root = dirname(__DIR__);
$signDir = $root . '/signaling';
if (!is_dir($signDir)) mkdir($signDir, 0750, true);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['target_ip']) || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['error'=>'bad_request']);
    exit;
}
$target_ip = $data['target_ip'];
$message = $data['message'];
$message['from'] = $_SERVER['REMOTE_ADDR'];
$message['ts'] = time();

$queueFile = $signDir . '/' . str_replace(':','_',$target_ip) . '.queue';
$lock = fopen($queueFile, 'c+');
if ($lock) {
    flock($lock, LOCK_EX);
    $contents = stream_get_contents($lock);
    $arr = $contents ? json_decode($contents, true) : [];
    if (!is_array($arr)) $arr = [];
    $arr[] = $message;
    ftruncate($lock,0);
    rewind($lock);
    fwrite($lock, json_encode($arr));
    fflush($lock);
    flock($lock, LOCK_UN);
    fclose($lock);
    echo json_encode(['ok'=>true]);
} else {
    http_response_code(500);
    echo json_encode(['error'=>'file_error']);
}