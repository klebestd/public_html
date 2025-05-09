// Função para exibir alertas na página
function showAlert(containerId, type, message) {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error(`Container de alerta '${containerId}' não encontrado.`);
        return;
    }
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 300);
        }
    }, 5000);
}

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
    }
}

function stopAudio() {
    const audio = document.getElementById('audio-player');
    if (audio) {
        audio.pause();
        audio.currentTime = 0;
        updateStreamStatus('stopped');
        showAlert('alert-container', 'success', 'Streaming parado com sucesso!');
    }
}

function skipTrack() {
    fetch('stream.php?action=skip', { method: 'POST' })
        .then(response => {
            if (response.ok) {
                reloadStream();
                showAlert('alert-container', 'success', 'Música pulada com sucesso!');
            } else {
                showAlert('alert-container', 'danger', 'Erro ao pular música.');
            }
        })
        .catch(() => showAlert('alert-container', 'danger', 'Erro ao pular música.'));
}

// Copiar link do stream
function copyStreamLink() {
    const streamLink = document.getElementById('stream-link');
    streamLink.select();
    navigator.clipboard.writeText(streamLink.value)
        .then(() => showAlert('alert-container', 'success', 'Link do stream copiado com sucesso!'))
        .catch(() => showAlert('alert-container', 'danger', 'Erro ao copiar o link. Copie manualmente.'));
}

// Upload com barra de progresso
function setupUploadProgress() {
    const form = document.getElementById('upload-form');
    if (!form) {
        console.error('Formulário de upload não encontrado.');
        return;
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        console.log('Formulário de upload submetido.');

        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const uploadButton = form.querySelector('button[type="submit"]');

        if (!progressContainer || !progressBar || !uploadButton) {
            console.error('Elementos de progresso ou botão não encontrados.');
            return;
        }

        uploadButton.disabled = true;
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressBar.setAttribute('aria-valuenow', 0);

        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = `${percent}%`;
                progressBar.textContent = `${percent}%`;
                progressBar.setAttribute('aria-valuenow', percent);
                console.log(`Progresso do upload: ${percent}%`);
            } else {
                console.warn('Progresso do upload não calculável.');
            }
        });

        xhr.addEventListener('load', () => {
            console.log('Upload concluído, resposta:', xhr.responseText);
            progressContainer.style.display = 'none';
            uploadButton.disabled = false;
            try {
                const response = JSON.parse(xhr.responseText);
                showAlert('alert-container', response.type, response.message);
                if (response.type === 'success') {
                    form.reset();
                }
            } catch (e) {
                console.error('Erro ao processar resposta:', e);
                showAlert('alert-container', 'danger', 'Erro ao processar a resposta do servidor.');
            }
        });

        xhr.addEventListener('error', () => {
            console.error('Erro durante o upload.');
            progressContainer.style.display = 'none';
            uploadButton.disabled = false;
            showAlert('alert-container', 'danger', 'Erro no upload. Tente novamente.');
        });

        xhr.open('POST', form.action);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    });
}

// Playlist creation com feedback
function setupPlaylistForm() {
    const form = document.getElementById('playlist-form');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        console.log('Formulário de playlist submetido.');
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            showAlert('alert-container', data.type, data.message);
            if (data.type === 'success') {
                form.reset();
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(error => {
            console.error('Erro ao criar playlist:', error);
            showAlert('alert-container', 'danger', 'Erro ao criar playlist. Tente novamente.');
        });
    });
}

// Drag-and-drop otimizado
function setupDragAndDrop() {
    const availableSongs = document.getElementById('available-songs');
    const playlistSongs = document.getElementById('playlist-songs');
    const sequenceContainer = document.getElementById('sequence-container');

    if (!availableSongs || !playlistSongs || !sequenceContainer) return;

    function updateSequence() {
        const items = playlistSongs.querySelectorAll('.drag-item');
        sequenceContainer.innerHTML = '';
        items.forEach(item => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'sequencia[]';
            input.value = item.dataset.id;
            sequenceContainer.appendChild(input);
        });
    }

    const lists = [availableSongs, playlistSongs];
    lists.forEach(list => {
        list.addEventListener('dragover', e => {
            e.preventDefault();
            list.classList.add('over');
        });

        list.addEventListener('dragleave', () => {
            list.classList.remove('over');
        });

        list.addEventListener('drop', e => {
            e.preventDefault();
            list.classList.remove('over');
            const id = e.dataTransfer.getData('text/plain');
            const sourceList = document.querySelector(`.drag-item[data-id="${id}"]`).parentElement;

            if (list === playlistSongs && sourceList === availableSongs) {
                const existing = playlistSongs.querySelector(`[data-id="${id}"]`);
                if (existing) return;
                const item = document.querySelector(`.drag-item[data-id="${id}"]`);
                const clone = item.cloneNode(true);
                clone.classList.add('playlist');
                list.appendChild(clone);
                updateSequence();
            } else if (list === playlistSongs && sourceList === playlistSongs) {
                const draggedItem = document.querySelector(`.drag-item[data-id="${id}"]`);
                const dropY = e.clientY;
                const items = Array.from(list.children);
                const closest = items.find(item => {
                    const rect = item.getBoundingClientRect();
                    return dropY < rect.top + rect.height / 2;
                });
                if (closest) {
                    list.insertBefore(draggedItem, closest);
                } else {
                    list.appendChild(draggedItem);
                }
                updateSequence();
            }
        });
    });

    document.querySelectorAll('.drag-item').forEach(item => {
        item.addEventListener('dragstart', e => {
            item.classList.add('dragging');
            e.dataTransfer.setData('text/plain', item.dataset.id);
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
        });
    });
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    setupDragAndDrop();
    setupUploadProgress();
    setupPlaylistForm();
});