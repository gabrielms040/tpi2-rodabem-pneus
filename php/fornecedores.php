<?php
// cadastro de fornecedores: listar, buscar, salvar (incluir ou alterar) e excluir
require_once 'sessao.php';

exigirLogin();

$acao = acaoPedida();

if ($acao === 'listar') {
    $linhas = consultar(
        'SELECT f.id, f.razao_social, f.cnpj, f.cidade, f.uf, f.prazo_entrega,
                (SELECT COUNT(*) FROM pneus p WHERE p.fornecedor_id = f.id) AS pneus
           FROM fornecedores f
          ORDER BY f.razao_social'
    );

    $lista = [];
    foreach ($linhas as $f) {
        $lista[] = [
            'id' => $f['id'],
            'Razão social' => $f['razao_social'],
            'CNPJ' => formatarCnpj($f['cnpj']),
            'Local' => $f['cidade'] . ' - ' . $f['uf'],
            'Prazo' => $f['prazo_entrega'] . ' dias úteis',
            'Pneus' => $f['pneus']
        ];
    }
    responder(true, 'Fornecedores cadastrados', [], $lista);
}

// usado pela tela de pneus para montar o select de fornecedores
if ($acao === 'opcoes') {
    $linhas = consultar('SELECT id, razao_social AS nome FROM fornecedores ORDER BY razao_social');
    responder(true, 'Fornecedores', [], $linhas);
}

if ($acao === 'buscar') {
    $f = consultarUm('SELECT * FROM fornecedores WHERE id = ?', [idPedido()]);
    if ($f === null) {
        responder(false, 'Fornecedor não encontrado.', ['O fornecedor pode ter sido excluído.']);
    }
    responder(true, 'Fornecedor encontrado.', [], [
        'id' => $f['id'],
        'razao_social' => $f['razao_social'],
        'cnpj' => formatarCnpj($f['cnpj']),
        'telefone' => formatarTelefone($f['telefone']),
        'email' => $f['email'],
        'cidade' => $f['cidade'],
        'uf' => $f['uf'],
        'prazo_entrega' => $f['prazo_entrega']
    ]);
}

aceitarSomentePost();

if ($acao === 'excluir') {
    // na tabela pneus o fornecedor fica vazio (ON DELETE SET NULL)
    excluirRegistro('fornecedores', idPedido(), 'Não foi possível excluir este fornecedor.');
}

$id = idPedido();
$erros = [];

verificarObrigatorios([
    'razao_social' => 'Razão social',
    'cnpj' => 'CNPJ',
    'telefone' => 'Telefone',
    'email' => 'E-mail',
    'cidade' => 'Cidade',
    'uf' => 'Estado',
    'prazo_entrega' => 'Prazo de entrega'
], $erros);

$razao = campo('razao_social');
$cnpj = campo('cnpj');
$telefone = campo('telefone');
$email = campo('email');
$cidade = campo('cidade');
$uf = strtoupper(campo('uf'));
$prazo = campo('prazo_entrega');

$estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
    'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

if ($cnpj !== '' && !validarCnpj($cnpj)) {
    $erros[] = 'O CNPJ informado não é válido.';
}

if ($cnpj !== '' && validarCnpj($cnpj)) {
    $outro = consultarUm('SELECT razao_social FROM fornecedores WHERE cnpj = ? AND id <> ?', [somenteNumeros($cnpj), $id]);
    if ($outro !== null) {
        $erros[] = 'Este CNPJ já está cadastrado para ' . $outro['razao_social'] . '.';
    }
}

if ($telefone !== '' && !validarTelefone($telefone)) {
    $erros[] = 'O telefone deve ter DDD e 8 ou 9 dígitos.';
}

if ($email !== '' && !validarEmail($email)) {
    $erros[] = 'O e-mail informado não é válido.';
}

if ($uf !== '' && !in_array($uf, $estados)) {
    $erros[] = 'Estado inválido.';
}

if ($prazo !== '' && (!ctype_digit($prazo) || $prazo < 1 || $prazo > 60)) {
    $erros[] = 'O prazo de entrega deve ser de 1 a 60 dias úteis.';
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível salvar o fornecedor.', $erros);
}

$valores = [$razao, somenteNumeros($cnpj), somenteNumeros($telefone), strtolower($email), $cidade, $uf, (int) $prazo];

if ($id === 0) {
    $id = inserir(
        'INSERT INTO fornecedores (razao_social, cnpj, telefone, email, cidade, uf, prazo_entrega) VALUES (?, ?, ?, ?, ?, ?, ?)',
        $valores
    );
    $mensagem = 'Fornecedor cadastrado com sucesso.';
} else {
    $valores[] = $id;
    $alterados = executar(
        'UPDATE fornecedores SET razao_social = ?, cnpj = ?, telefone = ?, email = ?, cidade = ?, uf = ?, prazo_entrega = ? WHERE id = ?',
        $valores
    );
    if ($alterados === 0) {
        responder(false, 'Não foi possível salvar o fornecedor.', ['Fornecedor não encontrado.']);
    }
    $mensagem = 'Fornecedor alterado com sucesso.';
}

// logica adicional 1: calcula a data de entrega de um pedido feito hoje,
// pulando sabados e domingos
$data = new DateTime();
$diasContados = 0;
while ($diasContados < (int) $prazo) {
    $data->modify('+1 day');
    $diaSemana = (int) $data->format('N'); // 6 = sabado, 7 = domingo
    if ($diaSemana < 6) {
        $diasContados++;
    }
}

// logica adicional 2: classifica o fornecedor pelo prazo e pela regiao
// (a loja fica em Uberlandia - MG)
if ($prazo <= 3) {
    $classificacao = 'Entrega rápida';
} elseif ($prazo <= 7) {
    $classificacao = 'Entrega normal';
} else {
    $classificacao = 'Entrega demorada, fazer pedido com antecedência';
}

$regiao = ($uf === 'MG') ? 'Mesmo estado da loja' : 'Outro estado (frete interestadual)';

// logica adicional 3: compara o prazo com os outros fornecedores cadastrados
$maisRapido = consultarUm(
    'SELECT razao_social, prazo_entrega FROM fornecedores ORDER BY prazo_entrega, razao_social LIMIT 1'
);
if ((int) $maisRapido['prazo_entrega'] === (int) $prazo) {
    $comparacao = 'É o fornecedor com o menor prazo da loja.';
} else {
    $comparacao = 'O mais rápido é ' . $maisRapido['razao_social'] . ' (' . $maisRapido['prazo_entrega'] . ' dias úteis).';
}

responder(true, $mensagem, [], [
    'Código' => $id,
    'Razão social' => $razao,
    'CNPJ' => formatarCnpj($cnpj),
    'Contato' => formatarTelefone($telefone) . ' / ' . strtolower($email),
    'Local' => "$cidade - $uf",
    'Região' => $regiao,
    'Prazo' => "$prazo dias úteis ($classificacao)",
    'Pedido feito hoje chega em' => $data->format('d/m/Y'),
    'Comparação' => $comparacao
]);
