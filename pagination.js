// Scroll suave ao trocar de página
document.addEventListener('DOMContentLoaded', function () {
    // Adiciona comportamento aos botões de paginação
    const paginationLinks = document.querySelectorAll('.pagination-btn[href]');

    paginationLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            // Permite a navegação normal
            // Mas faz scroll suave ao topo antes de carregar
            setTimeout(() => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }, 50);
        });
    });

    // Se chegou na página através de paginação, scroll suave ao topo
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('page') && window.scrollY > 100) {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
});
