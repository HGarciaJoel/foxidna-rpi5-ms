
document.addEventListener("DOMContentLoaded", () => {

    /*
     * =========================================================
     * Comprobaciones iniciales
     * =========================================================
     */

    const creatureEl = document.querySelector("#creature");

    if (!creatureEl) {
        return;
    }

    if (typeof anime === "undefined") {
        console.error(
            "foxidna: Anime.js no está disponible."
        );
        return;
    }


    /*
     * =========================================================
     * Configuración
     * =========================================================
     */

    const rows = 13;
    const grid = [rows, rows];
    const from = "center";


    /*
     * Colores de foxidna
     *
     * El color de cada partícula se interpola mediante HSL.
     * Después aplicamos una segunda transformación para
     * acercarlo a nuestra paleta rosa / magenta / violeta.
     */

    const foxidnaColors = [
        "#FF004D",
        "#FF00CC",
        "#B300FF"
    ];


    /*
     * =========================================================
     * Preparación de la rejilla
     * =========================================================
     */

    for (let i = 0; i < rows * rows; i++) {
        creatureEl.appendChild(
            document.createElement("div")
        );
    }

    const particleEls =
        creatureEl.querySelectorAll("div");


    /*
     * =========================================================
     * Staggers
     * =========================================================
     */

    const scaleStagger = anime.stagger(
        [2, 5],
        {
            ease: "inQuad",
            grid,
            from
        }
    );

    const opacityStagger = anime.stagger(
        [1, 0.1],
        {
            grid,
            from
        }
    );


    /*
     * =========================================================
     * Tamaño de la criatura
     * =========================================================
     */

    anime.utils.set(creatureEl, {
        width: `${rows * 10}em`,
        height: `${rows * 10}em`
    });


    /*
     * =========================================================
     * Estado inicial de las partículas
     * =========================================================
     */

    anime.utils.set(particleEls, {

        x: 0,
        y: 0,

        scale: scaleStagger,
        opacity: opacityStagger,

        background: anime.stagger(
            foxidnaColors,
            {
                grid,
                from,
                modifier: (value, index) => {
                    return foxidnaColors[
                        index % foxidnaColors.length
                    ];
                }
            }
        ),

        boxShadow: anime.stagger(
            [8, 1],
            {
                grid,
                from,
                modifier: (value) => {
                    const glow =
                        anime.utils.round(value, 0);

                    return `
                        0 0 ${glow}em
                        rgba(255, 0, 204, 0.35)
                    `;
                }
            }
        ),

        zIndex: anime.stagger(
            [rows * rows, 1],
            {
                grid,
                from,
                modifier: anime.utils.round(0)
            }
        )

    });


    /*
     * =========================================================
     * Posición inicial del cursor
     * =========================================================
     */

    let viewport = {
        w: window.innerWidth * 0.5,
        h: window.innerHeight * 0.5
    };

    const cursor = {
        x: 0,
        y: 0
    };


    /*
     * =========================================================
     * Actualizar viewport
     * =========================================================
     */

    window.addEventListener("resize", () => {

        viewport.w =
            window.innerWidth * 0.5;

        viewport.h =
            window.innerHeight * 0.5;

    });


    /*
     * =========================================================
     * Pulso de las partículas
     * =========================================================
     */

    const pulse = () => {

        anime.animate(particleEls, {

            keyframes: [

                {
                    scale: 5,
                    opacity: 1,

                    delay: anime.stagger(
                        90,
                        {
                            start: 1650,
                            grid,
                            from
                        }
                    ),

                    duration: 150
                },

                {
                    scale: scaleStagger,
                    opacity: opacityStagger,

                    ease: "inOutQuad",

                    duration: 600
                }

            ]

        });

    };


    /*
     * =========================================================
     * Movimiento principal
     * =========================================================
     *
     * 15 FPS para mantener controlado el consumo.
     */

    const mainLoop = anime.createTimer({

        frameRate: 15,

        onUpdate: () => {

            anime.animate(
                particleEls,
                {
                    x: cursor.x,
                    y: cursor.y,

                    delay: anime.stagger(
                        40,
                        {
                            grid,
                            from
                        }
                    ),

                    duration: anime.stagger(
                        120,
                        {
                            start: 750,
                            ease: "inQuad",
                            grid,
                            from
                        }
                    ),

                    ease: "inOut",

                    composition: "blend"
                }
            );

        }

    });


    /*
     * =========================================================
     * Movimiento automático
     * =========================================================
     *
     * La criatura continúa moviéndose cuando el usuario
     * deja de interactuar con el cursor.
     */

    const autoMove = anime.createTimeline()

        .add(
            cursor,
            {
                x: [
                    -viewport.w * 0.45,
                    viewport.w * 0.45
                ],

                modifier: (x) => {
                    return (
                        x +
                        Math.sin(
                            mainLoop.currentTime * 0.0007
                        ) *
                        viewport.w *
                        0.5
                    );
                },

                duration: 3000,

                ease: "inOutExpo",

                alternate: true,
                loop: true,

                onBegin: pulse,
                onLoop: pulse
            },
            0
        )

        .add(
            cursor,
            {
                y: [
                    -viewport.h * 0.45,
                    viewport.h * 0.45
                ],

                modifier: (y) => {
                    return (
                        y +
                        Math.cos(
                            mainLoop.currentTime * 0.00012
                        ) *
                        viewport.h *
                        0.5
                    );
                },

                duration: 1000,

                ease: "inOutQuad",

                alternate: true,
                loop: true
            },
            0
        );


    /*
     * =========================================================
     * Temporizador de regreso al movimiento automático
     * =========================================================
     */

    const manualMovementTimeout =
        anime.createTimer({

            duration: 1500,

            onComplete: () => {
                autoMove.play();
            }

        });


    /*
     * =========================================================
     * Seguimiento del cursor
     * =========================================================
     */

    const followPointer = (event) => {

        const pointer =
            event.type === "touchmove"
                ? event.touches[0]
                : event;

        cursor.x =
            pointer.pageX - viewport.w;

        cursor.y =
            pointer.pageY - viewport.h;


        /*
         * Pausar movimiento automático mientras
         * el usuario mueve el cursor.
         */

        autoMove.pause();

        manualMovementTimeout.restart();

    };


    /*
     * =========================================================
     * Eventos
     * =========================================================
     */

    document.addEventListener(
        "mousemove",
        followPointer,
        { passive: true }
    );

    document.addEventListener(
        "touchmove",
        followPointer,
        { passive: true }
    );

});
