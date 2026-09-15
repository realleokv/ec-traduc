<?php
session_start();
if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

// 2. Processa o envio do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Inclua seu arquivo de conexão com o banco de dados
    if (file_exists('conexao.php')) {
        require_once 'conexao.php';
    } else {
        // Fallback de conexão caso ainda não tenha conexao.php
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=seu_banco;charset=utf8', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erro na conexão com o banco: " . $e->getMessage());
        }
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['password'] ?? '';

    if ($email && !empty($senha)) {
        // Busca o usuário/admin pelo e-mail
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica a senha (compatível com senhas criptografadas por password_hash)
        // Verifica a senha
        if ($usuario && (password_verify($senha, $usuario['senha']) || $senha === $usuario['senha'])) {
            
            // Login bem-sucedido! Salva os dados na sessão
            $_SESSION['admin_logado'] = true;
            $_SESSION['user_id']      = $usuario['id'];
            $_SESSION['user_nome']    = $usuario['nome'] ?? 'Elisa Caires';
            $_SESSION['admin_email']  = $usuario['email'];

            // Redireciona para o Painel da Elisa
            header('Location: dashboard.php');
            exit;
        } else {
            $erro = 'E-mail ou senha incorretos!';
        }
    } else {
        $erro = 'Por favor, preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Elisa Caires</title>
    <link rel="stylesheet" href="login.css">
    <!-- Ícones FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilo rápido para mensagem de erro de login */
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Globos de iluminação de fundo -->
    <div class="glow-sphere glow-1"></div>
    <div class="glow-sphere glow-2"></div>

    <div class="login-card">
        <!-- Foto / Avatar da Elisa -->
        <div class="avatar-container">
            <img src="img/elisa.png" alt="Elisa Caires" onerror="this.src='https://ui-avatars.com/api/?name=Elisa+Caires&background=5b318f&color=fff'">
        </div>

        <h2>Bem-vinda de volta, Elisa! ✨</h2>
        <p class="subtitle">Acesse seu painel de agendamentos</p>

        <!-- Exibe mensagem de erro se o login falhar -->
        <?php if (!empty($erro)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="input-group">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" name="email" placeholder="Seu e-mail" required autocomplete="off" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Sua senha" required>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" checked>
                    <span class="checkmark"></span>
                    Lembrar de mim
                </label>
                <a href="#" class="forgot-pass">Esqueceu a senha?</a>
            </div>

            <button type="submit" class="btn-login">ENTRAR</button>
        </form>
    </div>

</body>
</html>