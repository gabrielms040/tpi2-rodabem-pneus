<?php
// confere o login e a senha no banco e guarda o usuario na sessao
require_once 'sessao.php';

aceitarSomentePost();

$erros = [];
verificarObrigatorios([
    'login' => 'Usuário',
    'senha' => 'Senha'
], $erros);

if (count($erros) > 0) {
    responder(false, 'Não foi possível entrar.', $erros);
}

$login = strtolower(campo('login'));
$senha = $_POST['senha']; // a senha nao passa pelo trim, espaco tambem conta

$usuario = consultarUm(
    'SELECT id, nome, login, senha_hash, perfil, ativo FROM usuarios WHERE login = ?',
    [$login]
);

// a mesma mensagem para usuario inexistente e senha errada,
// assim ninguem descobre quais logins existem
if ($usuario === null || !password_verify($senha, $usuario['senha_hash'])) {
    responder(false, 'Não foi possível entrar.', ['Usuário ou senha incorretos.']);
}

if (!$usuario['ativo']) {
    responder(false, 'Não foi possível entrar.', ['Este usuário está desativado. Procure o administrador.']);
}

// gera um novo id de sessao depois do login (protege contra roubo de sessao)
session_regenerate_id(true);

$_SESSION['usuario'] = [
    'id' => (int) $usuario['id'],
    'nome' => $usuario['nome'],
    'login' => $usuario['login'],
    'perfil' => $usuario['perfil']
];

executar('UPDATE usuarios SET ultimo_acesso = CURRENT_TIMESTAMP WHERE id = ?', [$usuario['id']]);

responder(true, 'Bem-vindo, ' . $usuario['nome'] . '!', [], $_SESSION['usuario']);
