document.addEventListener('DOMContentLoaded', function() {
    const tabNav = document.querySelector('.tab-nav');
    if (!tabNav) return; // Só executa se a navegação por abas existir

    const tabPanels = document.querySelectorAll('.tab-panel');
    const tabBtns = document.querySelectorAll('.tab-btn');

    tabNav.addEventListener('click', (e) => {
        const clickedBtn = e.target.closest('.tab-btn');
        if (!clickedBtn) return;

        const tabId = clickedBtn.dataset.tab;

        // Remove 'active' de todos os botões e painéis
        tabBtns.forEach(btn => btn.classList.remove('active'));
        tabPanels.forEach(panel => panel.classList.remove('active'));

        // Adiciona 'active' ao botão clicado e ao painel correspondente
        clickedBtn.classList.add('active');
        document.getElementById(`tab-${tabId}`).classList.add('active');
    });
});

function moveOrder(event, pedidoId, novoStatus) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('pedido_id', pedidoId);
    formData.append('status', novoStatus);

    const card = document.getElementById(`pedido-${pedidoId}`);
    // Obtém o status original diretamente do dataset do card, que é a fonte da verdade.
    const originalStatus = card.dataset.status;
    // Validação do código de rastreio
    if (novoStatus === 'pedido enviado') {
        const codigoRastreio = formData.get('codigo_rastreio').trim();
        if (!codigoRastreio) {
            showToast('Por favor, insira o código de rastreio.', 'error');
            return;
        }
    }

    fetch(`${API_BASE_URL}update_status.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw new Error(err.message || 'Erro na resposta do servidor') });
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');

            // Remove a mensagem de coluna vazia, se houver
            const novoStatusId = novoStatus.replace(/ /g, '_');
            const targetColumnList = document.querySelector(`#tab-${novoStatusId} .orders-list`);
            
            if (!targetColumnList) {
                console.error(`Erro crítico: A coluna de destino para o status "${novoStatus}" (ID: #tab-${novoStatusId}) não foi encontrada.`);
                showToast(`Erro: A coluna de destino não foi encontrada.`, 'error');
                return;
            }
            const emptyMsg = targetColumnList.querySelector('.empty-column-msg');
            if (emptyMsg) {
                emptyMsg.remove();
            }

            // Move o card para a nova coluna
            targetColumnList.prepend(card);

            // Atualiza o card para o novo estado
            updateCardUI(pedidoId, novoStatus, formData.get('codigo_rastreio'));

            // Adiciona mensagem de coluna vazia se a original ficou vazia
            const originalStatusId = originalStatus.replace(/ /g, '_');
            const sourceColumnList = document.querySelector(`#tab-${originalStatusId} .orders-list`);
            // Verifica se a coluna de origem existe e se não há mais order-cards dentro dela
            if (sourceColumnList && !sourceColumnList.querySelector('.order-card')) {
                const p = document.createElement('p');
                p.className = 'empty-column-msg';
                p.textContent = 'Nenhum pedido aqui.';
                sourceColumnList.appendChild(p);
            }
        } else {
            showToast('Erro: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        showToast(error.message || 'Ocorreu um erro de comunicação.', 'error');
    });
}

function updateCardUI(pedidoId, novoStatus, codigoRastreio) {
    const card = document.getElementById(`pedido-${pedidoId}`);
    if (!card) return;
    
    // Atualiza o data-status para refletir o novo estado. Essencial para a próxima movimentação.
    card.dataset.status = novoStatus;
    
    const actionContainer = card.querySelector('.status-action-form');
    if (!actionContainer) return;

    // Cria o novo HTML de ação com base no novo status.
    let newActionHTML = '';
    if (novoStatus === 'em preparação') {
        // Se o pedido está "Em preparação", o próximo passo é "Enviar Pedido".
        newActionHTML = `<form onsubmit="moveOrder(event, '${pedidoId}', 'pedido enviado')"><input type="text" name="codigo_rastreio" placeholder="Código de Rastreio" required><button type="submit" class="action-btn">Enviar Pedido</button></form>`;
    } else if (novoStatus === 'pedido enviado' && codigoRastreio) {
        // Se foi enviado, mostra o código de rastreio.
        newActionHTML = `<div class="order-status"><strong>Rastreio:</strong> ${codigoRastreio}</div>`;
    }
    
    // Substitui o conteúdo do contêiner de ação pelo novo HTML.
    actionContainer.innerHTML = newActionHTML;
}