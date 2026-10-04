# foxidna-rpi5-ms

Servidor multimedia experimental sobre linux.

Este repositorio le sirve como plantilla para montar su propio
servidor de streaming de pruebas en linux, usando
Docker, Nginx y FFmpeg. Aquí encontrará las configuraciones, los
scripts de despliegue y la documentación necesaria para replicar el
entorno y publicar flujos HLS.

Tenga en cuenta que el servidor corre en modo *headless*: usted
accede a él por SSH y no hay entorno gráfico instalado. Toda la
operación se realiza desde la terminal.

Todo el software que se utiliza aquí es libre. Las licencias de los
componentes de terceros están documentadas en la carpeta `licenses/`
y en el archivo `LICENCE` del repositorio.

## Objetivo del repositorio

Este repositorio fue creado para que una actualización no rompa a
otra. Si alguna versión del proyecto deja de funcionar como se
espera, usted puede volver a una versión anterior apoyándose en el
historial de Git y en las etiquetas de versión del repositorio.

Además, si lo necesita, puede levantar más contenedores tomando este
repositorio como plantilla, sin tener que reconstruir la
configuración desde cero. De esta forma, el repositorio actúa como
respaldo reproducible del estado funcional del servidor y como base
para desplegar nuevas instancias en paralelo.

## Estructura del repositorio

El repositorio se organiza de la siguiente manera:

    .
    ├── docker/
    │   ├── Dockerfile
    │   └── nginx/
    │       └── default.conf
    ├── docs/
    │   ├── architecture.md
    │   └── how-to-use-script_to_upload_new_media.sh
    ├── LICENCE
    ├── README.md
    ├── scripts/
    │   ├── foxidna-entrypoint.sh
    │   └── script_to_upload_new_media.sh
    └── web/
        ├── api/
        ├── assets/
        ├── components/
        ├── index.html
        ├── index.nginx-debian.html
        └── player.html

- `docker/`: contiene el `Dockerfile` y la configuración de Nginx
  (`default.conf`) que definen el servidor multimedia.
- `docs/`: documentación extendida del proyecto, incluyendo la
  arquitectura y las guías de uso de los scripts.
- `scripts/`: scripts de entrada y de carga de contenido nuevo hacia
  el servidor.
- `web/`: raíz del sitio que sirve Nginx, con la interfaz del
  reproductor, los estilos, los recursos y la API en PHP.

Los archivos multimedia generados (segmentos y listas HLS) no forman
parte del repositorio.

## Instalación y despliegue

Esta sección describe, a nivel conceptual, cómo se pone en marcha el
servidor. Los detalles exactos de cada paso están comentados en el
`Dockerfile`, en `docker/nginx/default.conf` y en los scripts de la
carpeta `scripts/`.

### Requisitos previos

Antes de comenzar, usted debe contar con:

- Una Raspberry Pi 5 con Linux instalado.
- Acceso por SSH a la Raspberry Pi. El servidor corre en modo
  *headless*, sin entorno gráfico.
- Docker y Git instalados en la Raspberry Pi.
- FFmpeg disponible en el equipo desde el cual transcodificará el
  contenido nuevo (no en la Raspberry Pi, a menos que lo haga desde
  ahí).

### Consideraciones generales

El servidor se compone de dos procesos que corren dentro del mismo
contenedor:

- **Nginx**: sirve el sitio, los archivos estáticos y los flujos HLS.
- **PHP-FPM 8.4**: atiende los endpoints de la carpeta `api/`, que
  exponen el catálogo y los metadatos técnicos de cada video.

Ambos procesos se levantan y se detienen en conjunto mediante el
script `scripts/foxidna-entrypoint.sh`, que actúa como supervisor
dentro del contenedor.

El contenido HLS vive fuera del contenedor, en el sistema de archivos
de la Raspberry Pi. El contenedor lo ve a través de un volumen
montado. Por eso, cuando usted publica un video nuevo, no necesita
reconstruir la imagen ni reiniciar el contenedor: basta con colocar
los archivos en la ruta compartida.

### Pasos generales

El despliegue sigue este orden:

1. **Preparación del entorno.** Usted accede a la Raspberry Pi 5 por
   SSH y verifica que Docker y Git estén instalados.

2. **Clonado del repositorio.** Usted clona este repositorio en la
   Raspberry Pi y se ubica en la rama `main`, que representa la
   línea principal de desarrollo.

3. **Construcción de la imagen.** A partir del `Dockerfile` se
   construye la imagen del servidor. En esta etapa se instalan
   Nginx, PHP 8.4, FFmpeg y el punto de entrada del contenedor.

