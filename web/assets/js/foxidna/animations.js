function animateVideoCards(cards) {

    if (!cards || !cards.length || typeof anime === "undefined") {
        return;
    }

    anime.animate(cards, {
        opacity: [0, 1],
        translateY: [18, 0],
        scale: [0.97, 1],
        delay: anime.stagger(80),
        duration: 550,
        ease: "out(3)"
    });
}


/*
 * También permitimos animar tarjetas que ya existan
 * al cargar la página.
 */
document.addEventListener("DOMContentLoaded", () => {

    const cards = document.querySelectorAll(".video-card");

    if (!cards.length) {
        return;
    }

    animateVideoCards(cards);

});
