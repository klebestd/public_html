<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$msg = null;
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) {
        $msg = ["type" => "danger", "text" => "O nome de usuário é obrigatório."];
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $msg = ["type" => "danger", "text" => "Nome de usuário já existe."];
        } else {
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$username, $passwordHash, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$username, $userId]);
            }
            $_SESSION['username'] = $username;
            $msg = ["type" => "success", "text" => "Perfil atualizado com sucesso!"];
        }
    }
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil - AutoDJ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
        <a class="nav-link" href="playlists.php"><i class="fas fa-list me-1"></i> Playlists</a>
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
        <a class="nav-link active" href="edit_user.php"><i class="fas fa-user-edit me-1"></i> Perfil</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Sair</a>
    </li>
</ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="my-4 text-center text-white"><i class="fas fa-user-edit me-2"></i> Editar Perfil</h1>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-user me-2"></i> Atualizar Dados do Usuário
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                        <?= $msg['text'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nome de Usuário:</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nova Senha (deixe em branco para não alterar):</label>
                        <input type="password" name="password" id="password" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Salvar</button>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Voltar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>