<?php
session_start();

// CONFIGURAÇÃO DO ADMIN
$admin_user = "admin";
$admin_pass = "12345"; // você pode mudar depois ou usar variável de ambiente

// BANCO DE DADOS SQLITE
$db_path = __DIR__ . "/data/agenda.db";
$db = new PDO("sqlite:" . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tabelas
$db->exec("CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT UNIQUE,
    senha TEXT,
    telefone TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS agendamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER,
    data TEXT,
    FOREIGN KEY(cliente_id) REFERENCES clientes(id)
)");

// LOGIN CLIENTE
if (isset($_POST['login_cliente'])) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE usuario=? AND senha=?");
    $stmt->execute([$_POST['usuario'], $_POST['senha']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['cliente_id'] = $user['id'];
        $_SESSION['usuario'] = $user['usuario'];
    } else {
        $erro = "Usuário ou senha inválidos.";
    }
}

// CADASTRO CLIENTE
if (isset($_POST['cadastro_cliente'])) {
    try {
        $stmt = $db->prepare("INSERT INTO clientes (usuario, senha, telefone) VALUES (?,?,?)");
        $stmt->execute([$_POST['usuario'], $_POST['senha'], $_POST['telefone']]);
        $msg = "Cadastro realizado com sucesso!";
    } catch (Exception $e) {
        $erro = "Erro: usuário já existe.";
    }
}

// LOGIN ADMIN
if (isset($_POST['login_admin'])) {
    if ($_POST['usuario'] === $admin_user && $_POST['senha'] === $admin_pass) {
        $_SESSION['admin'] = true;
    } else {
        $erro = "Admin inválido.";
    }
}

// AGENDAR CORTE
if (isset($_POST['agendar']) && isset($_SESSION['cliente_id'])) {
    $data = $_POST['data'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE data=?");
    $stmt->execute([$data]);
    $quantos = $stmt->fetchColumn();

    if ($quantos >= 5) { // limite de 5 pessoas por dia (pode mudar)
        $erro = "Esse dia já está cheio.";
    } else {
        $stmt = $db->prepare("INSERT INTO agendamentos (cliente_id, data) VALUES (?, ?)");
        $stmt->execute([$_SESSION['cliente_id'], $data]);
        $msg = "Agendamento realizado com sucesso!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Agendamento de Corte</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #000;
      color: #fff;
      margin: 0;
      padding: 0;
    }
    .container {
      width: 90%;
      max-width: 900px;
      margin: auto;
      padding: 20px;
    }
    h2 {
      border-bottom: 2px solid #fff;
      padding-bottom: 5px;
      margin-top: 20px;
    }
    form {
      background: #111;
      padding: 15px;
      border-radius: 10px;
      margin-top: 10px;
      box-shadow: 0 0 10px #333;
    }
    input, button {
      display: block;
      width: 100%;
      margin: 8px 0;
      padding: 10px;
      border: none;
      border-radius: 5px;
    }
    input {
      background: #222;
      color: #fff;
    }
    button {
      background: #444;
      color: #fff;
      cursor: pointer;
      font-weight: bold;
    }
    button:hover {
      background: #666;
    }
    .msg { color: lightgreen; margin: 10px 0; }
    .erro { color: red; margin: 10px 0; }
  </style>
</head>
<body>
  <div class="container">
    <h1>💈 Sistema de Agendamento</h1>

    <?php if (isset($msg)) echo "<p class='msg'>$msg</p>"; ?>
    <?php if (isset($erro)) echo "<p class='erro'>$erro</p>"; ?>

    <!-- Login Cliente -->
    <h2>Login do Cliente</h2>
    <form method="post">
      <input type="text" name="usuario" placeholder="Usuário" required>
      <input type="password" name="senha" placeholder="Senha" required>
      <button type="submit" name="login_cliente">Entrar</button>
    </form>

    <!-- Cadastro Cliente -->
    <h2>Cadastro Cliente</h2>
    <form method="post">
      <input type="text" name="usuario" placeholder="Usuário" required>
      <input type="password" name="senha" placeholder="Senha" required>
      <input type="text" name="telefone" placeholder="Telefone" required>
      <button type="submit" name="cadastro_cliente">Cadastrar</button>
    </form>

    <!-- Login Admin -->
    <h2>Admin</h2>
    <form method="post">
      <input type="text" name="usuario" placeholder="Login" required>
      <input type="password" name="senha" placeholder="Senha" required>
      <button type="submit" name="login_admin">Entrar</button>
    </form>

    <?php
    // Cliente logado
    if (isset($_SESSION['cliente_id'])) {
        echo "<h2>Bem-vindo, ".htmlspecialchars($_SESSION['usuario'])."</h2>";
        echo '<form method="post">
                <input type="date" name="data" required>
                <button type="submit" name="agendar">Agendar Corte</button>
              </form>';
    }

    // Admin logado
    if (isset($_SESSION['admin'])) {
        echo "<h2>Painel do Admin</h2>";
        $res = $db->query("SELECT a.id, c.usuario, c.telefone, a.data 
                           FROM agendamentos a 
                           JOIN clientes c ON a.cliente_id=c.id 
                           ORDER BY a.data");
        echo "<ul>";
        foreach ($res as $row) {
            echo "<li>".htmlspecialchars($row['data'])." - ".htmlspecialchars($row['usuario'])." (".htmlspecialchars($row['telefone']).")</li>";
        }
        echo "</ul>";
    }
    ?>
  </div>
</body>
</html>
