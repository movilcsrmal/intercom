<?php
// config.php - admin configuration page
// Protected by password (default admin123). Edit names, IPs, intercom name and room names.
// After saving, redirect back to room (param room) or index.

$root = __DIR__;
$configPath = $root . '/config.txt';
if (!file_exists($configPath)) {
    die("Falta config.txt");
}
$configRaw = file_get_contents($configPath);
$config = json_decode($configRaw, true);
if (!$config) die("config.txt corrupto");

$room = isset($_GET['room']) ? intval($_GET['room']) : 1;
if ($room < 1 || $room > 4) $room = 1;

$err = '';
$ok = '';
$showForm = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $pwd = $_POST['password'] ?? '';
    if (password_verify($pwd, $config['password_hash'])) {
        $showForm = true;
    } else {
        $err = 'Contraseña incorrecta';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    // validate password again from posted 'password' (to protect save)
    $pwd = $_POST['password_confirm'] ?? '';
    if (!password_verify($pwd, $config['password_hash'])) {
        $err = 'Contraseña incorrecta';
        $showForm = true;
    } else {
        // save changes
        $config['intercom_name'] = substr($_POST['intercom_name'] ?? $config['intercom_name'], 0, 50);
        for ($r = 1; $r <= 4; $r++) {
            $config['rooms'][strval($r)]['name'] = substr($_POST['room_name_'.$r] ?? $config['rooms'][strval($r)]['name'],0,50);
            for ($i=0;$i<40;$i++) {
                $nameField = "user_{$r}_{$i}_name";
                $ipField = "user_{$r}_{$i}_ip";
                $newName = substr($_POST[$nameField] ?? $config['rooms'][strval($r)]['users'][$i]['name'],0,50);
                $newIp = trim($_POST[$ipField] ?? $config['rooms'][strval($r)]['users'][$i]['ip']);
                // basic IP validation (allow empty)
                if ($newIp !== '' && !filter_var($newIp, FILTER_VALIDATE_IP)) {
                    $err = "IP inválida: $newIp";
                    $showForm = true;
                    break 2;
                }
                $config['rooms'][strval($r)]['users'][$i]['name'] = $newName;
                $config['rooms'][strval($r)]['users'][$i]['ip'] = $newIp;
            }
        }
        // password change optional
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 4) {
                $err = "La nueva contraseña es demasiado corta";
                $showForm = true;
            } else {
                $config['password_hash'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            }
        }
        if (!$err) {
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            $ok = "Guardado correctamente";
            // reload to reflect changes and go back to room
            header("Location: index.php?room=" . intval($_POST['return_room'] ?? $room));
            exit;
        }
    }
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES); }

?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuración - <?php echo h($config['intercom_name']); ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="topbar">
    <div class="left">
      <img src="logo.svg" alt="logo" class="logo">
      <div class="intercom-name"><?php echo h($config['intercom_name']); ?></div>
    </div>
  </header>

  <main class="config-main">
    <?php if (!$showForm): ?>
      <h2>Acceso configuración</h2>
      <?php if ($err) echo '<div class="error">'.h($err).'</div>'; ?>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <label>Contraseña: <input type="password" name="password" required></label><br>
        <button type="submit">Entrar</button>
      </form>
    <?php else: ?>
      <h2>Configuración</h2>
      <?php if ($err) echo '<div class="error">'.h($err).'</div>'; ?>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="return_room" value="<?php echo $room; ?>">
        <label>Contraseña actual (para confirmar cambios): <input type="password" name="password_confirm" required></label><br>
        <label>Nombre genérico del intercom: <input name="intercom_name" value="<?php echo h($config['intercom_name']); ?>"></label><br>
        <?php for ($r=1;$r<=4;$r++): 
            $rdata = $config['rooms'][strval($r)]; ?>
          <fieldset>
            <legend>Sala <?php echo $r; ?></legend>
            <label>Nombre sala: <input name="room_name_<?php echo $r; ?>" value="<?php echo h($rdata['name']); ?>"></label>
            <div class="users-grid">
            <?php for ($i=0;$i<40;$i++):
                $u = $rdata['users'][$i];
                $iname = "user_{$r}_{$i}_name";
                $iip = "user_{$r}_{$i}_ip";
            ?>
              <div class="user-row">
                <span class="index"><?php echo $i+1; ?></span>
                <input name="<?php echo $iname; ?>" value="<?php echo h($u['name']); ?>" maxlength="7">
                <input name="<?php echo $iip; ?>" value="<?php echo h($u['ip']); ?>" placeholder="IP (ej. 192.168.1.5)">
              </div>
            <?php endfor; ?>
            </div>
          </fieldset>
        <?php endfor; ?>

        <fieldset>
          <legend>Cambiar contraseña</legend>
          <label>Nuevo (dejar vacío para no cambiar): <input name="new_password" type="password"></label>
        </fieldset>

        <div class="form-actions">
          <button type="submit">Guardar</button>
          <a href="index.php?room=<?php echo $room; ?>" class="button">Volver</a>
        </div>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>