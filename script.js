document.addEventListener('DOMContentLoaded', function() {
    const searchBar = document.getElementById('search-bar');
    const albumGrid = document.getElementById('album-grid');
    const albumCards = albumGrid.querySelectorAll('.album-card');

    // Modal elements
    const modal = document.getElementById('album-modal');
    const modalClose = document.querySelector('.modal-close');
    const modalOverlay = document.querySelector('.modal-overlay');

    // Se não houver cartões de álbum, não há nada a fazer.
    if (albumCards.length === 0) {
        return;
    }

    // --- Funcionalidade de Pesquisa ---
    searchBar.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();

        albumCards.forEach(card => {
            const title = card.dataset.titulo.toLowerCase();
            const artist = card.dataset.artista.toLowerCase();
            const genre = card.dataset.genero.toLowerCase();

            if (title.includes(searchTerm) || artist.includes(searchTerm) || genre.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // --- Funcionalidade do Modal (Popup) ---

    // Função para acionar a busca
    const triggerSearch = (term) => {
        closeModal();
        searchBar.value = term;
        searchBar.dispatchEvent(new Event('input', { bubbles: true }));
    };

    // Função para abrir o modal
    const openModal = (card) => {
        // Adiciona a classe ao body para aplicar o blur no fundo
        document.body.classList.add('modal-open');
        // Define a imagem de fundo do modal
        modal.querySelector('.modal-content').style.setProperty('--modal-bg-image', `url('${card.dataset.capa}')`);
        // Preencher dados do modal
        document.getElementById('modal-capa').src = card.dataset.capa;
        document.getElementById('modal-capa').alt = `Capa do álbum ${card.dataset.titulo}`;
        document.getElementById('modal-titulo').textContent = card.dataset.titulo;
        
        // Preencher Artista e adicionar evento de clique
        const modalArtista = document.getElementById('modal-artista');
        modalArtista.textContent = card.dataset.artista;
        modalArtista.onclick = () => triggerSearch(card.dataset.artista);

        document.getElementById('modal-ano').textContent = card.dataset.ano;

        // Preencher Gêneros e adicionar eventos de clique no span de display
        const generoDisplaySpan = document.getElementById('modal-genero-display');
        generoDisplaySpan.innerHTML = '<strong>Gênero:</strong> '; // Começa com o texto "Gênero:"

        const generos = card.dataset.genero.split(', ').filter(g => g);
        if (generos.length > 0) {
            generos.forEach((genero, index) => {
                const genreLink = document.createElement('a');
                genreLink.textContent = genero;
                genreLink.className = 'genre-link'; // Mantém a classe para o estilo de link
                genreLink.onclick = () => triggerSearch(genero);
                generoDisplaySpan.appendChild(genreLink);
                if (index < generos.length - 1) {
                    generoDisplaySpan.append(', ');
                }
            });
        } else {
            generoDisplaySpan.append('N/A');
        }

        document.getElementById('modal-gravadora').textContent = card.dataset.gravadora;
        document.getElementById('modal-duracao').textContent = card.dataset.duracao;
        
        // Preencher lista de exemplares
        const formatosList = document.getElementById('modal-formatos');
        formatosList.innerHTML = ''; // Limpa formatos anteriores
        try {
            const formatos = JSON.parse(card.dataset.formatos);
            if (formatos && formatos.length > 0) {
                formatos.forEach(exemplar => {
                    const li = document.createElement('li');
                    // Formata o tipo (ex: "vinil_7" -> "Vinil 7"")
                    const tipoFormatado = exemplar.tipo.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) + (exemplar.tipo.includes('vinil') ? '"' : '');
                    const preco = parseFloat(exemplar.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    const quantidade = exemplar.quantidade_estoque;

                    li.innerHTML = `
                        <span class="formato-tipo">${tipoFormatado}</span>
                        <span class="formato-estoque">Estoque: ${quantidade}</span>
                        <span class="formato-preco">${preco}</span>
                    `;
                    formatosList.appendChild(li);
                });
            } else {
                formatosList.innerHTML = '<li>Nenhum formato disponível.</li>';
            }
        } catch (e) {
            formatosList.innerHTML = '<li>Erro ao carregar informações dos formatos.</li>';
        }

        // Exibir o modal com animação
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.style.opacity = '1';
            modal.querySelector('.modal-content').style.transform = 'scale(1)';
        }, 10); // Pequeno delay para garantir que a transição CSS funcione
    };

    // Função para fechar o modal
    const closeModal = () => {
        // Remove a classe do body para remover o blur
        document.body.classList.remove('modal-open');
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Tempo da transição
    };

    // Adicionar evento de clique para cada card
    albumCards.forEach(card => {
        card.addEventListener('click', () => openModal(card));
    });

    // Eventos para fechar o modal
    modalClose.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });
});