# foxidna-rpi5-ms

Servidor multimedia experimental desarrollado sobre Raspberry Pi 5.

## Estado del proyecto

Proyecto en desarrollo.

La primera etapa consiste en implementar y evaluar un servidor de
streaming multimedia utilizando Docker, Nginx y FFmpeg.

Actualmente existe un prototipo funcional de streaming HLS.

## Plataforma

- Raspberry Pi 5
- Linux
- Docker
- Nginx
- FFmpeg
- HLS

## Acceso actual

El prototipo puede consultarse mediante:

    http://<IP_DE_LA_RASPBERRY>:8080/index.html

## Versionado

El proyecto utiliza Git para el control de versiones.

La rama `main` representa la línea principal de desarrollo.

Las versiones experimentales y nuevas funcionalidades podrán
desarrollarse mediante ramas independientes.

## Estado actual de HLS

El prototipo genera tres variantes de video:

- v0
- v1
- v2

El contenido HLS se encuentra desplegado actualmente en:

    /var/www/html/hls/video1/

Los archivos multimedia generados no forman parte del repositorio.
