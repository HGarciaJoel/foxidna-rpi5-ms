# Arquitectura actual del prototipo HLS

## Plataforma

El prototipo se ejecuta sobre una Raspberry Pi 5.

## Componentes actuales

- Raspberry Pi 5
- Linux
- FFmpeg
- Docker
- Debian
- Nginx
- HLS

## Arquitectura

La generación y el servicio del contenido se encuentran actualmente separados.

FFmpeg se ejecuta directamente sobre la Raspberry Pi y se utiliza para
generar el contenido multimedia HLS.

Nginx se ejecuta dentro de un contenedor Docker basado en Debian.

El directorio `/var/www/html` de la Raspberry Pi se monta dentro del
contenedor en la misma ruta:

    /var/www/html -> /var/www/html

Nginx escucha en el puerto 80 dentro del contenedor. Docker publica
ese puerto como el puerto 8080 de la Raspberry Pi.

Por lo tanto:

    Raspberry Pi:8080
            |
            v
    Docker: foxidna
            |
            v
    Nginx:80
            |
            v
    /var/www/html

## Streaming HLS

El contenido HLS se encuentra actualmente bajo:

    /var/www/html/hls/

El servidor Nginx utiliza una configuración específica para `/hls/`
que establece las cabeceras:

    Cache-Control: no-cache
    Access-Control-Allow-Origin: *

## Estado

Esta arquitectura corresponde al prototipo experimental actual.

La configuración será posteriormente automatizada y reproducida
mediante Docker y scripts versionados en Git.


## Entorno principal y entorno de pruebas

El contenedor `foxidna` constituye el entorno principal del servidor
multimedia y utiliza actualmente el puerto 8080 de la Raspberry Pi.

El contenedor `foxidna-nginx-test` constituye un entorno de pruebas
independiente. Se utiliza para validar nuevas imágenes y configuraciones
antes de considerar su incorporación al entorno principal.

Actualmente:

    foxidna              -> puerto 8080 -> entorno principal
    foxidna-nginx-test   -> puerto 8081 -> entorno de pruebas

Ambos contenedores pueden utilizar el contenido multimedia almacenado
localmente en:

    /var/www/html

El contenido multimedia y los segmentos HLS generados permanecen
fuera del repositorio Git.

El repositorio Git contiene la configuración, documentación, código y
archivos necesarios para reconstruir el entorno del servidor.

Por lo tanto, GitHub funciona como respaldo del estado reproducible
del proyecto y no como almacenamiento del contenido multimedia.

## Estrategia de recuperación

En caso de pérdida o corrupción del entorno principal, el repositorio
permite recuperar la configuración versionada y reconstruir la imagen
Docker.

Los archivos multimedia permanecen almacenados localmente en la
Raspberry Pi y no forman parte del proceso normal de versionado.
