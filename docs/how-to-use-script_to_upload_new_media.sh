# Guía para subir videos a `foxidna` con FFmpeg + HLS

## 1. Objetivo

Este procedimiento convierte un video propio en tres variantes HLS para el servidor `foxidna` y publica automáticamente el resultado.

La estructura estándar por video es:

```text
video/
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
```

Las variantes del proyecto quedan fijadas así:

| Variante | Resolución | Uso |
|---|---:|---|
| `v0` | 2560×1440 | Calidad original del proyecto |
| `v1` | 1920×1080 | 1080p |
| `v2` | 1280×720 | 720p |

No se genera una variante 480p.

El criterio de codificación de video es `H.264 / libx264`, `CRF 20`, `preset veryfast` y `yuv420p`. El audio se convierte a `AAC-LC`, 48 kHz, estéreo, 128 kb/s.

## 2. `master.m3u8` automático

Originalmente FFmpeg genera los tres streams HLS y el `master.m3u8` en una sola ejecución mediante `var_stream_map` y `master_pl_name`.

FFmpeg permite definir varias variantes, usar `%v` para crear directorios separados por variante y generar automáticamente un master playlist. Documentación oficial de FFmpeg: HLS muxer: https://ffmpeg.org/ffmpeg-formats.html

La idea central es:

```text
                    ┌── v0 → 2560×1440 ──┐
Video original ────┼── v1 → 1920×1080 ──┼── HLS
                    └── v2 → 1280×720  ──┘
                                  │
                                  ▼
                            master.m3u8
```

Por tanto, ya no es necesario escribir manualmente:

```text
#EXT-X-STREAM-INF:...
```

FFmpeg crea el master a partir de las variantes que produce.

## 3. Organización local

Se recomienda trabajar desde el directorio que contiene los videos de prueba, por ejemplo:

```text
main/
├── video_0.mkv
├── video_1.mov
├── video_2.mp4
└── hls_output/
```

Para cada video, el script crea:

```text
hls_output/NOMBRE_VIDEO/
```

El archivo original nunca se modifica.

## 4. Procedimiento automático recomendado

Copiar el script `uploader.sh` al directorio de trabajo `ss/` y darle permisos de ejecución:

```bash
chmod +x uploader.sh
```

Después, para subir un video:

```bash
./uploader.sh video_0.mkv
```

o:

```bash
./uploader.sh video_1.mkv
```

Para una ruta absoluta también es válido:

```bash
./uploader.sh /ruta/completa/al/video_3.mov
```

El script automáticamente:

1. Obtiene los FPS del origen.
2. Define un GOP de aproximadamente 6 segundos según el FPS del video.
3. Crea `v0`, `v1` y `v2`.
4. Conserva `CRF 20` para las tres variantes.
5. Convierte el audio PCM/otro formato a AAC.
6. Genera los segmentos HLS de 6 segundos.
7. Genera automáticamente `master.m3u8`.
8. Valida las playlists con `ffprobe`.
9. Publica el resultado en `/var/www/html/hls/NOMBRE_VIDEO/`.
10. Comprueba desde el contenedor `foxidna` que el master está disponible cuando el contenedor está en ejecución.

## 5. Estructura publicada en `foxidna`

Por ejemplo, después de procesar `video_4.mkv`:

```text
/var/www/html/hls/video_4/
├── master.m3u8
├── v0/
├── v1/
└── v2/
```

La URL HLS será:

```text
http://<IP_DE_LA_RASPBERRY>:8080/hls/video_4/master.m3u8
```

## 6. Página HTML del video

El script NO modifica `index.html`. Esto es intencional: el procesamiento multimedia y la interfaz web se mantienen separados.

Una página HTML de video debe apuntar al master correspondiente, por ejemplo:

```javascript
const videoSrc = "/hls/video_4/master.m3u8";
```

La página puede reutilizar el reproductor HLS.js utilizado actualmente por `video1.html`, `video2.html` y `video_2.html`.

