document.addEventListener("DOMContentLoaded", async () => {

    const grid = document.querySelector(".video-grid");
    const sectionCount = document.querySelector(".section-count");

    if (!grid) {
        return;
    }

    try {

        const response = await fetch("api/scanner.php", {
            cache: "no-store"
        });

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
         * Eliminamos cualquier contenido estático
         * que pudiera existir dentro de la cuadrícula.
         */
        grid.replaceChildren();

        const fragment = document.createDocumentFragment();
        const cards = [];

        data.videos.forEach((video) => {

            const card = document.createElement("a");

            card.href =
                `player.html?v=${encodeURIComponent(video.id)}`;

            card.className = "video-card";
            card.dataset.video = video.id;

            /*
             * -------------------------------------------------
             * Vista previa
             * -------------------------------------------------
             */

            const preview = document.createElement("div");
            preview.className = "video-preview";

            const overlay = document.createElement("div");
            overlay.className = "preview-overlay";

            const playButton = document.createElement("div");
            playButton.className = "play-button";
            playButton.setAttribute("aria-hidden", "true");

            const playButtonIcon = document.createElement("span");
            playButton.appendChild(playButtonIcon);

            const badge = document.createElement("span");
            badge.className = "video-badge";
            badge.textContent = "LOCAL";

            const infoOverlay = document.createElement("div");
            infoOverlay.className = "video-info-overlay";

            const infoLabel = document.createElement("span");
            infoLabel.className = "info-label";
            infoLabel.textContent = "SERVIDOR LOCAL";

            const infoTitle = document.createElement("span");
            infoTitle.className = "info-title";
            infoTitle.textContent = video.title || video.id;

            infoOverlay.appendChild(infoLabel);
            infoOverlay.appendChild(infoTitle);

            preview.appendChild(overlay);
            preview.appendChild(playButton);
            preview.appendChild(badge);
            preview.appendChild(infoOverlay);

            /*
             * -------------------------------------------------
             * Contenido de la tarjeta
             * -------------------------------------------------
             */

            const cardContent = document.createElement("div");
            cardContent.className = "card-content";

            const title = document.createElement("h2");
            title.className = "video-title";
            title.textContent = video.title || video.id;

            const meta = document.createElement("p");
            meta.className = "video-meta";
            meta.textContent = "Servidor local";

            cardContent.appendChild(title);
            cardContent.appendChild(meta);

            /*
             * -------------------------------------------------
             * Ensamblar tarjeta
             * -------------------------------------------------
             */

            card.appendChild(preview);
            card.appendChild(cardContent);

            fragment.appendChild(card);
            cards.push(card);
        });

        grid.appendChild(fragment);

        /*
         * Actualizar contador.
         */
        if (sectionCount) {

            const count = data.videos.length;

            sectionCount.textContent =
                `${count} ${count === 1 ? "elemento" : "elementos"}`;
        }

        /*
         * Animar las tarjetas recién creadas.
         */
        if (typeof animateVideoCards === "function") {
            animateVideoCards(cards);
        }

    } catch (error) {

        console.error(
            "No fue posible cargar el catálogo de vídeos:",
            error
        );

        if (sectionCount) {
            sectionCount.textContent = "Error";
        }

        grid.replaceChildren();

        const message = document.createElement("p");

        message.textContent =
            "No fue posible cargar los vídeos del servidor.";

        grid.appendChild(message);
    }

});
