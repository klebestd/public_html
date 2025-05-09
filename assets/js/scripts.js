// scripts.js

// Funções para o player
function updateStreamStatus(status) {
    const statusElement = document.getElementById('player-status');
    const statusText = document.getElementById('stream-status-text');
    if (statusElement && statusText) {
        statusElement.className = `player-status ${status}`;
        statusElement.innerHTML = `<i class="fas fa-${status === 'playing' ? 'play' : 'pause'}"></i> ${status === 'playing' ? 'Tocando' : 'Parado'}`;
        statusText.textContent = status === 'playing' ? 'Tocando' : 'Parada';
    }
}

function reloadStream() {
    const audio = document.getElementById('audio-player');
    if (audio) {
        audio.src = 'stream.php?t=' + new Date().getTime();
        audio.load();
        audio.play().catch(error => console.error('Erro ao reproduzir:', error));
        updateStreamStatus('playing');
    }
}

function stopAudio() {
    const audio = document.getElementById('audio-player');
    if (audio) {
        audio.pause();
        audio.currentTime = 0;
        audio.src = '';
        updateStreamStatus('stopped');
    }
}

// Copiar link do stream
function copyStreamLink() {
    const streamLink = document.getElementById('stream-link');
    if (streamLink) {
        streamLink.select();
        navigator.clipboard.writeText(streamLink.value)
            .then(() => showAlert('success', 'Link do stream copiado com sucesso!'))
            .catch(() => showAlert('danger', 'Erro ao copiar o link. Copie manualmente.'));
    }
}

// Exibir alertas
function showAlert(type, message) {
    const modal = document.getElementById('notification-modal');
    if (modal) {
        // Usar modal Bootstrap se presente (ex.: index.php)
        const modalMessage = document.getElementById('modal-message');
        const modalTitle = document.getElementById('modal-title');
        if (modalMessage && modalTitle) {
            modalTitle.textContent = type === 'success' ? 'Sucesso' : 'Erro';
            modalMessage.className = `alert alert-${type}`;
            modalMessage.textContent = message;

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            // Adicionar reload ao fechar o modal para mensagens específicas
            if (message === 'Streaming parado com sucesso!' || message === 'Streaming iniciado com sucesso!') {
                const closeBtn = modal.querySelector('.btn-secondary');
                if (closeBtn) {
                    const handler = () => {
                        window.location.reload();
                        closeBtn.removeEventListener('click', handler); // Remover após executar
                    };
                    closeBtn.addEventListener('click', handler);
                }

                // Também recarregar ao fechar o modal com o X
                modal.addEventListener('hidden.bs.modal', () => {
                    window.location.reload();
                }, { once: true });
            }
        }
    } else {
        // Fallback para alert dinâmico em outras páginas
        const cardBody = document.querySelector('.card-body');
        if (!cardBody) return;

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        cardBody.prepend(alertDiv);
        setTimeout(() => alertDiv.remove(), 3000);
    }
}

// Configurar drag-and-drop com SortableJS
function setupDragAndDrop() {
    const availableSongs = document.getElementById('musicas-disponiveis');
    const playlistSongs = document.getElementById('musicas-playlist');

    if (!availableSongs || !playlistSongs) return;

    new Sortable(availableSongs, {
        group: {
            name: 'shared',
            pull: 'clone',
            put: false
        },
        animation: 150,
        ghostClass: 'sortable-ghost'
    });

    new Sortable(playlistSongs, {
        group: {
            name: 'shared',
            pull: true,
            put: true
        },
        animation: 150,
        ghostClass: 'sortable-ghost',
        onSort: updateMusicasOrdenadas
    });
}

// Atualizar campo oculto com a ordem das músicas
function updateMusicasOrdenadas() {
    const playlistSongs = document.getElementById('musicas-playlist');
    const musicasOrdenadas = document.getElementById('musicas-ordenadas');
    if (!playlistSongs || !musicasOrdenadas) return;

    const items = playlistSongs.querySelectorAll('.sortable-item');
    const musicaIds = Array.from(items).map(item => item.dataset.id);
    musicasOrdenadas.value = JSON.stringify(musicaIds);
}

