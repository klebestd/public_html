<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$msg = null;
$musicas = [];
$comerciais = [];

try {
    // Carregar músicas
    $stmt = $pdo->query("SELECT id, nome FROM arquivos WHERE tipo = 'musica' ORDER BY nome ASC");
    $musicas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar comerciais
    $stmt = $pdo->query("SELECT id, nome FROM arquivos WHERE tipo = 'comercial' ORDER BY nome ASC");
    $comerciais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $msg = ["type" => "danger", "text" => "Erro ao carregar arquivos: " . $e->getMessage()];
}

// Processar criação de playlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $horario_inicio = $_POST['horario_inicio'] ?? '';
    $horario_fim = $_POST['horario_fim'] ?? '';
    $dias_semana = isset($_POST['dias_semana']) ? implode(',', $_POST['dias_semana']) : '';
    $arquivos = json_decode($_POST['arquivos'] ?? '[]', true);

    if (empty($nome) || empty($horario_inicio) || empty($horario_fim) || empty($dias_semana) || empty($arquivos)) {
        $msg = ["type" => "danger", "text" => "Por favor, preencha todos os campos e selecione pelo menos um arquivo."];
    } else {
        try {
            $m3uFile = PLAYLISTS_DIR . preg_replace('/[^A-Za-z0-9\-]/', '_', $nome) . '_' . time() . '.m3u';
            
			
			$m3uContent = "#EXTM3U\n";
foreach ($arquivos as $arquivoId) {
    $stmt = $pdo->prepare("SELECT caminho FROM arquivos WHERE id = ?");
    $stmt->execute([$arquivoId]);
    $arquivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($arquivo) {
        // Normalizar o caminho para minúsculas
        $caminho = str_replace('/Uploads/', '/uploads/', $arquivo['caminho']);
        $m3uContent .= BASE_URL . $caminho . "\n";
    }
}
			
			
			
            file_put_contents($m3uFile, $m3uContent);

            $stmt = $pdo->prepare("INSERT INTO playlists (nome, caminho, horario_inicio, horario_fim, dias_semana, data_criacao) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nome, $m3uFile, $horario_inicio, $horario_fim, $dias_semana]);
            $playlistId = $pdo->lastInsertId();

            // Inserir arquivos com ordem
            $ordem = 1;
            foreach ($arquivos as $arquivoId) {
                $stmt = $pdo->prepare("INSERT INTO playlist_arquivos (playlist_id, arquivo_id, ordem) VALUES (?, ?, ?)");
                $stmt->execute([$playlistId, $arquivoId, $ordem]);
                $ordem++;
            }

            if (CACHE_ENABLED && function_exists('apcu_delete')) {
                apcu_delete('active_playlist');
            }

            $msg = ["type" => "success", "text" => "Playlist criada com sucesso!"];
        } catch (Exception $e) {
            error_log("Erro ao criar playlist: " . $e->getMessage());
            if (file_exists($m3uFile)) {
                unlink($m3uFile);
            }
            $msg = ["type" => "danger", "text" => "Erro ao criar playlist: " . $e->getMessage()];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Playlist - AutoDJ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .drag-area {
            border: 2px dashed #007bff;
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
            border-radius: 5px;
            min-height: 100px;
            transition: border-color 0.3s, background-color 0.3s;
        }
        .drag-area.dragover {
            border-color: #28a745;
            background-color: #e9ecef;
        }
        .drag-area i {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 10px;
        }
        .drag-area p {
            margin: 0;
            color: #6c757d;
        }
        #selected-arquivos {
            list-style: none;
            padding: 0;
        }
        #selected-arquivos li {
            padding: 10px;
            margin-bottom: 5px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: move;
            display: flex;
            align-items: center;
        }
        #selected-arquivos li i {
            margin-right: 10px;
            color: #007bff;
        }
        #selected-arquivos li:hover {
            background-color: #f1f1f1;
        }
        /* Estilo para Dias da Semana */
        .dias-semana-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .dias-semana-container .form-check {
            margin-bottom: 0;
        }
        .dias-semana-container .form-check-label {
            margin-left: 5px;
        }
        /* Estilo para listas de Músicas e Comerciais */
        .musicas-list-container,
        .comerciais-list-container,
        .selected-arquivos-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .musicas-list-container::-webkit-scrollbar,
        .comerciais-list-container::-webkit-scrollbar,
        .selected-arquivos-container::-webkit-scrollbar {
            width: 8px;
        }
        .musicas-list-container::-webkit-scrollbar-thumb,
        .comerciais-list-container::-webkit-scrollbar-thumb,
        .selected-arquivos-container::-webkit-scrollbar-thumb {
            background-color: #007bff;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            .dias-semana-container {
                flex-direction: column;
                gap: 10px;
            }
            .musicas-list-container,
            .comerciais-list-container,
            .selected-arquivos-container {
                max-height: 300px;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-music me-2"></i> AutoDJ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php"><i class="fas fa-upload me-1"></i> Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="playlists.php"><i class="fas fa-list me-1"></i> Playlists</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="comerciais.php"><i class="fas fa-bullhorn me-1"></i> Comerciais</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="musicas.php"><i class="fas fa-music me-1"></i> Músicas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="lista_playlists.php"><i class="fas fa-list-alt me-1"></i> Listar Playlists</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="edit_user.php"><i class="fas fa-user-edit me-1"></i> Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="my-4 text-center text-white"><i class="fas fa-list me-2"></i> Criar Playlist</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle me-2"></i> Nova Playlist
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                        <?= $msg['text'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form id="playlist-form" method="POST">
                    <!-- Informações da Playlist -->
                    <div class="mb-4">
                        <h5><i class="fas fa-info-circle me-2"></i> Informações da Playlist</h5>
                        <div class="mb-3">
                            <label class="form-label">Dias da Semana</label>
                            <div class="dias-semana-container">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="seg" id="seg">
                                    <label class="form-check-label" for="seg">Segunda</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="ter" id="ter">
                                    <label class="form-check-label" for="ter">Terça</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="qua" id="qua">
                                    <label class="form-check-label" for="qua">Quarta</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="qui" id="qui">
                                    <label class="form-check-label" for="qui">Quinta</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="sex" id="sex">
                                    <label class="form-check-label" for="sex">Sexta</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="sab" id="sab">
                                    <label class="form-check-label" for="sab">Sábado</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" value="dom" id="dom">
                                    <label class="form-check-label" for="dom">Domingo</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome da Playlist</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="horario_inicio" class="form-label">Horário Início</label>
                                <input type="time" class="form-control" id="horario_inicio" name="horario_inicio" required>
                            </div>
                            <div class="col">
                                <label for="horario_fim" class="form-label">Horário Fim</label>
                                <input type="time" class="form-control" id="horario_fim" name="horario_fim" required>
                            </div>
                        </div>
                    </div>

                    <!-- Seções de Músicas, Comerciais e Arquivos Selecionados -->
                    <div class="row">
                        <!-- Músicas (Esquerda) -->
                        <div class="col-md-4">
                            <h5><i class="fas fa-music me-2"></i> Músicas</h5>
                            <div class="musicas-list-container">
                                <ul id="musicas-list" class="list-group">
                                    <?php foreach ($musicas as $musica): ?>
                                        <li class="list-group-item" data-id="<?= $musica['id'] ?>" data-nome="<?= htmlspecialchars($musica['nome']) ?>" draggable="true">
                                            <i class="fas fa-music me-2"></i> <?= htmlspecialchars($musica['nome']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <!-- Comerciais (Centro) -->
                        <div class="col-md-4">
                            <h5><i class="fas fa-bullhorn me-2"></i> Comerciais</h5>
                            <div class="comerciais-list-container">
                                <ul id="comerciais-list" class="list-group">
                                    <?php foreach ($comerciais as $comercial): ?>
                                        <li class="list-group-item" data-id="<?= $comercial['id'] ?>" data-nome="<?= htmlspecialchars($comercial['nome']) ?>" draggable="true">
                                            <i class="fas fa-bullhorn me-2"></i> <?= htmlspecialchars($comercial['nome']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <!-- Arquivos Selecionados (Direita) -->
                        <div class="col-md-4">
                            <h5><i class="fas fa-check-circle me-2"></i> Arquivos Selecionados</h5>
                            <div class="selected-arquivos-container">
                                <div class="drag-area" id="drag-area">
                                    <i class="fas fa-arrow-down"></i>
                                    <p>Arraste músicas e comerciais aqui</p>
                                </div>
                                <ul id="selected-arquivos" class="sortable-list"></ul>
                                <input type="hidden" name="arquivos" id="arquivos-input">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-2"></i> Criar Playlist</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        const musicasList = document.getElementById('musicas-list');
        const comerciaisList = document.getElementById('comerciais-list');
        const selectedArquivos = document.getElementById('selected-arquivos');
        const dragArea = document.getElementById('drag-area');
        const arquivosInput = document.getElementById('arquivos-input');
        let selectedArquivosArray = [];

        // Inicializar SortableJS para reordenação
        new Sortable(selectedArquivos, {
            animation: 150,
            handle: 'li',
            onEnd: () => {
                updateSelectedArquivosArray();
            }
        });

        // Função para atualizar o array de arquivos selecionados
        function updateSelectedArquivosArray() {
            selectedArquivosArray = Array.from(selectedArquivos.children).map(item => parseInt(item.dataset.id));
            arquivosInput.value = JSON.stringify(selectedArquivosArray);
        }

        // Eventos de drag and drop
        ['dragstart', 'dragend', 'dragover', 'dragenter', 'dragleave', 'drop'].forEach(event => {
            dragArea.addEventListener(event, e => {
                e.preventDefault();
                if (['dragover', 'dragenter'].includes(event)) {
                    dragArea.classList.add('dragover');
                } else if (['dragleave', 'dragend', 'drop'].includes(event)) {
                    dragArea.classList.remove('dragover');
                }
            });

            musicasList.addEventListener(event, e => {
                if (event === 'dragstart') {
                    const item = e.target.closest('li');
                    if (item) e.dataTransfer.setData('text/plain', JSON.stringify({
                        id: item.dataset.id,
                        nome: item.dataset.nome,
                        tipo: 'musica'
                    }));
                }
            });

            comerciaisList.addEventListener(event, e => {
                if (event === 'dragstart') {
                    const item = e.target.closest('li');
                    if (item) e.dataTransfer.setData('text/plain', JSON.stringify({
                        id: item.dataset.id,
                        nome: item.dataset.nome,
                        tipo: 'comercial'
                    }));
                }
            });
        });

        dragArea.addEventListener('drop', e => {
            const data = JSON.parse(e.dataTransfer.getData('text/plain'));
            if (!selectedArquivosArray.includes(parseInt(data.id))) {
                const li = document.createElement('li');
                li.dataset.id = data.id;
                li.dataset.nome = data.nome;
                li.innerHTML = `<i class="fas ${data.tipo === 'musica' ? 'fa-music' : 'fa-bullhorn'} me-2"></i> ${data.nome}`;
                selectedArquivos.appendChild(li);
                selectedArquivosArray.push(parseInt(data.id));
                arquivosInput.value = JSON.stringify(selectedArquivosArray);
            }
        });

        // Inicializar formulário
        document.getElementById('playlist-form').addEventListener('submit', () => {
            arquivosInput.value = JSON.stringify(selectedArquivosArray);
        });
    </script>
</body>
</html>