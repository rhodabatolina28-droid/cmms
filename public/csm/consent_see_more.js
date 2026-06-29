const toggleBtn = document.querySelector('.toggle-btn');
const consent = document.querySelector('.consent-text');

toggleBtn.addEventListener('click', function() {
    consent.classList.toggle('expanded');

    if (consent.classList.contains('expanded')) {
        toggleBtn.innerHTML = 'See Less <span class="arrow">&#9650;</span>';
    } else {
        toggleBtn.innerHTML = 'See More <span class="arrow">&#9660;</span>';
    }
});
