<?php
// index.php - main intercom page
// Ruta: /var/www/html/intercom/index.php
// Muestra la sala (room param) o sala 1 por defecto. Comprueba acceso por IP y sala.
// Si no autorizado: "Acceso denegado"

$root = __DIR__;
$configPath = $root . '/config.txt';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo "Falta config.txt";
    exit;
}
$configRaw = file_get_contents($configPath);
$config = json_decode($configRaw, true);
if (!$config) {
    http_response_code(500);
    echo "config.txt corrupto";
    exit;
}

$room = isset($_GET['room']) ? intval($_GET['room']) : 1;
if ($room < 1 || $room > 4) $room = 1;

$client_ip = $_SERVER['REMOTE_ADDR'];

// Check access: user must have his IP recorded in that room's users
$allowed = false;
$myUserIndex = -1;
$roomData = $config['rooms'][strval($room)];
foreach ($roomData['users'] as $i => $u) {
    if (isset($u['ip']) && $u['ip'] !== '' && $u['ip'] === $client_ip) {
        $allowed = true;
        $myUserIndex = $i;
        break;
    }
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES); }

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo h($config['intercom_name']); ?> - <?php echo h($roomData['name']); ?></title>
  <link rel="icon" type="image/png" href="icono.png">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="topbar">
    <div class="left">
      <img src="logo.svg" alt="logo" class="logo">
      <div class="intercom-name"><?php echo h($config['intercom_name']); ?></div>
    </div>
    <div class="right">
      <button id="speakerBtn" title="Configuración de audio"><img src="altavoz.png" alt="altavoz"></button>
      <a href="config.php?room=<?php echo $room; ?>" class="config-link" title="Configuración"><img src="conf.png" alt="config"></a>
    </div>
  </header>

  <?php if (!$allowed): ?>
    <main class="centered">
      <h2>Acceso denegado</h2>
      <p>Tu equipo (IP <?php echo h($client_ip); ?>) no está registrado para <?php echo h($roomData['name']); ?>.</p>
      <p>Pide al administrador que registre tu IP o usa la página de configuración.</p>
    </main>
  <?php else: ?>
  <main>
    <div class="controls">
      <div class="room-links">
        <a href="?room=1"<?php if($room==1) echo ' class="active"'; ?>>Sala 1</a>
        <a href="?room=2"<?php if($room==2) echo ' class="active"'; ?>>Sala 2</a>
        <a href="?room=3"<?php if($room==3) echo ' class="active"'; ?>>Sala 3</a>
        <a href="?room=4"<?php if($room==4) echo ' class="active"'; ?>>Sala 4</a>
      </div>
      <div class="yourinfo">Conectado como: <strong><?php echo h($roomData['users'][$myUserIndex]['name']); ?></strong></div>
    </div>

    <div id="grid" class="grid" data-room="<?php echo $room; ?>" data-myip="<?php echo $client_ip; ?>">
      <?php
        // render 40 buttons
        for ($i=0;$i<40;$i++) {
          $user = $roomData['users'][$i];
          $displayName = $user['name'];
          if (mb_strlen($displayName) > 7) $displayName = mb_substr($displayName,0,7);
          $hasIp = isset($user['ip']) && $user['ip'] !== '';
          $btnClass = $hasIp ? 'btn-offline' : 'btn-empty';
          echo '<button class="grid-btn '.$btnClass.'" data-index="'.$i.'" data-ip="'.h($user['ip']).'"><span class="btn-name">'.h($displayName).'</span></button>';
        }
      ?>
    </div>
  </main>

  <div id="modal-audio" class="modal hidden">
    <div class="modal-content">
      <h3>Configuración de audio</h3>
      <label>Salida: <select id="audioOutput"></select></label><br>
      <label>Entrada: <select id="audioInput"></select></label><br>
      <div class="modal-actions">
        <button id="saveAudio">Guardar</button>
        <button id="closeAudio">Cerrar</button>
      </div>
    </div>
  </div>

  <audio id="remoteAudio" autoplay></audio>

  <script>
    window.APP = {
      myIp: "<?php echo $client_ip; ?>",
      room: <?php echo $room; ?>,
      myIndex: <?php echo $myUserIndex; ?>,
      serverBase: "api"
    };
  </script>
  <script src="assets/app.js"></script>
  <?php endif; ?>

</body>
</html>