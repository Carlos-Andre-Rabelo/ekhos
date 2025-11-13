document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('album-modal');
    const modalClose = modal.querySelector('.modal-close');
    const albumGrid = document.getElementById('album-grid');
    const body = document.body;
    const searchBar = document.getElementById('search-bar');
    const userRole = body.dataset.userRole;

    //funcao pra auxiliar busca (vai pro escopo global)
    const triggerSearch = (term) => {
        closeModal();
        searchBar.value = term;
        //dispara evento input
        searchBar.dispatchEvent(new Event('input', { bubbles: true }));
    };

    //funcao abrir modal
    const openModal = (card) => {
        //preenche dados do modal
        document.getElementById('modal-titulo').textContent = card.dataset.titulo;
        document.getElementById('modal-ano').textContent = card.dataset.ano;
        document.getElementById('modal-gravadora').textContent = card.dataset.gravadora;
        document.getElementById('modal-duracao').textContent = card.dataset.duracao;
        document.getElementById('modal-capa').src = card.dataset.capa;

        //fundo desfocado
        modal.querySelector('.modal-content').style.setProperty('--modal-bg-image', `url('${card.dataset.capa}')`);
        
        //artista clicavel
        const modalArtista = document.getElementById('modal-artista');
        modalArtista.innerHTML = `<a href="#" class="modal-search-link" data-search-term="${card.dataset.artista}">${card.dataset.artista}</a>`;

        //generos clicaveis
        const generos = card.dataset.genero.split(',').map(g => g.trim()).filter(g => g);
        const generoDisplay = document.getElementById('modal-genero-display');
        generoDisplay.innerHTML = '<strong>Gêneros:</strong> '; //reinicia
        generos.forEach((genero, index) => {
            generoDisplay.innerHTML += `<a href="#" class="modal-search-link" data-search-term="${genero}">${genero}</a>`;
            if (index < generos.length - 1) {
                generoDisplay.innerHTML += ', ';
            }
        });

        //limpa e preenche formatos disponiveis
        const formatosList = document.getElementById('modal-formatos');
        formatosList.innerHTML = '';
        const formatos = JSON.parse(card.dataset.formatos);

        formatos.forEach(formato => {
            const li = document.createElement('li');
            
            const isOutOfStock = parseInt(formato.quantidade_estoque, 10) <= 0;
            const precoFormatado = parseFloat(formato.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

            let actionHtml = '';
            if (userRole === 'guest') {
                actionHtml = `<a href="/ekhos/login/login.php" class="btn-add-cart-login">Login para Comprar</a>`;
            } else if (userRole === 'client') {
                if (isOutOfStock) {
                    actionHtml = `<span class="out-of-stock">Esgotado</span>`;
                } else {
                    actionHtml = `
                        <div class="formato-acao-cliente">
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn quantity-minus" aria-label="Diminuir">-</button>
                                <input type="number" class="quantidade-input" value="1" min="1" max="${formato.quantidade_estoque}" readonly>
                                <button type="button" class="quantity-btn quantity-plus" aria-label="Aumentar">+</button>
                            </div>
                            <button class="btn-add-cart-icon" data-album-id="${card.dataset.id}" data-formato-tipo="${formato.tipo}" title="Adicionar ao carrinho">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                            </button>
                        </div>
                    `;
                }
            }

            li.innerHTML = `
                <div class="formato-info">
                    <span class="formato-tipo">${formato.tipo.charAt(0).toUpperCase() + formato.tipo.slice(1).replace('_', ' ')}</span>
                    <span class="formato-preco">${precoFormatado}</span>
                </div>
                ${actionHtml}
            `;
            formatosList.appendChild(li);
        });

        modal.style.display = 'flex';
        setTimeout(() => {
            modal.style.opacity = '1';
            modal.querySelector('.modal-content').style.transform = 'scale(1)';
            body.classList.add('modal-open');
        }, 10);
    };

    //funcao fechar modal
    const closeModal = () => {
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
        body.classList.remove('modal-open');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    };

    //listener abrir modal
    if (albumGrid) {
        albumGrid.addEventListener('click', function(e) {
            const card = e.target.closest('.album-card');
            //nao abre o modal se o clique foi no link de editar do admin
            if (card && !e.target.closest('.edit-link')) {
                openModal(card);
            }
        });
    }

    //listener fechar modal
    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    //listener busca modal
    modal.addEventListener('click', function(e) {
        const searchLink = e.target.closest('.modal-search-link');
        if (searchLink) {
            e.preventDefault();
            const searchTerm = searchLink.dataset.searchTerm;
            triggerSearch(searchTerm);
        }
    });

    //listener botoes quant modal
    modal.addEventListener('click', function(e) {
        const minusBtn = e.target.closest('.quantity-minus');
        const plusBtn = e.target.closest('.quantity-plus');
        
        if (!minusBtn && !plusBtn) return;

        const controlDiv = e.target.closest('.quantity-control');
        const input = controlDiv.querySelector('.quantidade-input');
        let currentValue = parseInt(input.value, 10);
        const min = parseInt(input.min, 10);
        const max = parseInt(input.max, 10);

        if (minusBtn && currentValue > min) {
            input.value = currentValue - 1;
        }

        if (plusBtn && currentValue < max) {
            input.value = currentValue + 1;
        }
        //change pra logicas dependentes funcionarem
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    //listener add carrinho
    modal.addEventListener('click', function(e) {
        const addButton = e.target.closest('.btn-add-cart-icon');
        if (addButton) {
            const albumId = addButton.dataset.albumId;
            const formatoTipo = addButton.dataset.formatoTipo;
            const quantidadeInput = addButton.parentElement.querySelector('.quantidade-input');
            const quantidade = quantidadeInput.value;

            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('album_id', albumId);
            formData.append('formato_tipo', formatoTipo);
            formData.append('quantidade', quantidade);

            //chamada carrinho
            fetch('/ekhos/carrinho/cart_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    closeModal();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro ao adicionar o item ao carrinho.');
            });
        }
    });

    //fechar modal esc
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });

    //busca tempo real
    searchBar.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase().trim();
        const albumCards = albumGrid.querySelectorAll('.album-card');

        albumCards.forEach(card => {
            const title = card.dataset.titulo.toLowerCase();
            const artist = card.dataset.artista.toLowerCase();
            const genre = card.dataset.genero.toLowerCase();

            if (title.includes(searchTerm) || artist.includes(searchTerm) || genre.includes(searchTerm)) {
                card.style.display = 'block'; //mostra card
            } else {
                card.style.display = 'none'; //oculta card
            }
        });
    });
});