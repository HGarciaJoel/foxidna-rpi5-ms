
document.addEventListener("DOMContentLoaded", () => {

    /* =========================================================
       Elementos del reproductor
       ========================================================= */

    const video = document.getElementById("videoPlayer");
    const qualitySelect = document.getElementById("qualitySelect");

    const resData = document.getElementById("resData");
    const bitrateData = document.getElementById("bitrateData");
    const downData = document.getElementById("downData");


    /* =========================================================
       Obtener identificador del vídeo
       ========================================================= */

    const urlParams = new URLSearchParams(window.location.search);
    const videoId = urlParams.get("v");

    if (!videoId) {
        window.location.href = "index.html";
        return;
    }


    /* =========================================================
       Ruta al manifiesto HLS
       ========================================================= */

    const videoSrc = `/hls/${videoId}/master.m3u8`;


    /* =========================================================
       HLS.js
       ========================================================= */

    if (Hls.isSupported()) {

        const hls = new Hls();

        hls.loadSource(videoSrc);
        hls.attachMedia(video);


        /* -----------------------------------------------------
           Manifiesto cargado
           ----------------------------------------------------- */

        hls.on(Hls.Events.MANIFEST_PARSED, () => {

            qualitySelect.innerHTML = "";

            /* Opción automática */

            const autoOption =
                document.createElement("option");

            autoOption.value = "-1";
            autoOption.textContent = "Automático";

            qualitySelect.appendChild(autoOption);


            /* Opciones reales disponibles */

            hls.levels.forEach((level, index) => {

                const option =
                    document.createElement("option");

                option.value = index;

                option.textContent =
                    `${level.width} × ${level.height}`;

                qualitySelect.appendChild(option);

            });


            /* Intentar reproducción automática */

            video.play().catch(() => {
                console.log(
                    "Auto-play prevenido por el navegador."
                );
            });

        });


        /* -----------------------------------------------------
           Selección manual de calidad
           ----------------------------------------------------- */

        qualitySelect.addEventListener("change", () => {

            const level =
                parseInt(qualitySelect.value, 10);

            hls.currentLevel = level;

        });


        /* -----------------------------------------------------
           Cambio efectivo de resolución
           ----------------------------------------------------- */

        hls.on(
            Hls.Events.LEVEL_SWITCHED,
            (event, data) => {

                const level =
                    hls.levels[data.level];

                if (!level) {
                    return;
                }

                resData.textContent =
                    `${level.width} × ${level.height}`;

                bitrateData.textContent =
                    `${(level.bitrate / 1000).toFixed(0)} kb/s`;

            }
        );


        /* -----------------------------------------------------
           Velocidad de transferencia
           ----------------------------------------------------- */

        hls.on(
            Hls.Events.FRAG_LOADED,
            (event, data) => {

                const bytes =
                    data.frag.stats.total;

                const start =
                    data.frag.stats.loading.start;

                const end =
                    data.frag.stats.loading.end;

                const tiempo =
                    (end - start) / 1000;

                if (tiempo <= 0) {
                    return;
                }

                const velocidad =
                    (
                        bytes *
                        8 /
                        tiempo /
                        1000000
                    ).toFixed(2);

                downData.textContent =
                    `${velocidad} Mb/s`;

            }
        );


    /* =========================================================
       HLS nativo del navegador
       ========================================================= */

    } else if (
        video.canPlayType(
            "application/vnd.apple.mpegurl"
        )
    ) {

        video.src = videoSrc;

        video.addEventListener(
            "loadedmetadata",
            () => {

                video.play().catch(() => {
                    console.log(
                        "Auto-play prevenido por el navegador."
                    );
                });

            }
        );


    /* =========================================================
       HLS no disponible
       ========================================================= */

    } else {

        alert(
            "Tu navegador no soporta reproducción HLS."
        );

    }

});

