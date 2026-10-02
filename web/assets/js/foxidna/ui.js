document.addEventListener("DOMContentLoaded", () => {

    /*
     * =========================================================
     * Feedback de clic
     * =========================================================
     */

    if (typeof anime !== "undefined") {

        const interactiveElements = document.querySelectorAll(
            "button, .back-btn"
        );

        interactiveElements.forEach((element) => {

            element.addEventListener("click", () => {

                /*
                 * Evitamos animar elementos deshabilitados.
                 */
                if (element.disabled) {
                    return;
                }

                anime.animate(element, {
                    scale: [1, 0.96, 1],
                    duration: 160,
                    ease: "out(4)"
                });

            });

        });

    }


    /*
     * =========================================================
     * Estado visual del selector de calidad
     * =========================================================
     *
     * Por ahora NO reemplazamos el <select> nativo.
     * Solo detectamos cambios para poder ampliar su
     * comportamiento posteriormente.
     */

    const qualitySelect =
        document.getElementById("qualitySelect");

    if (qualitySelect) {

        qualitySelect.addEventListener("change", () => {

            qualitySelect.classList.add("quality-changed");

            setTimeout(() => {
                qualitySelect.classList.remove("quality-changed");
            }, 180);

        });

    }

});
