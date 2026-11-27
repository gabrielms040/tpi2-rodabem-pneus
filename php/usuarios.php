<?php
// cadastro dos usuarios que acessam o sistema (CRUD)
// so o administrador pode mexer nos usuarios
require_once 'sessao.php';

exigirAdministrador();

$acao = acaoPedida();
$logado = usuarioLogado();

if ($acao === 'listar') {
    $linhas = consultar(
        'SELECT id, nome, login, email, perfil, ativo, ultimo_acesso FROM usuarios ORDER BY nome'
    );

    $lista = [];
    foreach ($linhas as $u) {
        $acesso = 'Nunca acessou';
        if ($u['ultimo_acesso'] !== null) {
            $acesso = (new DateTime($u['ultimo_acesso']))->format('d/m/Y H:i');
        }
        $lista[] = [
            'id' => $u['id'],
            'Nome' => $u['nome'],
            'Usuário' => $u['login'],
            'E-mail' => $u['email'],
            'Perfil' => ucfirst($u['perfil']),
            'Situação' => $u['ativo'] ? 'Ativo' : 'Desativado',
            'Último acesso' => $acesso
        ];
    }
    responder(true, 'Usuários cadastrados', [], $lista);
}

if ($acao === 'buscar') {
    // a senha nunca volta para a tela
    $u = consultarUm('SELECT id, nome, login, email, perfil, ativo FROM usuarios WHERE id = ?', [idPedido()]);
    if ($u === null) {
        responder(false, 'Usuário não encontrado.', ['O usuário pode ter sido excluído.']);
    }
    responder(true, 'Usuário encontrado.', [], [
        'id' => $u['id'],
        'nome' => $u['nome'],
        'login' => $u['login'],
        'email' => $u['email'],
        'perfil' => $u['perfil'],
        'ativo' => $u['ativo'] ? 'sim' : 'nao',
        'senha' => '',
        'confirmar_senha' => ''
    ]);
}

aceitarSomentePost();

if ($acao === 'excluir') {
    if (idPedido() === $logado['id']) {
        responder(false, 'Não foi possível excluir.', ['Você não pode excluir o seu próprio usuário.']);
    }
    excluirRegistro('usuarios', idPedido(), 'Não foi possível excluir este usuário.');
}

$id = idPedido();
$novo = ($id === 0);
$erros = [];

$obrigatorios = [
    'nome' => 'Nome',
    'login' => 'Usuário',
    'email' => 'E-mail',
    'perfil' => 'Perfil'
];
// no cadastro novo a senha e obrigatoria; na alteracao, em branco mantem a atual
if ($novo) {
    $obrigatorios['senha'] = 'Senha';
}
verificarObrigatorios($obrigatorios, $erros);

$nome = campo('nome');
$login = strtolower(campo('login'));
$email = campo('email');
$perfil = campo('perfil');
$ativo = campo('ativo') !== 'nao';
$senha = isset($_POST['senha']) ? $_POST['senha'] : '';
$confirmacao = isset($_POST['confirmar_senha']) ? $_POST['confirmar_senha'] : '';

if ($login !== '' && !preg_match('/^[a-z0-9._]{3,30}$/', $login)) {
    $erros[] = 'O usuário deve ter de 3 a 30 caracteres: letras sem acento, números, ponto ou sublinhado.';
}

if ($login !== '') {
    $outro = consultarUm('SELECT nome FROM usuarios WHERE login = ? AND id <> ?', [$login, $id]);
    if ($outro !== null) {
        $erros[] = 'O usuário "' . $login . '" já existe.';
    }
}

if ($email !== '' && !validarEmail($email)) {
    $erros[] = 'O e-mail informado não é válido.';
}

if ($perfil !== '' && !in_array($perfil, ['administrador', 'operador'])) {
    $erros[] = 'Perfil inválido.';
}

// logica adicional 1: regra de senha forte (minimo 6 caracteres com letra e numero)
if ($senha !== '') {
    if (strlen($senha) < 6 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/[0-9]/', $senha)) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres, com letras e números.';
    }
    if ($senha !== $confirmacao) {
        $erros[] = 'A confirmação não é igual à senha.';
    }
}

// logica adicional 2: o administrador logado nao pode se desativar nem perder o perfil,
// senao o sistema poderia ficar sem nenhum administrador
if (!$novo && $id === $logado['id']) {
    if (!$ativo) {
        $erros[] = 'Você não pode desativar o seu próprio usuário.';
    }
    if ($perfil !== '' && $perfil !== 'administrador') {
        $erros[] = 'Você não pode tirar o perfil de administrador do seu próprio usuário.';
    }
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível salvar o usuário.', $erros);
}

if ($novo) {
    $id = inserir(
        'INSERT INTO usuarios (nome, login, email, senha_hash, perfil, ativo) VALUES (?, ?, ?, ?, ?, ?)',
        [$nome, $login, strtolower($email), password_hash($senha, PASSWORD_DEFAULT), $perfil, $ativo ? 'true' : 'false']
    );
    $mensagem = 'Usuário cadastrado com sucesso.';
} else {
    $alterados = executar(
        'UPDATE usuarios SET nome = ?, login = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?',
        [$nome, $login, strtolower($email), $perfil, $ativo ? 'true' : 'false', $id]
    );
    if ($alterados === 0) {
        responder(false, 'Não foi possível salvar o usuário.', ['Usuário não encontrado.']);
    }
    // a senha so e trocada quando foi preenchida
    if ($senha !== '') {
        executar('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $id]);
    }
    // se alterou o proprio cadastro, atualiza o nome que aparece no topo
    if ($id === $logado['id']) {
        $_SESSION['usuario']['nome'] = $nome;
        $_SESSION['usuario']['login'] = $login;
    }
    $mensagem = 'Usuário alterado com sucesso.';
}

$dados = [
    'Código' => $id,
    'Nome' => $nome,
    'Usuário' => $login,
    'E-mail' => strtolower($email),
    'Perfil' => ucfirst($perfil),
    'Situação' => $ativo ? 'Ativo (pode entrar no sistema)' : 'Desativado (não consegue entrar)'
];
if (!$novo) {
    $dados['Senha'] = ($senha !== '') ? 'Alterada' : 'Mantida a atual';
}

responder(true, $mensagem, [], $dados);
