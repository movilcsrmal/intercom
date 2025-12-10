<?php
// save_config.php - interfaz mínima para guardado remoto si fuese necesario (no usada por defecto).
// NO implementado por seguridad (uso config.php). Responde 403.
http_response_code(403);
echo json_encode(['error'=>'use_config_php']);