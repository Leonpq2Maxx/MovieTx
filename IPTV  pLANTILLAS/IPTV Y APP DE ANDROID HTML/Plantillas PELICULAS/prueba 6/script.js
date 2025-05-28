let currentSlide = 0;
const totalSlides = 5;

// Deslizar el slider automáticamente cada 5 segundos
function moveSlider() {
    const sliderContainer = document.querySelector('.slider-container');
    currentSlide++;

    if (currentSlide >= totalSlides) {
        currentSlide = 0;
    }

    sliderContainer.style.transform = `translateX(-${currentSlide * 320}px)`;
}

setInterval(moveSlider, 5000); // Desliza cada 5 segundos

// Mostrar más géneros
function showMoreGenres() {
    alert('Mostrando más géneros...');
}

// Funciones para navegación en el menú
function goHome() {
    alert('https://leonpq2maxx.github.io/MegaTx/');
}

function goMovies() {
    alert('Navegando a Movies');
}

function goSeries() {
    alert('Index.html');
}