4. **Configuración de Nginx.** El archivo `docker/nginx/default.conf`
   define las rutas del sitio, los tipos MIME de HLS y el paso de
   PHP a PHP-FPM. Usted ajusta este archivo si cambia el puerto o
   la ubicación del contenido.

5. **Levantamiento del contenedor.** El contenedor arranca con el
   `ENTRYPOINT` definido en la imagen, que pone en marcha Nginx y
   PHP-FPM en paralelo y se encarga de detenerlos ordenadamente al
   apagar el contenedor.

6. **Publicación del contenido.** Usted coloca los flujos HLS en la
   ruta compartida con el contenedor (por ejemplo,
   `/var/www/html/hls/`). Cada video debe estar en su propio
   directorio, con un `master.m3u8` en la raíz y las variantes
   `v0`, `v1` y `v2`.

7. **Verificación.** Desde otro equipo de la misma red, usted abre
   el navegador en `http://<IP_DE_LA_RASPBERRY>:8080/index.html` y
   confirma que el catálogo carga y que el reproductor funciona. En
   `player.html` puede además verificar las métricas de resolución,
   bitrate y velocidad de descarga.

### Después del despliegue

Si en algún momento una actualización rompe el funcionamiento del
servidor, usted puede volver a una versión anterior del repositorio
y reconstruir el contenedor a partir de ese estado. Ese es
justamente el objetivo del versionado descrito más arriba.

## Cómo subir nuevo contenido

Para publicar un video nuevo en el servidor, usted utiliza el script
`scripts/script_to_upload_new_media.sh`. Este script toma un archivo
de video local, lo transcodifica a HLS con tres variantes de
resolución y lo coloca en la ruta que Nginx sirve.

### Antes de ejecutar el script

Verifique lo siguiente:

- El equipo donde ejecutará el script debe tener `ffmpeg` y `ffprobe`
  disponibles en el `PATH`. El script aborta si no los encuentra.
- El archivo de video debe tener un nombre **sin espacios**. El
  nombre del archivo se convierte en el identificador del video y en
  la ruta pública bajo `/hls/`. Si contiene espacios, el script se
  detiene y le pide renombrarlo antes de continuar.
- La fuente debe estar en 30 o 60 fps. El script está pensado para
  esas tasas. Si detecta otra, emite una advertencia pero continúa.
- No debe existir ya un directorio con el mismo identificador ni en
  el entorno local ni en el servidor. El script no sobrescribe
  contenido previo, para evitar pérdidas accidentales.

### Qué hace el script

El script ejecuta el siguiente flujo:

1. Valida los argumentos y las dependencias.
2. Calcula el identificador del video a partir del nombre del
   archivo.
3. Detecta la tasa de cuadros del origen y calcula el tamaño del GOP
   (6 segundos de video, alineado con la duración de cada segmento
   HLS).
4. Transcodifica el video a tres variantes en una sola ejecución de
   FFmpeg:

   - `v0`: 2560 × 1440
   - `v1`: 1920 × 1080
   - `v2`: 1280 × 720

   El manifiesto `master.m3u8` se genera automáticamente en la misma
   pasada.
5. Valida que cada variante tenga su playlist y al menos un segmento
   `.ts`, y que el `master.m3u8` exista.
6. Publica el resultado en el directorio compartido con el
   contenedor (por defecto, `/var/www/html/hls/<id>/`).
7. Si el contenedor `foxidna` está corriendo, verifica desde dentro
   que el `master.m3u8` sea visible y lista los manifiestos
   publicados.

### Estructura generada

Después de ejecutar el script, el servidor queda con esta estructura
para el video nuevo:

    /hls/<id>/
    ├── master.m3u8
    ├── v0/
    │   ├── prog_index.m3u8
    │   └── segment_*.ts
    ├── v1/
    │   ├── prog_index.m3u8
    │   └── segment_*.ts
    └── v2/
        ├── prog_index.m3u8
        └── segment_*.ts

El catálogo del servidor detecta el video automáticamente en la
próxima consulta. No es necesario reiniciar el contenedor ni
reconstruir la imagen.

### Lo que el script no hace

El script **no** modifica `index.html` ni crea una página HTML
específica para el video. La interfaz del catálogo se construye de
forma dinámica desde `api/scanner.php`, así que agregar contenido no
requiere tocar el HTML. Esta separación es deliberada: evita
introducir cambios accidentales en la interfaz al publicar videos
nuevos.

### Guía extendida

La guía detallada de uso, con la descripción de cada validación y
los mensajes que puede mostrar el script, está en:

    docs/how-to-use-script_to_upload_new_media.sh

## Documentación adicional

En la carpeta `docs/` usted encuentra la documentación extendida del
proyecto:

- `architecture.md`: descripción de la arquitectura del servidor,
  los componentes que lo integran y el flujo de datos entre el
  cliente y el equipo.