// Configurar exclusão de playlists
function setupDeletePlaylists() {
    const deleteButtons = document.querySelectorAll('.delete-playlist');
    if (!deleteButtons.length) return;

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    let currentPlaylistId = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            currentPlaylistId = this.dataset.id;
            const playlistNome = this.dataset.nome;
            const nomeSpan = document.getElementById('playlist-nome');
            if (nomeSpan) {
                nomeSpan.textContent = playlistNome;
            }
            deleteModal.show();
        });
    });

    const confirmDelete = document.getElementById('confirm-delete');
    if (confirmDelete) {
        confirmDelete.addEventListener('click', async () => {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete&playlist_id=${currentPlaylistId}`
                });
                const data = await response.json();
                if (data.status === 'success') {
                    const row = document.getElementById(`playlist-${currentPlaylistId}`);
                    if (row) row.remove();
                    showAlert('success', data.message);
                    deleteModal.hide();
                } else {
                    showAlert('danger', data.message);
                }
            } catch (error) {
                showAlert('danger', 'Erro ao excluir playlist: ' + error.message);
            }
        });
    }
}

// Configurar exclusão de músicas
function setupDeleteMusicas() {
    const deleteButtons = document.querySelectorAll('.delete-musica');
    if (!deleteButtons.length) return;

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    let currentMusicaId = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            currentMusicaId = this.dataset.id;
            const musicaNome = this.dataset.nome;
            const nomeSpan = document.getElementById('musica-nome');
            if (nomeSpan) {
                nomeSpan.textContent = musicaNome;
            }
            deleteModal.show();
        });
    });

    const confirmDelete = document.getElementById('confirm-delete');
    if (confirmDelete) {
        confirmDelete.addEventListener('click', async () => {
            try {
                const response = await fetch('musicas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete&musica_id=${currentMusicaId}`
                });
                const data = await response.json();
                if (data.status === 'success') {
                    const row = document.getElementById(`musica-${currentMusicaId}`);
                    if (row) row.remove();
                    showAlert('success', data.message);
                    deleteModal.hide();
                } else {
                    showAlert('danger', data.message);
                }
            } catch (error) {
                showAlert('danger', 'Erro ao excluir música: ' + error.message);
            }
        });
    }
}

// Configurar ações do streaming
function setupStreamActions() {
    const playBtn = document.getElementById('play-btn');
    const stopBtn = document.getElementById('stop-btn');
    const skipBtn = document.querySelector('.btn-skip');

    if (playBtn) {
        playBtn.addEventListener('click', async () => {
            const spinner = playBtn.querySelector('.spinner');
            if (spinner) spinner.classList.add('active');
            playBtn.disabled = true;

            try {
                const response = await fetch('stream.php?action=play', {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: AbortSignal.timeout(2000)
                });
                const data = await response.json();
                if (data.status === 'success') {
                    reloadStream();
                    showAlert('success', 'Streaming iniciado com sucesso!');
                } else {
                    showAlert('danger', data.message || 'Erro ao iniciar streaming.');
                }
            } catch (error) {
                showAlert('danger', 'Erro: ' + error.message);
            } finally {
                if (spinner) spinner.classList.remove('active');
                playBtn.disabled = false;
            }
        });
    }

    if (stopBtn) {
        stopBtn.addEventListener('click', async () => {
            const spinner = stopBtn.querySelector('.spinner');
            if (spinner) spinner.classList.add('active');
            stopBtn.disabled = true;

            try {
                const response = await fetch('stream.php?action=stop', {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: AbortSignal.timeout(2000)
                });
                const data = await response.json();
                if (data.status === 'success') {
                    stopAudio();
                    showAlert('success', 'Streaming parado com sucesso!');
                } else {
                    showAlert('danger', data.message || 'Erro ao parar streaming.');
                }
            } catch (error) {
                showAlert('danger', 'Erro: ' + error.message);
            } finally {
                if (spinner) spinner.classList.remove('active');
                stopBtn.disabled = false;
            }
        });
    }

    if (skipBtn) {
        skipBtn.addEventListener('click', async () => {
            try {
                const response = await fetch('stream.php?action=skip', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.status === 'success') {
                    reloadStream();
                    showAlert('success', 'Música pulada com sucesso!');
                } else {
                    showAlert('danger', 'Erro ao pular música.');
                }
            } catch (error) {
                showAlert('danger', 'Erro ao pular música: ' + error.message);
            }
        });
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('musicas-disponiveis') && document.getElementById('musicas-playlist')) {
        setupDragAndDrop();
    }

    setupDeletePlaylists();
    setupDeleteMusicas();
    setupStreamActions();

    const playlistForm = document.getElementById('playlist-form');
    if (playlistForm) {
        playlistForm.addEventListener('submit', (e) => {
            const musicasOrdenadas = document.getElementById('musicas-ordenadas');
            if (musicasOrdenadas && (!musicasOrdenadas.value || JSON.parse(musicasOrdenadas.value).length === 0)) {
                e.preventDefault();
                showAlert('danger', 'Selecione pelo menos uma música para a playlist.');
            }
        });
    }
});