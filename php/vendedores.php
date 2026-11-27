<?php
// cadastro de vendedores: listar, buscar, salvar (incluir ou alterar) e excluir
require_once 'sessao.php';

exigirLogin();

$acao = acaoPedida();

if ($acao === 'listar') {
    // o total vendido no mes e somado direto no banco
    $linhas = consultar(
        "SELECT ve.id, ve.nome, ve.cpf, ve.telefone, ve.comissao, ve.ativo,
                COALESCE(SUM(v.total) FILTER (
                    WHERE date_trunc('month', v.data_venda) = date_trunc('month', CURRENT_DATE)
                ), 0) AS vendido_mes
           FROM vendedores ve
           LEFT JOIN vendas v ON v.vendedor_id = ve.id
          GROUP BY ve.id
          ORDER BY ve.nome"
    );

    $lista = [];
    foreach ($linhas as $v) {
        $lista[] = [
            'id' => $v['id'],
            'Nome' => $v['nome'],
            'CPF' => formatarCpf($v['cpf']),
            'Telefone' => formatarTelefone($v['telefone']),
            'Comissão' => number_format($v['comissao'], 2, ',', '.') . '%',
            'Vendido no mês' => dinheiro($v['vendido_mes']),
            'Situação' => $v['ativo'] ? 'Ativo' : 'Inativo'
        ];
    }
    responder(true, 'Vendedores cadastrados', [], $lista);
}

// usado pela tela de vendas para montar o select de vendedores
if ($acao === 'ativos') {
    $linhas = consultar('SELECT id, nome FROM vendedores WHERE ativo ORDER BY nome');
    responder(true, 'Vendedores ativos', [], $linhas);
}

if ($acao === 'buscar') {
    $v = consultarUm('SELECT * FROM vendedores WHERE id = ?', [idPedido()]);
    if ($v === null) {
        responder(false, 'Vendedor não encontrado.', ['O vendedor pode ter sido excluído.']);
    }
    responder(true, 'Vendedor encontrado.', [], [
        'id' => $v['id'],
        'nome' => $v['nome'],
        'cpf' => formatarCpf($v['cpf']),
        'telefone' => formatarTelefone($v['telefone']),
        'email' => $v['email'],
        'comissao' => number_format($v['comissao'], 2, ',', ''),
        'ativo' => $v['ativo'] ? 'sim' : 'nao'
    ]);
}

aceitarSomentePost();

if ($acao === 'excluir') {
    excluirRegistro('vendedores', idPedido(), 'Este vendedor já tem vendas registradas. Em vez de excluir, marque-o como inativo.');
}

$id = idPedido();
$erros = [];

verificarObrigatorios([
    'nome' => 'Nome completo',
    'cpf' => 'CPF',
    'telefone' => 'Telefone',
    'email' => 'E-mail',
    'comissao' => 'Comissão'
], $erros);

$nome = campo('nome');
$cpf = campo('cpf');
$telefone = campo('telefone');
$email = campo('email');
$comissao = paraNumero(campo('comissao'));
$ativo = campo('ativo') !== 'nao';

if ($nome !== '' && count(explode(' ', $nome)) < 2) {
    $erros[] = 'Informe o nome e o sobrenome do vendedor.';
}

if ($cpf !== '' && !validarCpf($cpf)) {
    $erros[] = 'O CPF informado não é válido.';
}

if ($cpf !== '' && validarCpf($cpf)) {
    $outro = consultarUm('SELECT nome FROM vendedores WHERE cpf = ? AND id <> ?', [somenteNumeros($cpf), $id]);
    if ($outro !== null) {
        $erros[] = 'Este CPF já está cadastrado para o vendedor ' . $outro['nome'] . '.';
    }
}

if ($telefone !== '' && !validarTelefone($telefone)) {
    $erros[] = 'O telefone deve ter DDD e 8 ou 9 dígitos.';
}

if ($email !== '' && !validarEmail($email)) {
    $erros[] = 'O e-mail informado não é válido.';
}

if (campo('comissao') !== '' && ($comissao === null || $comissao < 0 || $comissao > 15)) {
    $erros[] = 'A comissão deve ficar entre 0% e 15%.';
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível salvar o vendedor.', $erros);
}

$nomeFormatado = mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE, 'UTF-8');
$valores = [
    $nomeFormatado,
    somenteNumeros($cpf),
    somenteNumeros($telefone),
    strtolower($email),
    $comissao,
    $ativo ? 'true' : 'false'
];

if ($id === 0) {
    $id = inserir(
        'INSERT INTO vendedores (nome, cpf, telefone, email, comissao, ativo) VALUES (?, ?, ?, ?, ?, ?)',
        $valores
    );
    $mensagem = 'Vendedor cadastrado com sucesso.';
} else {
    $valores[] = $id;
    $alterados = executar(
        'UPDATE vendedores SET nome = ?, cpf = ?, telefone = ?, email = ?, comissao = ?, ativo = ? WHERE id = ?',
        $valores
    );
    if ($alterados === 0) {
        responder(false, 'Não foi possível salvar o vendedor.', ['Vendedor não encontrado.']);
    }
    $mensagem = 'Vendedor alterado com sucesso.';
}

// logica adicional: simula a comissao de um jogo de 4 pneus e soma o que o vendedor
// ja ganhou no mes com as vendas registradas
$exemploVenda = 4 * 400;
$comissaoMes = consultarUm(
    "SELECT COALESCE(SUM(total), 0) AS total
       FROM vendas
      WHERE vendedor_id = ? AND date_trunc('month', data_venda) = date_trunc('month', CURRENT_DATE)",
    [$id]
);

responder(true, $mensagem, [], [
    'Código' => $id,
    'Nome' => $nomeFormatado,
    'CPF' => formatarCpf($cpf),
    'Contato' => formatarTelefone($telefone) . ' / ' . strtolower($email),
    'Comissão' => number_format($comissao, 2, ',', '.') . '%',
    'Comissão em um jogo de 4 pneus de R$ 400,00' => dinheiro($exemploVenda * $comissao / 100),
    'Vendido no mês' => dinheiro($comissaoMes['total']),
    'Comissão a receber no mês' => dinheiro($comissaoMes['total'] * $comissao / 100),
    'Situação' => $ativo ? 'Ativo (aparece na tela de vendas)' : 'Inativo (não aparece na tela de vendas)'
]);
