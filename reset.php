<?php
require_once 'conexao.php';

// A senha que queremos definir
$senha_limpa = '$08012011$1l$2e$3o$4k$5v$';

// O PHP cria o hash perfeito e compatível com a sua versão
$senha_nova_criptografada = password_hash($senha_limpa, PASSWORD_DEFAULT);
$email = 'elisa.ectraducoes@gmail.com';

try {
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE email = :email");
    $stmt->execute([
        ':senha' => $senha_nova_criptografada, 
        ':email' => $email
    ]);
    
    echo "<h1>Sucesso!</h1>";
    echo "<p>A senha foi atualizada no banco de dados.</p>";
    echo "<p>E-mail: <strong>" . htmlspecialchars($email) . "</strong></p>";
    echo "<p>Senha: <strong>" . htmlspecialchars($senha_limpa) . "</strong></p>";
    echo "<a href='login.php'>Voltar para o Login</a>";

} catch (PDOException $e) {
    echo "Erro ao atualizar: " . $e->getMessage();
}
?>