<?php
session_start();

// Conexão SQLite
$db = new PDO("sqlite:agenda.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Criar tabelas se não existirem
$db->exec("CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT,
    email TEXT UNIQUE,
    senha TEXT,
    telefone TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS agendamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER,
    data TEXT,
    hora TEXT,
    FOREIGN KEY(cliente_id) REFERENCES clientes(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS admin (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT,
    senha TEXT
)");

// Criar admin padrão se não existir
$stmt = $db->query("SELECT COUNT(*) FROM admin");
if($stmt->fetchColumn() == 0){
    $db->exec("INSERT INTO admin (usuario, senha) VALUES ('admin', '12345')");
}

// Funções para login
function isAdmin(){
    return isset($_SESSION['admin_usuario']);
}
function isCliente(){
    return isset($_SESSION['cliente_id']);
}

// Ações de cadastro, login e agendamento
if(isset($_POST['acao'])){
    $acao = $_POST['acao'];

    // Cadastro Cliente
    if($acao == 'cadastrar_cliente'){
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $telefone = $_POST['telefone'];
        $stmt = $db->prepare("INSERT INTO clientes (nome,email,senha,telefone) VALUES (?,?,?,?)");
        $stmt->execute([$nome,$email,$senha,$telefone]);
        echo "<script>alert('Cadastro realizado!');</script>";
    }

    // Login Cliente
    if($acao == 'login_cliente'){
        $email = $_POST['email'];
        $senha = $_POST['senha'];
        $stmt = $db->prepare("SELECT * FROM clientes WHERE email=?");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if($cliente && password_verify($senha,$cliente['senha'])){
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nome'] = $cliente['nome'];
        } else {
            echo "<script>alert('Email ou senha incorretos');</script>";
        }
    }

    // Login Admin
    if($acao == 'login_admin'){
        $usuario = $_POST['usuario'];
        $senha = $_POST['senha'];
        $stmt = $db->prepare("SELECT * FROM admin WHERE usuario=? AND senha=?");
        $stmt->execute([$usuario,$senha]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if($admin){
            $_SESSION['admin_usuario'] = $admin['usuario'];
        } else {
            echo "<script>alert('Usuário ou senha incorretos');</script>";
        }
    }

    // Agendar corte com contagem de pessoas no mesmo dia
    if($acao == 'agendar' && isCliente()){
        $data = $_POST['data'];
        $hora = $_POST['hora'];

        // Contar quantos agendamentos já existem na mesma data
        $stmt_count = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE data=?");
        $stmt_count->execute([$data]);
        $quantidade = $stmt_count->fetchColumn();

        echo "<script>alert('Já existem $quantidade pessoas agendadas para o dia $data');</script>";

        // Salvar o agendamento
        $stmt = $db->prepare("INSERT INTO agendamentos (cliente_id,data,hora) VALUES (?,?,?)");
        $stmt->execute([$_SESSION['cliente_id'],$data,$hora]);

        echo "<script>alert('Agendamento realizado!');</script>";
    }

    // Logout
    if($acao == 'logout'){
        session_destroy();
        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Agendamento Longuinho</title>
<style>
body { background:#000; color:#fff; font-family:Arial, sans-serif; text-align:center; padding:20px;}
h1,h2 { margin:20px;}
input, select { padding:10px; margin:5px; width:200px;}
button { padding:10px 20px; margin:5px; cursor:pointer;}
a { color:#fff; text-decoration:none; margin:5px; display:inline-block;}
.container { max-width:400px; margin:auto; }
table { width:100%; border-collapse: collapse; margin-top:20px;}
th, td { border:1px solid #fff; padding:8px;}
th { background:#333;}
</style>
</head>
<body>
<div class="container">
<h1>Agendamento Longuinho</h1>

<?php if(!isCliente() && !isAdmin()): ?>
    <!-- Página inicial -->
    <a href="#login_cliente">Longuinho do Cliente</a> | 
    <a href="#login_admin">Longuinho do Admin</a> | 
    <a href="#cadastro">Cadastrar Cliente</a>

    <!-- Cadastro Cliente -->
    <div id="cadastro">
        <h2>Cadastro Cliente</h2>
        <form method="post">
            <input type="hidden" name="acao" value="cadastrar_cliente">
            <input type="text" name="nome" placeholder="Nome" required><br>
            <input type="email" name="email" placeholder="Email" required><br>
            <input type="password" name="senha" placeholder="Senha" required><br>
            <input type="text" name="telefone" placeholder="Telefone" required><br>
            <button type="submit">Cadastrar</button>
        </form>
    </div>

    <!-- Login Cliente -->
    <div id="login_cliente">
        <h2>Login Cliente</h2>
        <form method="post">
            <input type="hidden" name="acao" value="login_cliente">
            <input type="email" name="email" placeholder="Email" required><br>
            <input type="password" name="senha" placeholder="Senha" required><br>
            <button type="submit">Entrar</button>
        </form>
    </div>

    <!-- Login Admin -->
    <div id="login_admin">
        <h2>Login Admin</h2>
        <form method="post">
            <input type="hidden" name="acao" value="login_admin">
            <input type="text" name="usuario" placeholder="Usuário" required><br>
            <input type="password" name="senha" placeholder="Senha" required><br>
            <button type="submit">Entrar</button>
        </form>
    </div>

<?php elseif(isCliente()): ?>
    <!-- Dashboard Cliente -->
    <h2>Bem-vindo, <?php echo $_SESSION['cliente_nome']; ?></h2>
    <form method="post">
        <input type="hidden" name="acao" value="logout">
        <button type="submit">Sair</button>
    </form>

    <h3>Agendar Corte</h3>
    <form method="post">
        <input type="hidden" name="acao" value="agendar">
        <input type="date" name="data" required><br>
        <input type="time" name="hora" required><br>
        <button type="submit">Agendar</button>
    </form>

    <h3>Seus Agendamentos</h3>
    <table>
        <tr><th>Data</th><th>Hora</th></tr>
        <?php
        $stmt = $db->prepare("SELECT * FROM agendamentos WHERE cliente_id=? ORDER BY data,hora");
        $stmt->execute([$_SESSION['cliente_id']]);
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            echo "<tr><td>{$row['data']}</td><td>{$row['hora']}</td></tr>";
        }
        ?>
    </table>

<?php elseif(isAdmin()): ?>
    <!-- Dashboard Admin -->
    <h2>Painel do Cabeleireiro (Admin)</h2>
    <form method="post">
        <input type="hidden" name="acao" value="logout">
        <button type="submit">Sair</button>
    </form>

    <h3>Agendamentos</h3>
    <table>
        <tr><th>Cliente</th><th>Telefone</th><th>Data</th><th>Hora</th></tr>
        <?php
        $stmt = $db->query("SELECT a.data,a.hora,c.nome,c.telefone FROM agendamentos a JOIN clientes c ON a.cliente_id=c.id ORDER BY a.data,a.hora");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            echo "<tr><td>{$row['nome']}</td><td>{$row['telefone']}</td><td>{$row['data']}</td><td>{$row['hora']}</td></tr>";
        }
        ?>
    </table>
<?php endif; ?>

</div>
</body>
</html>
