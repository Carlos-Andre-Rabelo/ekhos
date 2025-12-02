// --- Função para criar notificações Toast (movida para o escopo global) ---
const showToast = (message, type = 'success') => {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    // Remove o toast após a animação terminar (4 segundos)
    setTimeout(() => {
        toast.remove();
    }, 4000);
};

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('album-modal');
    const modalClose = modal.querySelector('.modal-close');
    const albumGrid = document.getElementById('album-grid');
    const body = document.body;
    const searchBar = document.getElementById('search-bar');
    const userRole = body.dataset.userRole;

    // Função auxiliar para acionar a busca (movida para o escopo global do script)
    const triggerSearch = (term) => {
        closeModal();
        searchBar.value = term;
        // Dispara o evento 'input' para que o listener da busca seja ativado
        searchBar.dispatchEvent(new Event('input', { bubbles: true }));
    };

    // --- Lógica de verificação de estoque e carrinho ---
    let userCart = {}; // Armazena o estado do carrinho do usuário

    // Função para buscar o estado atual do carrinho do usuário via AJAX
    const fetchCartState = async () => {
        if (userRole !== 'client') return;
        try {
            const response = await fetch('carrinho/cart_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_cart_state'
            });
            const data = await response.json();
            if (data.status === 'success') {
                userCart = data.cart;
            }
        } catch (error) {
            console.error('Erro ao buscar estado do carrinho:', error);
        }
    };

    // Função para atualizar o estado do botão de adicionar ao carrinho
    const atualizarEstadoBotao = (li) => {
        const addButton = li.querySelector('.btn-add-cart-icon');
        const input = li.querySelector('.quantidade-input');
        if (!addButton || !input) return;

        const albumId = addButton.dataset.albumId;
        const formatoTipo = addButton.dataset.formatoTipo;
        const quantidadeDesejada = parseInt(input.value, 10);
        const estoqueDisponivel = parseInt(input.max, 10);
        const quantidadeNoCarrinho = userCart[`${albumId}-${formatoTipo}`] || 0;

        addButton.disabled = (quantidadeNoCarrinho + quantidadeDesejada) > estoqueDisponivel;
    };

    // Função para abrir o modal
    const openModal = (card) => {
        // Preenche os dados básicos do modal
        document.getElementById('modal-titulo').textContent = card.dataset.titulo;
        document.getElementById('modal-ano').textContent = card.dataset.ano;
        document.getElementById('modal-gravadora').textContent = card.dataset.gravadora;
        document.getElementById('modal-duracao').textContent = card.dataset.duracao;
        document.getElementById('modal-capa').src = card.dataset.capa;

        // Define a variável CSS para a imagem de fundo desfocada.
        modal.querySelector('.modal-content').style.setProperty('--modal-bg-image', `url('${card.dataset.capa}')`);
        
        // Preenche o artista como um link clicável
        const modalArtista = document.getElementById('modal-artista');
        modalArtista.innerHTML = `<a href="#" class="modal-search-link" data-search-term="${card.dataset.artista}">${card.dataset.artista}</a>`;

        // Preenche os gêneros como links clicáveis
        const generos = card.dataset.genero.split(',').map(g => g.trim()).filter(g => g);
        const generoDisplay = document.getElementById('modal-genero-display');
        generoDisplay.innerHTML = '<strong>Gêneros:</strong> '; // Limpa e reinicia
        generos.forEach((genero, index) => {
            generoDisplay.innerHTML += `<a href="#" class="modal-search-link" data-search-term="${genero}">${genero}</a>`;
            if (index < generos.length - 1) {
                generoDisplay.innerHTML += ', ';
            }
        });

        // Limpa e preenche os formatos disponíveis
        const formatosList = document.getElementById('modal-formatos');
        formatosList.innerHTML = '';
        const formatos = JSON.parse(card.dataset.formatos);

        formatos.forEach(formato => {
            const li = document.createElement('li');
            
            const isOutOfStock = parseInt(formato.quantidade_estoque, 10) <= 0;
            const precoFormatado = parseFloat(formato.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

            let actionHtml = '';
            if (userRole === 'guest') {
                actionHtml = `<a href="login/login.php" class="btn-add-cart-login">Login para Comprar</a>`;
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

            if (userRole === 'client' && !isOutOfStock) {
                atualizarEstadoBotao(li);
            }
        });

        modal.style.display = 'flex';
        setTimeout(() => {
            modal.style.opacity = '1';
            modal.querySelector('.modal-content').style.transform = 'scale(1)';
            body.classList.add('modal-open');
        }, 10);
    };

    // Busca o estado do carrinho assim que a página carrega, se for cliente
    if (userRole === 'client') {
        fetchCartState();
    }

    // Função para fechar o modal
    const closeModal = () => {
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
        body.classList.remove('modal-open');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    };

    // Event listener para abrir o modal
    if (albumGrid) {
        albumGrid.addEventListener('click', function(e) {
            const card = e.target.closest('.album-card');
            // Não abre o modal se o clique foi no link de editar do admin
            if (card && !e.target.closest('.edit-link')) {
                openModal(card);
            }
        });
    }

    // Event listener para fechar o modal
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

    // Event listener para os links de busca dentro do modal
    modal.addEventListener('click', function(e) {
        const searchLink = e.target.closest('.modal-search-link');
        if (searchLink) {
            e.preventDefault(); // Impede que o link '#' navegue
            const searchTerm = searchLink.dataset.searchTerm;
            triggerSearch(searchTerm);
        }
    });

    // Event listener para os botões de quantidade (+/-) no modal
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

        // Atualiza o estado do botão de adicionar ao carrinho
        const li = e.target.closest('li');
        if(li) atualizarEstadoBotao(li);
    });

    // Event listener para adicionar ao carrinho (delegação de evento)
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

            // **A CHAMADA CORRIGIDA**
            fetch('carrinho/cart_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Exibe a notificação de sucesso sem fechar o modal
                    showToast(data.message);
                    // Atualiza o estado do carrinho local e o estado do botão
                    fetchCartState().then(() => {
                        const li = addButton.closest('li');
                        if(li) atualizarEstadoBotao(li);
                    });
                } else {
                    // Exibe a notificação de erro
                    showToast('Erro: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro ao adicionar o item ao carrinho.');
            });
        }
    });

    // Fechar modal com a tecla Esc
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });

    // --- FUNCIONALIDADE DE BUSCA EM TEMPO REAL ---
    searchBar.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase().trim();
        const albumCards = albumGrid.querySelectorAll('.album-card');

        albumCards.forEach(card => {
            const title = card.dataset.titulo.toLowerCase();
            const artist = card.dataset.artista.toLowerCase();
            const genre = card.dataset.genero.toLowerCase();

            if (title.includes(searchTerm) || artist.includes(searchTerm) || genre.includes(searchTerm)) {
                card.style.display = 'block'; // Mostra o card
            } else {
                card.style.display = 'none'; // Oculta o card
            }
        });
    });
});