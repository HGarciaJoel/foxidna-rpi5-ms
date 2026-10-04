document.addEventListener("DOMContentLoaded", () => {

    /* =========================================================
       Elementos del reproductor
       ========================================================= */

    const video = document.getElementById("videoPlayer");
    const qualitySelect = document.getElementById("qualitySelect");

    const videoSidebarList = document.getElementById("videoSidebarList");

    const resData = document.getElementById("resData");
    const bitrateData = document.getElementById("bitrateData");
    const downData = document.getElementById("downData");

	const durationData = document.getElementById("durationData");

	const codecData = document.getElementById("codecData");

	const qualitiesData = document.getElementById("qualitiesData");


    /* =========================================================
       Obtener identificador del vídeo
       ========================================================= */

    const urlParams = new URLSearchParams(window.location.search);
    const videoId = urlParams.get("v");

    if (!videoId) {
        window.location.href = "index.html";
        return;
    }

    const videoSrc = `/hls/${videoId}/master.m3u8`;
	/* =========================================================
   Metadatos del vídeo
   ========================================================= */

function formatDuration(seconds) {

    if (!Number.isFinite(seconds) || seconds < 0) {
        return "No disponible";
    }

    const totalSeconds = Math.round(seconds);

    const hours =
        Math.floor(totalSeconds / 3600);

    const minutes =
        Math.floor((totalSeconds % 3600) / 60);

    const remainingSeconds =
        totalSeconds % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, "0")}:${String(remainingSeconds).padStart(2, "0")}`;
    }

    return `${String(minutes).padStart(2, "0")}:${String(remainingSeconds).padStart(2, "0")}`;
}


function formatBitrate(bitsPerSecond) {

    if (!Number.isFinite(bitsPerSecond) || bitsPerSecond <= 0) {
        return "No disponible";
    }

    return `${(bitsPerSecond / 1000000).toFixed(2)} Mb/s`;
}


async function loadVideoMetadata() {

    try {

        const response = await fetch(
            `/api/metadata.php?id=${encodeURIComponent(videoId)}`,
            {
                cache: "no-store"
            }
        );

        if (!response.ok) {
            throw new Error(
                `Error HTTP ${response.status}`
            );
        }

        const metadata = await response.json();

        /*
         * Duración global
         */
        durationData.textContent =
            formatDuration(metadata.duration);

        /*
         * Códec de vídeo.
         *
         * Tomamos v0 como referencia cuando está disponible.
         * Si no existe, buscamos la primera representación válida.
         */
        let referenceQuality = null;

        if (
            metadata.qualities &&
            metadata.qualities.v0 &&
            metadata.qualities.v0.available
        ) {
            referenceQuality = metadata.qualities.v0;
        } else if (metadata.qualities) {

            for (const quality of Object.values(metadata.qualities)) {

                if (quality.available) {
                    referenceQuality = quality;
                    break;
                }
            }
        }

        codecData.textContent =
            referenceQuality?.codec || "No disponible";

        /*
         * Representaciones disponibles
         */
        qualitiesData.replaceChildren();

        const qualityOrder = ["v0", "v1", "v2"];

        qualityOrder.forEach((variant) => {

            const quality =
                metadata.qualities?.[variant];

            if (!quality) {
                return;
            }

            const row = document.createElement("div");

            if (!quality.available) {

                row.textContent =
                    `${quality.label}: No disponible`;

            } else {

                row.textContent =
                    `${quality.label}  •  ` +
                    `${quality.width} × ${quality.height}  •  ` +
                    `${formatBitrate(quality.bitrate)}`;
            }

            qualitiesData.appendChild(row);
        });

    } catch (error) {

        console.error(
            "No fue posible cargar los metadatos del vídeo:",
            error
        );

        durationData.textContent = "No disponible";
        codecData.textContent = "No disponible";
        qualitiesData.textContent = "No disponible";
    }
}


/*
 * Los metadatos se cargan independientemente
 * del reproductor.
 */
loadVideoMetadata();


async function loadSidebarVideos() {

    if (!videoSidebarList) {
        return;
    }

    try {

        /*
         * Pedimos cinco elementos para poder excluir
         * el vídeo que ya se está reproduciendo.
         */
        const response = await fetch(
            "/api/scanner.php?limit=5",
            {
                cache: "no-store"
            }
        );

        if (!response.ok) {
            throw new Error(
                `Error HTTP ${response.status}`
            );
        }

        const data = await response.json();

        if (!Array.isArray(data.videos)) {
            throw new Error(
                "La respuesta de scanner.php no contiene una lista válida."
            );
        }

        /*
         * Excluir el vídeo actual y tomar los primeros cuatro.
         */
        const sidebarVideos =
            data.videos
                .filter((video) => video.id !== videoId)
                .slice(0, 4);

        videoSidebarList.replaceChildren();

        const fragment =
            document.createDocumentFragment();

        const cards = [];

        sidebarVideos.forEach((video) => {

            const card = document.createElement("a");

            card.href =
                `player.html?v=${encodeURIComponent(video.id)}`;

            card.className = "video-sidebar-card";

            /*
             * Vista previa abstracta.
             */
            const preview =
                document.createElement("div");

            preview.className =
                "sidebar-preview";

            const overlay =
                document.createElement("div");

            overlay.className =
                "sidebar-preview-overlay";

            const badge =
                document.createElement("span");

            badge.className =
                "sidebar-badge";

            badge.textContent =
                "LOCAL";

            preview.appendChild(overlay);
            preview.appendChild(badge);


            /*
             * Información.
             */
            const content =
                document.createElement("div");

            content.className =
                "sidebar-card-content";

            const title =
                document.createElement("h3");

            title.className =
                "sidebar-card-title";

            title.textContent =
                video.title || video.id;

            const meta =
                document.createElement("p");

            meta.className =
                "sidebar-card-meta";

            meta.textContent =
                "Servidor local";

            content.appendChild(title);
            content.appendChild(meta);


            /*
             * Ensamblar tarjeta.
             */
            card.appendChild(preview);
            card.appendChild(content);

            fragment.appendChild(card);
            cards.push(card);
        });

        videoSidebarList.appendChild(fragment);


        /*
         * Animación de entrada.
         */
        if (
            cards.length &&
            typeof anime !== "undefined"
        ) {

            anime.animate(cards, {
                opacity: [0, 1],
                translateY: [12, 0],
                scale: [0.98, 1],
                delay: anime.stagger(70),
                duration: 420,
                ease: "out(3)"
            });
        }

    } catch (error) {

        console.error(
            "No fue posible cargar los vídeos de la barra lateral:",
            error
        );

        videoSidebarList.replaceChildren();

        const message =
            document.createElement("p");

        message.textContent =
            "No fue posible cargar otros vídeos.";

        message.style.color =
            "#777780";

        message.style.fontSize =
            "12px";

        videoSidebarList.appendChild(message);
    }
}


loadSidebarVideos();



    /* =========================================================
       Inicialización de Plyr (NUEVO)
       ========================================================= */
       
    const player = new Plyr(video, {
        controls: [
            'play-large', 'rewind', 'play', 'fast-forward', 
            'progress', 'current-time', 'duration', 
            'mute', 'volume', 'settings', 'pip', 'fullscreen'
        ],
        seekTime: 10, // Saltos de 10 segundos
        settings: ['speed'], // Menú interno solo para velocidad (Calidad está en tu menú)
        speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
        i18n: {
            rewind: 'Retrasar 10s',
            fastForward: 'Adelantar 10s',
            speed: 'Velocidad',
            normal: 'Normal'
        }
    });


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
            const autoOption = document.createElement("option");
            autoOption.value = "-1";
            autoOption.textContent = "Automático";
            qualitySelect.appendChild(autoOption);

            /* Opciones reales disponibles */
            hls.levels.forEach((level, index) => {
                const option = document.createElement("option");
                option.value = index;
                option.textContent = `${level.width} × ${level.height}`;
                qualitySelect.appendChild(option);
            });

            /* Usamos Plyr para intentar la reproducción automática */
            player.play().catch(() => {
                console.log("Auto-play prevenido por el navegador.");
            });

        });

        /* -----------------------------------------------------
           Selección manual de calidad
           ----------------------------------------------------- */
        qualitySelect.addEventListener("change", () => {
            const level = parseInt(qualitySelect.value, 10);
            hls.currentLevel = level;
        });

        /* -----------------------------------------------------
           Cambio efectivo de resolución
           ----------------------------------------------------- */
        hls.on(Hls.Events.LEVEL_SWITCHED, (event, data) => {
            const level = hls.levels[data.level];

            if (!level) {
                return;
            }

            resData.textContent = `${level.width} × ${level.height}`;
            bitrateData.textContent = `${(level.bitrate / 1000).toFixed(0)} kb/s`;
        });

        /* -----------------------------------------------------
           Velocidad de transferencia
           ----------------------------------------------------- */
        hls.on(Hls.Events.FRAG_LOADED, (event, data) => {
            const bytes = data.frag.stats.total;
            const start = data.frag.stats.loading.start;
            const end = data.frag.stats.loading.end;
            const tiempo = (end - start) / 1000;

            if (tiempo <= 0) {
                return;
            }

            const velocidad = (bytes * 8 / tiempo / 1000000).toFixed(2);
            downData.textContent = `${velocidad} Mb/s`;
        });

    /* =========================================================
       HLS nativo del navegador
       ========================================================= */
    } else if (video.canPlayType("application/vnd.apple.mpegurl")) {
        video.src = videoSrc;
        video.addEventListener("loadedmetadata", () => {
            player.play().catch(() => {
                console.log("Auto-play prevenido por el navegador.");
            });
        });

    /* =========================================================
       HLS no disponible
       ========================================================= */
    } else {
        alert("Tu navegador no soporta reproducción HLS.");
    }

});
