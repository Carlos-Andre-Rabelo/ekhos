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

// --- Função para atualizar contador do carrinho ---
const updateCartCount = () => {
    fetch('carrinho/cart_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=count'
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const badges = document.querySelectorAll('.cart-badge');
                const count = data.count || 0;

                badges.forEach(badge => {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            }
        })
        .catch(error => console.error('Erro ao atualizar contador do carrinho:', error));
};

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('album-modal');
    const modalClose = modal.querySelector('.modal-close');
    const albumGrid = document.getElementById('album-grid');
    const body = document.body;
    const searchBar = document.getElementById('search-bar');
    const userRole = body.dataset.userRole;

    // --- Sistema de Tema ---
    const themeToggle = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || 'dark';

    // Aplica o tema salvo
    document.documentElement.setAttribute('data-theme', currentTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const theme = document.documentElement.getAttribute('data-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }

    // --- Sistema de Filtros e Visualização ---
    const toggleFiltersBtn = document.getElementById('toggle-filters');
    const filtersPanel = document.getElementById('filters-panel');
    const viewGridBtn = document.getElementById('view-grid');
    const viewListBtn = document.getElementById('view-list');
    const clearFiltersBtn = document.getElementById('clear-filters');

    const filterGenero = document.getElementById('filter-genero');
    const filterFormato = document.getElementById('filter-formato');
    const filterPreco = document.getElementById('filter-preco');
    const filterAno = document.getElementById('filter-ano');
    const filterOrdenacao = document.getElementById('filter-ordenacao');

    const searchSuggestions = document.getElementById('search-suggestions');
    let searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]');

    // Sistema de Autocomplete
    const buildSearchIndex = () => {
        const index = {
            titulos: new Set(),
            artistas: new Set(),
            generos: new Set()
        };

        const albumCards = albumGrid.querySelectorAll('.album-card');
        albumCards.forEach(card => {
            index.titulos.add(card.dataset.titulo);
            index.artistas.add(card.dataset.artista);
            const generos = card.dataset.genero.split(',').map(g => g.trim()).filter(g => g);
            generos.forEach(g => index.generos.add(g));
        });

        return {
            titulos: Array.from(index.titulos),
            artistas: Array.from(index.artistas),
            generos: Array.from(index.generos)
        };
    };

    const searchIndex = buildSearchIndex();

    const showSuggestions = (term) => {
        if (!term || term.length < 2) {
            searchSuggestions.classList.remove('show');
            return;
        }

        const termLower = term.toLowerCase();
        const suggestions = [];

        // Busca em títulos
        searchIndex.titulos.forEach(titulo => {
            if (titulo.toLowerCase().includes(termLower)) {
                suggestions.push({ type: 'Álbum', text: titulo });
            }
        });

        // Busca em artistas
        searchIndex.artistas.forEach(artista => {
            if (artista.toLowerCase().includes(termLower)) {
                suggestions.push({ type: 'Artista', text: artista });
            }
        });

        // Busca em gêneros
        searchIndex.generos.forEach(genero => {
            if (genero.toLowerCase().includes(termLower)) {
                suggestions.push({ type: 'Gênero', text: genero });
            }
        });

        // Limita a 8 sugestões
        const limitedSuggestions = suggestions.slice(0, 8);

        if (limitedSuggestions.length > 0) {
            searchSuggestions.innerHTML = limitedSuggestions.map(s => {
                const highlighted = s.text.replace(
                    new RegExp(`(${term})`, 'gi'),
                    '<span class="suggestion-highlight">$1</span>'
                );
                return `
                    <div class="suggestion-item" data-value="${s.text}">
                        <div class="suggestion-type">${s.type}</div>
                        <div class="suggestion-text">${highlighted}</div>
                    </div>
                `;
            }).join('');
            searchSuggestions.classList.add('show');
        } else {
            searchSuggestions.classList.remove('show');
        }
    };

    // Event listener para mostrar sugestões
    searchBar.addEventListener('input', function () {
        showSuggestions(this.value);
    });

    // Event listener para clicar em uma sugestão
    searchSuggestions.addEventListener('click', function (e) {
        const item = e.target.closest('.suggestion-item');
        if (item) {
            const value = item.dataset.value;
            searchBar.value = value;
            searchSuggestions.classList.remove('show');

            // Salva no histórico
            if (!searchHistory.includes(value)) {
                searchHistory.unshift(value);
                searchHistory = searchHistory.slice(0, 10); // Mantém apenas 10
                localStorage.setItem('searchHistory', JSON.stringify(searchHistory));
            }

            applyFilters();
        }
    });

    // Fecha sugestões ao clicar fora
    document.addEventListener('click', function (e) {
        if (!searchBar.contains(e.target) && !searchSuggestions.contains(e.target)) {
            searchSuggestions.classList.remove('show');
        }
    });

    // Toggle do painel de filtros
    if (toggleFiltersBtn && filtersPanel) {
        toggleFiltersBtn.addEventListener('click', function () {
            filtersPanel.classList.toggle('show');
            this.classList.toggle('active');
        });
    }

    // Alternância de visualização (grade/lista)
    if (viewGridBtn && viewListBtn) {
        viewGridBtn.addEventListener('click', function () {
            albumGrid.classList.remove('list-view');
            viewGridBtn.classList.add('active');
            viewListBtn.classList.remove('active');
            localStorage.setItem('viewMode', 'grid');
        });

        viewListBtn.addEventListener('click', function () {
            albumGrid.classList.add('list-view');
            viewListBtn.classList.add('active');
            viewGridBtn.classList.remove('active');
            localStorage.setItem('viewMode', 'list');
        });

        // Restaura a visualização salva
        const savedView = localStorage.getItem('viewMode');
        if (savedView === 'list') {
            viewListBtn.click();
        }
    }

    // Filtro de Favoritos
    const showFavoritesBtn = document.getElementById('show-favorites');
    let showingFavoritesOnly = false;

    if (showFavoritesBtn && userRole === 'client') {
        showFavoritesBtn.addEventListener('click', function () {
            showingFavoritesOnly = !showingFavoritesOnly;
            this.classList.toggle('active');
            applyFilters();
        });
    }

    // Função para aplicar filtros
    const applyFilters = () => {
        const albumCards = albumGrid.querySelectorAll('.album-card');
        const searchTerm = searchBar.value.toLowerCase().trim();
        const generoSelecionado = filterGenero?.value.toLowerCase();
        const formatoSelecionado = filterFormato?.value.toLowerCase();
        const precoSelecionado = filterPreco?.value;
        const anoSelecionado = filterAno?.value;

        let visibleCards = [];

        albumCards.forEach(card => {
            const title = card.dataset.titulo.toLowerCase();
            const artist = card.dataset.artista.toLowerCase();
            const genre = card.dataset.genero.toLowerCase();
            const year = card.dataset.ano;
            const formatos = JSON.parse(card.dataset.formatos || '[]');
            const albumId = parseInt(card.dataset.id);

            // Filtro de busca
            const matchSearch = !searchTerm ||
                title.includes(searchTerm) ||
                artist.includes(searchTerm) ||
                genre.includes(searchTerm);

            // Filtro de gênero
            const matchGenero = !generoSelecionado || genre.includes(generoSelecionado);

            // Filtro de formato
            const matchFormato = !formatoSelecionado ||
                formatos.some(f => f.tipo === formatoSelecionado);

            // Filtro de preço
            let matchPreco = true;
            if (precoSelecionado && formatos.length > 0) {
                const [min, max] = precoSelecionado.split('-').map(Number);
                const precoMinimo = Math.min(...formatos.map(f => parseFloat(f.preco)));
                matchPreco = precoMinimo >= min && precoMinimo <= max;
            }

            // Filtro de ano
            let matchAno = true;
            if (anoSelecionado) {
                if (anoSelecionado === '2020-antes') {
                    matchAno = parseInt(year) <= 2020;
                } else {
                    matchAno = year === anoSelecionado;
                }
            }

            // Filtro de favoritos
            const matchFavoritos = !showingFavoritesOnly || userFavorites.includes(albumId);

            // Aplica visibilidade
            if (matchSearch && matchGenero && matchFormato && matchPreco && matchAno && matchFavoritos) {
                card.style.display = 'block';
                visibleCards.push(card);
            } else {
                card.style.display = 'none';
            }
        });

        // Aplica ordenação
        applyOrdering(visibleCards);
    };

    // Função para ordenar cards
    const applyOrdering = (cards) => {
        if (!filterOrdenacao?.value) return;

        const [campo, direcao] = filterOrdenacao.value.split('-');

        cards.sort((a, b) => {
            let valorA, valorB;

            switch (campo) {
                case 'artista':
                    valorA = a.dataset.artista.toLowerCase();
                    valorB = b.dataset.artista.toLowerCase();
                    break;
                case 'titulo':
                    valorA = a.dataset.titulo.toLowerCase();
                    valorB = b.dataset.titulo.toLowerCase();
                    break;
                case 'ano':
                    valorA = parseInt(a.dataset.ano);
                    valorB = parseInt(b.dataset.ano);
                    break;
                case 'preco':
                    const formatosA = JSON.parse(a.dataset.formatos || '[]');
                    const formatosB = JSON.parse(b.dataset.formatos || '[]');
                    valorA = formatosA.length > 0 ? Math.min(...formatosA.map(f => parseFloat(f.preco))) : 0;
                    valorB = formatosB.length > 0 ? Math.min(...formatosB.map(f => parseFloat(f.preco))) : 0;
                    break;
            }

            if (direcao === 'asc') {
                return valorA > valorB ? 1 : -1;
            } else {
                return valorA < valorB ? 1 : -1;
            }
        });

        // Reordena os cards no DOM
        cards.forEach(card => albumGrid.appendChild(card));
    };

    // Event listeners para filtros
    [filterGenero, filterFormato, filterPreco, filterAno, filterOrdenacao].forEach(filter => {
        if (filter) {
            filter.addEventListener('change', applyFilters);
        }
    });

    // Limpar filtros
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function () {
            if (filterGenero) filterGenero.value = '';
            if (filterFormato) filterFormato.value = '';
            if (filterPreco) filterPreco.value = '';
            if (filterAno) filterAno.value = '';
            if (filterOrdenacao) filterOrdenacao.value = 'artista-asc';
            searchBar.value = '';
            applyFilters();
        });
    }

    // Função auxiliar para acionar a busca (movida para o escopo global do script)
    const triggerSearch = (term) => {
        closeModal();
        searchBar.value = term;
        // Dispara o evento 'input' para que o listener da busca seja ativado
        searchBar.dispatchEvent(new Event('input', { bubbles: true }));
        applyFilters(); // Também aplica os filtros
    };

    // --- Lógica de verificação de estoque e carrinho ---
    let userCart = {}; // Armazena o estado do carrinho do usuário
    let userFavorites = []; // Armazena os favoritos do usuário

    // Função para buscar os favoritos do usuário
    const fetchUserFavorites = async () => {
        if (userRole !== 'client') return;
        try {
            const response = await fetch('favoritos_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_favorites'
            });
            const data = await response.json();
            if (data.status === 'success') {
                userFavorites = data.favorites;
                updateFavoriteButtons();
            }
        } catch (error) {
            console.error('Erro ao buscar favoritos:', error);
        }
    };

    // Função para atualizar os botões de favorito
    const updateFavoriteButtons = () => {
        console.log('Atualizando botões de favoritos. Total de favoritos:', userFavorites.length);
        console.log('IDs dos favoritos:', userFavorites);

        document.querySelectorAll('.btn-favorite').forEach(btn => {
            const albumId = parseInt(btn.dataset.albumId);
            if (userFavorites.includes(albumId)) {
                btn.classList.add('favorited');
                btn.title = 'Remover dos favoritos';
            } else {
                btn.classList.remove('favorited');
                btn.title = 'Adicionar aos favoritos';
            }
        });
    };

    // Event listener para botões de favorito
    document.addEventListener('click', function (e) {
        const favoriteBtn = e.target.closest('.btn-favorite');
        if (favoriteBtn) {
            e.stopPropagation(); // Impede que abra o modal

            const albumId = favoriteBtn.dataset.albumId;
            console.log('Clicou no botão de favorito. Album ID:', albumId);

            fetch('favoritos_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle&album_id=${albumId}`
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Resposta do servidor:', data);
                    if (data.status === 'success') {
                        showToast(data.message);
                        if (data.isFavorite) {
                            userFavorites.push(parseInt(albumId));
                        } else {
                            userFavorites = userFavorites.filter(id => id !== parseInt(albumId));
                        }
                        console.log('Favoritos após atualização:', userFavorites);
                        updateFavoriteButtons();
                    } else {
                        showToast('Erro: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro ao atualizar favoritos', 'error');
                });
        }
    });

    // Busca favoritos ao carregar
    if (userRole === 'client') {
        fetchUserFavorites();
    }

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
        albumGrid.addEventListener('click', function (e) {
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
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    // Event listener para os links de busca dentro do modal
    modal.addEventListener('click', function (e) {
        const searchLink = e.target.closest('.modal-search-link');
        if (searchLink) {
            e.preventDefault(); // Impede que o link '#' navegue
            const searchTerm = searchLink.dataset.searchTerm;
            triggerSearch(searchTerm);
        }
    });

    // Event listener para os botões de quantidade (+/-) no modal
    modal.addEventListener('click', function (e) {
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
        if (li) atualizarEstadoBotao(li);
    });

    // Event listener para adicionar ao carrinho (delegação de evento)
    modal.addEventListener('click', function (e) {
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
                        // Atualiza o contador do carrinho
                        updateCartCount();
                        // Atualiza o estado do carrinho local e o estado do botão
                        fetchCartState().then(() => {
                            const li = addButton.closest('li');
                            if (li) atualizarEstadoBotao(li);
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
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });

    // --- FUNCIONALIDADE DE BUSCA EM TEMPO REAL ---
    searchBar.addEventListener('input', applyFilters); // Usa a função de filtros unificada
});