En la página se puede incluir un selector de calidad para elegir:

```text
Automático
2560 × 1440
1920 × 1080
1280 × 720
```

## 7. Comprobación de resolución y códec

La validación de una variante se puede hacer con:

```bash
ffprobe -hide_banner hls_output/video_4/v0/prog_index.m3u8
ffprobe -hide_banner hls_output/video_4/v1/prog_index.m3u8
ffprobe -hide_banner hls_output/video_4/v2/prog_index.m3u8
```

Se espera:

```text
v0 → 2560×1440
v1 → 1920×1080
v2 → 1280×720
```

con:

```text
Video: h264
Audio: aac
48 kHz
stereo
```

## 8. Cuidado con la plataforma (Raspberry Pi 5)

La Raspberry Pi 5 dispone del wrapper `h264_v4l2m2m` en la instalación de FFmpeg utilizada en el proyecto, pero la prueba realizada en este servidor devolvió:

```text
Could not find a valid device
```

Por ello, el procedimiento actual utiliza `libx264` por CPU.

El script genera las tres variantes en una sola ejecución de FFmpeg. Esto tiene una ventaja: el video fuente se decodifica una sola vez y se divide en tres ramas antes de codificar las resoluciones. Sin embargo, las tres codificaciones H.264 siguen consumiendo CPU simultáneamente. Por esta razón se debe procesar un video por vez.

No se debe lanzar simultáneamente:

```bash
./uploader.sh video_4.mkv &
./uploader.sh video_1.mkv &
```

## 9. Espacio de almacenamiento

Total aproximado:

```text
500 MB por 5:17 de video
```

El tamaño final depende del contenido y del comportamiento del codificador con `CRF 20`; por eso estos valores deben utilizarse como referencia y no como una constante.

## 10. Compatibilidad con el esquema anterior

El proyecto conserva deliberadamente los nombres:

```text
v0
v1
v2
```

Esto mantiene compatibilidad conceptual con los videos HLS que generalmente se toman.

La única corrección importante es que ahora FFmpeg genera automáticamente el `master.m3u8` mediante la misma ejecución que crea las variantes. La documentación oficial de FFmpeg describe `var_stream_map` para agrupar varias variantes y `master_pl_name` para crear el master playlist.

> Importante: para que FFmpeg genere el master de forma nativa, las tres variantes deben formar parte de la misma ejecución de FFmpeg. Esto reduce el trabajo de decodificación del archivo fuente a una sola lectura, pero las tres codificaciones H.264 se ejecutan en el mismo proceso y pueden elevar el uso de CPU de la Raspberry Pi 5. Por eso el flujo sigue siendo **un video por vez**.

## 11. Recomendación para el flujo de trabajo del equipo

Para cada nuevo video:

```text
1. Colocar el original en ss/
        ↓
2. Ejecutar ./uploader.sh video.ext
        ↓
3. Esperar a que termine FFmpeg
        ↓
4. Revisar la validación ffprobe
        ↓
5. Comprobar /hls/NOMBRE_VIDEO/master.m3u8
        ↓
6. Crear/enlazar la página HTML del video
        ↓
7. Reproducir y probar 1440p / 1080p / 720p
```

## 12. Nota sobre el `master.m3u8`

No se debe editar manualmente el master generado por el script salvo que exista una razón específica de configuración.

La intención del nuevo procedimiento es que esta parte:

```text
v0 + v1 + v2
        ↓
master.m3u8
```

sea responsabilidad de FFmpeg y no requiera intervención manual.

## 13. Fuentes técnicas

- FFmpeg Documentation — HLS muxer, `var_stream_map`, `master_pl_name` y `%v`: https://ffmpeg.org/ffmpeg-formats.html
- FFmpeg Documentation — HLS output y generación de master playlist: https://www.ffmpeg.org/ffmpeg-all.html
