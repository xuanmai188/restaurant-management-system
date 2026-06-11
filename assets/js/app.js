let currentSlideIndex = 0;
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.dot');

function showSlide(index) {
    if (!slides.length) return;
    currentSlideIndex = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => slide.classList.toggle('active', i === currentSlideIndex));
    dots.forEach((dot, i) => dot.classList.toggle('active', i === currentSlideIndex));
}

function changeSlide(step) {
    showSlide(currentSlideIndex + step);
}

function currentSlide(index) {
    showSlide(index);
}

if (slides.length) {
    setInterval(() => showSlide(currentSlideIndex + 1), 5000);
}

function toggleUserMenu() {
    const menu = document.getElementById('userDropdownMenu');
    if (menu) menu.classList.toggle('show');
}

document.addEventListener('click', (event) => {
    const dropdown = document.querySelector('.user-dropdown');
    const menu = document.getElementById('userDropdownMenu');
    if (!dropdown || !menu) return;
    if (!dropdown.contains(event.target)) {
        menu.classList.remove('show');
    }
});
