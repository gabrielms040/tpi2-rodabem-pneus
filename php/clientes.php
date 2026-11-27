<?php
// cadastro de clientes: listar, buscar, salvar (incluir ou alterar) e excluir
require_once 'sessao.php';

exigirLogin();

$acao = acaoPedida();

// lista todos os clientes para a tabela da tela
if ($acao === 'listar') {
    $linhas = consultar(
        'SELECT c.id, c.nome, c.cpf, c.data_nascimento, c.telefone, c.cidade,
                (SELECT COUNT(*) FROM veiculos v WHERE v.cliente_id = c.id) AS veiculos
           FROM clientes c
          ORDER BY c.nome'
    );

    $lista = [];
    foreach ($linhas as $c) {
        $idade = (new DateTime($c['data_nascimento']))->diff(new DateTime())->y;
        $lista[] = [
            'id' => $c['id'],
            'Nome' => $c['nome'],
            'CPF' => formatarCpf($c['cpf']),
            'Idade' => "$idade anos",
            'Telefone' => formatarTelefone($c['telefone']),
            'Cidade' => $c['cidade'],
            'Veículos' => $c['veiculos']
        ];
    }
    responder(true, 'Clientes cadastrados', [], $lista);
}

// devolve um cliente para preencher o formulario na hora de alterar
if ($acao === 'buscar') {
    $c = consultarUm('SELECT * FROM clientes WHERE id = ?', [idPedido()]);
    if ($c === null) {
        responder(false, 'Cliente não encontrado.', ['O cliente pode ter sido excluído.']);
    }
    responder(true, 'Cliente encontrado.', [], [
        'id' => $c['id'],
        'nome' => $c['nome'],
        'cpf' => formatarCpf($c['cpf']),
        'data_nascimento' => $c['data_nascimento'],
        'telefone' => formatarTelefone($c['telefone']),
        'email' => $c['email'],
        'cidade' => $c['cidade']
    ]);
}

aceitarSomentePost();

if ($acao === 'excluir') {
    excluirRegistro('clientes', idPedido(), 'Este cliente tem veículos ou vendas cadastrados. Exclua esses registros antes.');
}

// a partir daqui: salvar o formulario
$id = idPedido();
$erros = [];

verificarObrigatorios([
    'nome' => 'Nome completo',
    'cpf' => 'CPF',
    'data_nascimento' => 'Data de nascimento',
    'telefone' => 'Telefone',
    'email' => 'E-mail',
    'cidade' => 'Cidade'
], $erros);

$nome = campo('nome');
$cpf = campo('cpf');
$nascimento = campo('data_nascimento');
$telefone = campo('telefone');
$email = campo('email');
$cidade = campo('cidade');

if ($nome !== '' && count(explode(' ', $nome)) < 2) {
    $erros[] = 'Informe o nome e o sobrenome do cliente.';
}

if ($cpf !== '' && !validarCpf($cpf)) {
    $erros[] = 'O CPF informado não é válido.';
}

if ($telefone !== '' && !validarTelefone($telefone)) {
    $erros[] = 'O telefone deve ter DDD e 8 ou 9 dígitos.';
}

if ($email !== '' && !validarEmail($email)) {
    $erros[] = 'O e-mail informado não é válido.';
}

// logica adicional: calcula a idade e exige que o cliente seja maior de idade
$idade = null;
if ($nascimento !== '') {
    $data = DateTime::createFromFormat('Y-m-d', $nascimento);
    if (!$data) {
        $erros[] = 'A data de nascimento não é válida.';
    } else {
        $hoje = new DateTime();
        if ($data > $hoje) {
            $erros[] = 'A data de nascimento não pode ser no futuro.';
        } else {
            $idade = $data->diff($hoje)->y;
            if ($idade < 18) {
                $erros[] = "O cliente tem $idade anos. Só é possível cadastrar clientes com 18 anos ou mais.";
            }
        }
    }
}

// o CPF nao pode estar em outro cliente (na alteracao, ignora o proprio cliente)
if ($cpf !== '' && validarCpf($cpf)) {
    $outro = consultarUm('SELECT nome FROM clientes WHERE cpf = ? AND id <> ?', [somenteNumeros($cpf), $id]);
    if ($outro !== null) {
        $erros[] = 'Este CPF já está cadastrado para ' . $outro['nome'] . '.';
    }
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível salvar o cliente.', $erros);
}

// deixa o nome com a primeira letra de cada palavra maiuscula
$nomeFormatado = mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE, 'UTF-8');

$valores = [
    $nomeFormatado,
    somenteNumeros($cpf),
    $nascimento,
    somenteNumeros($telefone),
    strtolower($email),
    $cidade
];

if ($id === 0) {
    $id = inserir(
        'INSERT INTO clientes (nome, cpf, data_nascimento, telefone, email, cidade) VALUES (?, ?, ?, ?, ?, ?)',
        $valores
    );
    $mensagem = 'Cliente cadastrado com sucesso.';
} else {
    $valores[] = $id;
    $alterados = executar(
        'UPDATE clientes SET nome = ?, cpf = ?, data_nascimento = ?, telefone = ?, email = ?, cidade = ? WHERE id = ?',
        $valores
    );
    if ($alterados === 0) {
        responder(false, 'Não foi possível salvar o cliente.', ['Cliente não encontrado.']);
    }
    $mensagem = 'Cliente alterado com sucesso.';
}

responder(true, $mensagem, [], [
    'Código' => $id,
    'Nome' => $nomeFormatado,
    'CPF' => formatarCpf($cpf),
    'Idade' => "$idade anos",
    'Telefone' => formatarTelefone($telefone),
    'E-mail' => strtolower($email),
    'Cidade' => $cidade
]);
