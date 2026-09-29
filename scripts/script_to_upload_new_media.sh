#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# Subir video HLS a foxidna
#
# Estructura por video:
#   v0 -> 2560x1440
#   v1 -> 1920x1080
#   v2 -> 1280x720
#
# El master.m3u8 es generado directamente por FFmpeg mediante
# var_stream_map + master_pl_name.
# ============================================================

if [[ $# -ne 1 ]]; then
    echo "Uso: $0 /ruta/al/video.ext"
    exit 1
fi

INPUT="$1"

if [[ ! -f "$INPUT" ]]; then
    echo "ERROR: no existe el archivo: $INPUT"
    exit 1
fi

if ! command -v ffmpeg >/dev/null 2>&1; then
    echo "ERROR: ffmpeg no está instalado o no está en PATH."
    exit 1
fi

if ! command -v ffprobe >/dev/null 2>&1; then
    echo "ERROR: ffprobe no está instalado o no está en PATH."
    exit 1
fi

# Directorio del proyecto: se asume que el script se ejecuta desde ss/.
PROJECT_DIR="$(pwd)"
INPUT_ABS="$(realpath "$INPUT")"
BASE_NAME="$(basename "$INPUT_ABS")"
VIDEO_ID="${BASE_NAME%.*}"

# Para URLs/paths más limpios, se rechazan espacios en el nombre.
if [[ "$VIDEO_ID" =~ [[:space:]] ]]; then
    echo "ERROR: el nombre del video contiene espacios."
    echo "Renombra el archivo antes de continuar."
    exit 1
fi

LOCAL_ROOT="$PROJECT_DIR/hls_output/$VIDEO_ID"
SERVER_ROOT="/var/www/html/hls/$VIDEO_ID"

if [[ -e "$LOCAL_ROOT" ]]; then
    echo "ERROR: ya existe el directorio local: $LOCAL_ROOT"
    echo "Para evitar sobrescrituras accidentales, elimina o respalda ese directorio primero."
    exit 1
fi

if sudo test -e "$SERVER_ROOT"; then
    echo "ERROR: ya existe el directorio publicado: $SERVER_ROOT"
    echo "Para evitar sobrescrituras accidentales, elimina o respalda ese directorio primero."
    exit 1
fi

# Obtener FPS del origen. El proyecto usa fuentes de 30 o 60 fps.
RATE="$(ffprobe -v error -select_streams v:0 -show_entries stream=r_frame_rate -of csv=p=0 "$INPUT_ABS")"
FPS="$(awk -F/ '{ if ($2 == 0) exit 1; printf "%.6f", $1/$2 }' <<< "$RATE")"
FPS_INT="$(awk -v f="$FPS" 'BEGIN { printf "%d", f+0.5 }')"
GOP="$(awk -v f="$FPS" 'BEGIN { printf "%d", f*6+0.5 }')"

if [[ "$FPS_INT" -ne 30 && "$FPS_INT" -ne 60 ]]; then
    echo "ADVERTENCIA: se detectó aproximadamente ${FPS} fps."
    echo "El script está pensado para fuentes de 30 o 60 fps."
fi

echo "============================================================"
echo "Video:       $BASE_NAME"
echo "ID:          $VIDEO_ID"
echo "FPS origen:  $RATE (~${FPS_INT} fps)"
echo "GOP:         $GOP cuadros"
echo "Salida:      $LOCAL_ROOT"
echo "Publicación: $SERVER_ROOT"
echo "============================================================"

mkdir -p "$LOCAL_ROOT"

# Crear variantes v0/v1/v2 y master.m3u8 en una sola ejecución FFmpeg.
# Esto permite que FFmpeg genere el master automáticamente.
# El nombre v0/v1/v2 se fija mediante name: en var_stream_map.
# El split evita decodificar el HEVC tres veces.

# FFmpeg 7.x puede calcular BANDWIDTH/AVERAGE-BANDWIDTH al terminar el VOD.
# La opción se activa solo si la instalación local la ofrece.
HLS_FLAGS="independent_segments"
if ffmpeg -hide_banner -h muxer=hls 2>&1 | grep -q "average_bandwidth"; then
    HLS_FLAGS="${HLS_FLAGS}+average_bandwidth"
elif ffmpeg -hide_banner -h muxer=hls 2>&1 | grep -q "peak_segment_bw"; then
    HLS_FLAGS="${HLS_FLAGS}+peak_segment_bw"
fi

echo "HLS flags: $HLS_FLAGS"

ffmpeg -hide_banner -y \
    -i "$INPUT_ABS" \
    -filter_complex \
        "[0:v]split=3[src0][src1][src2];[src0]scale=2560:1440[v0];[src1]scale=1920:1080[v1];[src2]scale=1280:720[v2]" \
    -map "[v0]" -map 0:a:0 \
    -map "[v1]" -map 0:a:0 \
    -map "[v2]" -map 0:a:0 \
    -c:v:0 libx264 -preset veryfast -crf 20 -pix_fmt yuv420p -r "$FPS_INT" -g "$GOP" -keyint_min "$GOP" -sc_threshold 0 \
    -c:v:1 libx264 -preset veryfast -crf 20 -pix_fmt yuv420p -r "$FPS_INT" -g "$GOP" -keyint_min "$GOP" -sc_threshold 0 \
    -c:v:2 libx264 -preset veryfast -crf 20 -pix_fmt yuv420p -r "$FPS_INT" -g "$GOP" -keyint_min "$GOP" -sc_threshold 0 \
    -c:a:0 aac -b:a:0 128k -ar:a:0 48000 -ac:a:0 2 \
    -c:a:1 aac -b:a:1 128k -ar:a:1 48000 -ac:a:1 2 \
    -c:a:2 aac -b:a:2 128k -ar:a:2 48000 -ac:a:2 2 \
    -var_stream_map "v:0,a:0,name:v0 v:1,a:1,name:v1 v:2,a:2,name:v2" \
    -master_pl_name master.m3u8 \
    -hls_time 6 \
    -hls_playlist_type vod \
    -hls_flags "$HLS_FLAGS" \
    -hls_segment_filename "$LOCAL_ROOT/%v/segment_%03d.ts" \
    "$LOCAL_ROOT/%v/prog_index.m3u8"

# Validaciones básicas.
for v in v0 v1 v2; do
    [[ -f "$LOCAL_ROOT/$v/prog_index.m3u8" ]] || { echo "ERROR: falta $v/prog_index.m3u8"; exit 1; }
    find "$LOCAL_ROOT/$v" -maxdepth 1 -name '*.ts' -print -quit | grep -q . || {
        echo "ERROR: no hay segmentos .ts en $v"; exit 1;
    }
done

[[ -f "$LOCAL_ROOT/master.m3u8" ]] || { echo "ERROR: FFmpeg no creó master.m3u8"; exit 1; }

# Mostrar resumen técnico sin imprimir todas las listas/segmentos.
echo
echo "=== MASTER GENERADO POR FFMPEG ==="
cat "$LOCAL_ROOT/master.m3u8"

echo
echo "=== TAMAÑOS ==="
du -sh "$LOCAL_ROOT/v0" "$LOCAL_ROOT/v1" "$LOCAL_ROOT/v2"

echo
echo "=== VALIDACIÓN FFMPEG ==="
ffprobe -hide_banner "$LOCAL_ROOT/master.m3u8"

# Publicar al directorio compartido con el contenedor foxidna.
echo
echo "=== PUBLICANDO EN FOXIDNA ==="
sudo mkdir -p "$SERVER_ROOT"
sudo mv "$LOCAL_ROOT" "$SERVER_ROOT"
# Comprobación final desde el contenedor.
echo
echo "=== COMPROBACIÓN DESDE FOXIDNA ==="
if docker inspect -f '{{.State.Running}}' foxidna 2>/dev/null | grep -q '^true$'; then
    docker exec foxidna sh -c "test -f '$SERVER_ROOT/master.m3u8' && echo 'master.m3u8 OK'"
    docker exec foxidna sh -c "find '$SERVER_ROOT' -maxdepth 2 -type f -name '*.m3u8' -printf '%p\\n' | sort"
else
    echo "foxidna está detenido; los archivos ya fueron publicados en el host."
    echo "Arranca el contenedor antes de reproducir el video."
fi

echo
echo "Proceso terminado correctamente."
echo "HLS publicado en: $SERVER_ROOT"
echo "Master: /hls/$VIDEO_ID/master.m3u8"
echo
echo "Nota: este script NO modifica index.html ni crea la página HTML del video."
echo "Eso se deja separado para evitar cambios accidentales en la interfaz."