- `how-to-use-script_to_upload_new_media.sh`: guía de uso del script
  de carga de contenido nuevo, con la descripción de cada validación
  y de los mensajes que puede mostrar durante su ejecución.

Además, el código y los archivos de configuración incluyen
comentarios explicativos que detallan su funcionamiento. Se
recomienda revisarlos antes de modificar cualquier componente. Los
archivos con comentarios son, entre otros:

- `docker/Dockerfile` y `docker/nginx/default.conf`: construcción de
  la imagen y configuración de Nginx.
- `scripts/foxidna-entrypoint.sh` y
  `scripts/script_to_upload_new_media.sh`: arranque del contenedor y
  publicación de contenido nuevo.
- `web/api/`: endpoints y bibliotecas del catálogo y de los
  metadatos.
- `web/assets/js/foxidna/` y `web/assets/css/`: lógica de la
  interfaz y estilos de las vistas del catálogo y del reproductor.
## Licencias y créditos

Este proyecto se desarrolla con preferencia por el software libre.
La licencia principal del repositorio se encuentra en el archivo
`LICENCE`, en la raíz del proyecto.

### Componentes de terceros

La interfaz web utiliza bibliotecas y recursos de terceros. Las
licencias de cada uno están documentadas en la carpeta `licenses/`,
organizadas por componente:

- `animejs/`: Anime.js, biblioteca de animaciones.
- `firacode/`: Fira Code, tipografía monoespaciada.
- `hlsjs/`: hls.js, motor de reproducción HLS.
- `hover-css/`: Hover.css, efectos de interacción.
- `inter/`: Inter, tipografía de interfaz.
- `material-tailwind/`: Material Tailwind, componentes visuales.

Cada subcarpeta contiene el texto de la licencia correspondiente.
Se recomienda revisarlas antes de reutilizar cualquier componente en
un proyecto distinto.

### Uso como plantilla

Si usted reutiliza este repositorio como plantilla, conserve los
archivos de licencia correspondientes y respete las condiciones de
cada componente. La licencia del proyecto no sustituye ni modifica
las licencias de los componentes de terceros que se distribuyen con
él.

### Créditos

El código propio del proyecto, la documentación y los scripts de
despliegue fueron desarrollados como parte de las prácticas de
laboratorio descritas en este repositorio. Los componentes de
terceros mantienen la autoría original indicada en sus respectivas
licencias.

## Estado del proyecto

Proyecto en desarrollo.

La primera etapa consiste en implementar y evaluar un servidor de
streaming multimedia utilizando Docker, Nginx y FFmpeg. Actualmente
existe un prototipo funcional de streaming HLS que publica tres
variantes de video por contenido:

- `v0`: 2560 × 1440
- `v1`: 1920 × 1080
- `v2`: 1280 × 720

El prototipo incluye catálogo dinámico, reproductor con selección de
calidad y un panel de métricas que muestra resolución activa, tasa de
bits, velocidad de descarga, duración, códec y representaciones
disponibles.

## Versionado y contribuciones

El proyecto utiliza Git para el control de versiones. La rama `main`
representa la línea principal de desarrollo. Las versiones
experimentales y las nuevas funcionalidades pueden desarrollarse en
ramas independientes.

Si usted desea contribuir, se recomienda:

1. Trabajar en una rama aparte, con un nombre que describa el cambio.
2. Documentar los cambios realizados, tanto en el código como en los
   archivos de configuración afectados.
3. Verificar que el servidor siga funcionando antes de integrar los
   cambios a `main`. Esto incluye probar el catálogo, el reproductor
   y los endpoints de la carpeta `api/`.
4. Integrar los cambios solo cuando el funcionamiento esté
   confirmado.

De esta manera se preserva la posibilidad de volver a una versión
estable en cualquier momento. Si una actualización rompe el
funcionamiento del servidor, usted puede regresar a una versión
anterior del repositorio y reconstruir el contenedor a partir de ese
estado.

## Referencias

Documentación oficial de las tecnologías utilizadas en el proyecto:

### Infraestructura y servidor

- Docker: https://docs.docker.com/
- Nginx: https://nginx.org/en/docs/
- PHP: https://www.php.net/docs.php
- FFmpeg: https://ffmpeg.org/documentation.html

### Streaming

- Especificación HLS (RFC 8216): https://datatracker.ietf.org/doc/html/rfc8216
- hls.js: https://github.com/video-dev/hls.js/

### Interfaz web

- Plyr: https://github.com/sampotts/plyr/
- Anime.js: https://animejs.com/documentation/

### Tipografías

- Inter: https://rsms.me/inter/
- Fira Code: https://github.com/tonsky/FiraCode
