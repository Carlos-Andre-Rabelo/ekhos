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
        document.getElementById('modal-artista').textContent = card.dataset.artista;
        document.getElementById('modal-ano').textContent = card.dataset.ano;
        document.getElementById('modal-genero').textContent = card.dataset.genero;
        document.getElementById('modal-gravadora').textContent = card.dataset.gravadora;
        document.getElementById('modal-duracao').textContent = card.dataset.duracao;

        // Preencher lista de exemplares
        const exemplaresList = document.getElementById('modal-exemplares');
        exemplaresList.innerHTML = ''; // Limpa exemplares anteriores
        try {
            const exemplares = JSON.parse(card.dataset.exemplares);
            if (exemplares && exemplares.length > 0) {
                exemplares.forEach(exemplar => {
                    const li = document.createElement('li');
                    const tipo = exemplar.tipo.charAt(0).toUpperCase() + exemplar.tipo.slice(1);
                    const preco = parseFloat(exemplar.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    let text = `${tipo} - ${preco}`;
                    if(exemplar.tipo === 'vinil' && exemplar.tamanho) {
                        text += ` (${exemplar.tamanho}")`;
                    }
                    li.textContent = text;
                    exemplaresList.appendChild(li);
                });
            } else {
                exemplaresList.innerHTML = '<li>Nenhum exemplar disponível.</li>';
            }
        } catch (e) {
            exemplaresList.innerHTML = '<li>Erro ao carregar informações dos exemplares.</li>';
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