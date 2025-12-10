# Intercom en red local

Resumen
-------
Intercom local basado en PHP + WebRTC. Interfaz web para 4 salas (cada sala 40 botones). Acceso controlado por IP de cliente según `config.txt`. Señalización y presencia mediante archivos en el servidor (no hay base de datos). WebRTC P2P para audio.

Estructura de ficheros
----------------------
/var/www/html/intercom/
- config.txt                (archivo JSON con configuración)
- index.php                 (página principal / sala)
- config.php                (página de configuración protegida por contraseña)
- api/
  - get_config.php
  - presence_ping.php
  - presence_list.php
  - signaling_send.php
  - signaling_receive.php
  - cleanup_presence.php
- assets/
  - style.css
  - app.js
- presence/                 (directorio donde se crean ficheros de presencia)
- signaling/                (directorio donde se crean las colas de señalización)
- logo.svg                  (AÑADIR MANUALMENTE)
- icono.png                 (AÑADIR MANUALMENTE 64x64)
- altavoz.png               (AÑADIR MANUALMENTE)
- conf.png                  (AÑADIR MANUALMENTE)

Nota: logo.svg, icono.png, altavoz.png, conf.png deben añadirse manualmente (subir a la raíz del proyecto).

Requisitos en el servidor (Ubuntu 24.04)
---------------------------------------
- Apache2 instalado (mod_php o php-fpm funcionarán).
- PHP >= 8.0 con ext-json habilitado (normalmente por defecto).
- No se necesitan bases de datos.

Instalación de dependencias (comandos)
-------------------------------------
Ejemplo de comandos para instalar lo necesario en Ubuntu 24.04:

sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-cli php-common php-json
# si usas php-fpm:
# sudo apt install -y php-fpm

Configura Apache para servir `/var/www/html` (por defecto en Ubuntu), y coloca el proyecto en `/var/www/html/intercom`.

Permisos recomendados
---------------------
Asumiendo que copias los ficheros a /var/www/html/intercom:

sudo chown -R www-data:www-data /var/www/html/intercom
# Permisos mínimos
sudo find /var/www/html/intercom -type d -exec chmod 750 {} \;
sudo find /var/www/html/intercom -type f -exec chmod 640 {} \;
# Asegura que PHP pueda crear/editar presence/ y signaling/
sudo mkdir -p /var/www/html/intercom/presence /var/www/html/intercom/signaling
sudo chown -R www-data:www-data /var/www/html/intercom/presence /var/www/html/intercom/signaling
sudo chmod 770 /var/www/html/intercom/presence /var/www/html/intercom/signaling

Ajustes Apache/PHP
------------------
- Si usas mod_php (libapache2-mod-php), no hace falta cambiar mucho.
- Si usas php-fpm y nginx frontal, asegúrate que Apache proxy-pasa correctamente a php-fpm.
- Asegúrate de que `upload_max_filesize`, `post_max_size` en php.ini sean suficientes (por defecto son suficientes).
- Si usas HTTPS con certificado local (IP fija), añade el VirtualHost con el certificado. (Tu setup actual ya tiene SSL local; solo asegúrate de que Apache esté escuchando en 443 y el DocumentRoot contenga este proyecto).

Cron para limpiar presencia
---------------------------
Se recomienda añadir un cron job para ejecutar cleanup_presence.php cada minuto:

* * * * * /usr/bin/php /var/www/html/intercom/api/cleanup_presence.php

Flujo de funcionamiento
-----------------------
- El fichero `config.txt` contiene la configuración (contraseña en hash, nombre del intercom, nombres de sala, 40 usuarios por sala con su IP).
- Cuando un usuario abre `https://ip_servidor/intercom/` (o `index.php?room=N`) el servidor comprueba la IP del cliente y la sala solicitada. Si la IP está registrada en esa sala se permite el acceso; si no, muestra "Acceso denegado".
- Los usuarios registrados aparecen como botones; si su IP está presente (ping periódico) el botón quedará verde.
- Al pulsar sobre el botón de otro usuario se inicia una llamada P2P:
  - El que llama crea un RTCPeerConnection, añade su audio local y envía un `offer` al target vía señalización (server archivos).
  - El receptor crea un `answer` automáticamente (recibir audio) y lo envía de vuelta.
  - Si el receptor quiere enviar audio de vuelta, pulsa su propio botón: se obtiene su micrófono y se realiza una renegociación (reneg-offer/reneg-answer) para añadir su track.
- Indicadores:
  - Verde: en línea
  - Rojo: durante llamada
  - Gris: usuario no registrado (sin IP)

Parámetros de audio por sala
----------------------------
- Salas 1-3: OPUS, mono, 128 kbps, 48 kHz
- Sala 4: OPUS, estéreo, 256 kbps, 48 kHz

La librería JS intenta ajustar el SDP para el codec OPUS y los parámetros indicados.

Registro/Edición de usuarios
----------------------------
- Edición por la página `config.php` (contraseña por defecto `admin123`). Para confirmar cambios se solicita la contraseña actual.
- Cambiar ips/nombres/salas se aplica inmediatamente al guardar.

Seguridad y recomendaciones
---------------------------
- El sistema confía en la IP del cliente (LAN cableada). Asegúrate de que la red es de confianza.
- Protege el acceso físico a la red si es requerimiento de seguridad.
- No expongas el servidor a Internet sin revisar seguridad y autenticación.

Puntos manuales a completar
---------------------------
- Copia `logo.svg`, `icono.png` (64x64), `altavoz.png` y `conf.png` a la raíz del proyecto.
- Comprueba que el certificado SSL local funcione para `https://ip_servidor/`. Es normal que navegadores avisen por certificado auto-firmado; acepta la excepción en cada equipo.

Notas técnicas y limitaciones
-----------------------------
- Señalización y presencia: se usan ficheros en `signaling/` y `presence/` con bloqueo simple (flock). En LAN de pocos usuarios esto es suficiente.
- No hay almacenamiento histórico (sin DB) como solicitaste.
- La implementación de renegociación para que el receptor elija cuando enviar audio se realiza con intercambio adicional `reneg-offer` / `reneg-answer`.
- El código usa polling para señalización (cada 1s). Para mayor eficiencia se podría implementar WebSocket más adelante.
- SDP munging: se aplica un ajuste básico para OPUS; puede necesitar ajustes si algún navegador actúa distinto.

Pruebas recomendadas
--------------------
1) Copiar los ficheros y fijar permisos (ver arriba).
2) Añadir IP de uno de los equipos al config.txt (o via config.php) para sala 1.
3) Abrir https://ip_servidor/intercom/ desde ese equipo y desde otro con su IP registrada en la misma sala.
4) Probar llamada, respuesta y envío de audio de vuelta.
5) Revisar /var/www/html/intercom/presence y /var/www/html/intercom/signaling para diagnosticar.

Si quieres que genere un script bash para desplegar automáticamente estos ficheros y fijar permisos en Ubuntu 24.04, o que te entregue un .zip con todo listo para copiar, lo preparo y te lo adjunto. Si persiste el problema de visualización dime exactamente cómo lo ves (error, pantalla en blanco, código truncado) y lo corrijo de inmediato.