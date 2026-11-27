<?php
// cadastro de veiculos: listar, buscar, salvar (incluir ou alterar) e excluir
// todo veiculo pertence a um cliente ja cadastrado (procurado pelo CPF)
require_once 'sessao.php';

exigirLogin();

$acao = acaoPedida();

if ($acao === 'listar') {
    $linhas = consultar(
        'SELECT v.id, v.placa, v.marca, v.modelo, v.ano, v.medida_pneu, c.nome AS dono
           FROM veiculos v
           JOIN clientes c ON c.id = v.cliente_id
          ORDER BY c.nome, v.placa'
    );

    $lista = [];
    foreach ($linhas as $v) {
        $lista[] = [
            'id' => $v['id'],
            'Placa' => $v['placa'],
            'Veículo' => $v['marca'] . ' ' . $v['modelo'] . ' ' . $v['ano'],
            'Dono' => $v['dono'],
            'Medida do pneu' => $v['medida_pneu']
        ];
    }
    responder(true, 'Veículos cadastrados', [], $lista);
}

if ($acao === 'buscar') {
    $v = consultarUm(
        'SELECT v.*, c.cpf FROM veiculos v JOIN clientes c ON c.id = v.cliente_id WHERE v.id = ?',
        [idPedido()]
    );
    if ($v === null) {
        responder(false, 'Veículo não encontrado.', ['O veículo pode ter sido excluído.']);
    }
    responder(true, 'Veículo encontrado.', [], [
        'id' => $v['id'],
        'cpf_dono' => formatarCpf($v['cpf']),
        'placa' => $v['placa'],
        'marca' => $v['marca'],
        'modelo' => $v['modelo'],
        'ano' => $v['ano'],
        'medida_pneu' => $v['medida_pneu']
    ]);
}

aceitarSomentePost();

if ($acao === 'excluir') {
    excluirRegistro('veiculos', idPedido(), 'Não foi possível excluir este veículo.');
}

$id = idPedido();
$erros = [];

verificarObrigatorios([
    'cpf_dono' => 'CPF do dono',
    'placa' => 'Placa',
    'marca' => 'Marca',
    'modelo' => 'Modelo',
    'ano' => 'Ano',
    'medida_pneu' => 'Medida do pneu'
], $erros);

$cpf = campo('cpf_dono');
$placa = strtoupper(str_replace(['-', ' '], '', campo('placa')));
$marca = campo('marca');
$modelo = campo('modelo');
$ano = campo('ano');
$medidaTexto = campo('medida_pneu');

// o dono precisa estar cadastrado como cliente
$cliente = null;
if ($cpf !== '') {
    if (!validarCpf($cpf)) {
        $erros[] = 'O CPF do dono não é válido.';
    } else {
        $cliente = consultarUm('SELECT id, nome FROM clientes WHERE cpf = ?', [somenteNumeros($cpf)]);
        if ($cliente === null) {
            $erros[] = 'Nenhum cliente tem esse CPF. Cadastre o cliente antes do veículo.';
        }
    }
}

// logica adicional 1: identifica o padrao da placa
// Mercosul: ABC1D23 | modelo antigo: ABC1234
$tipoPlaca = '';
if ($placa !== '') {
    if (preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa)) {
        $tipoPlaca = 'Padrão Mercosul';
    } elseif (preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)) {
        $tipoPlaca = 'Padrão antigo (cinza)';
    } else {
        $erros[] = 'A placa deve estar no formato ABC1234 ou ABC1D23.';
    }
}

// a mesma placa nao pode estar em dois veiculos
if ($tipoPlaca !== '') {
    $outro = consultarUm('SELECT id FROM veiculos WHERE placa = ? AND id <> ?', [$placa, $id]);
    if ($outro !== null) {
        $erros[] = 'Já existe um veículo com a placa ' . $placa . '.';
    }
}

$anoAtual = (int) date('Y');
if ($ano !== '') {
    if (!ctype_digit($ano) || $ano < 1950 || $ano > $anoAtual + 1) {
        $erros[] = 'O ano deve estar entre 1950 e ' . ($anoAtual + 1) . '.';
    }
}

$medida = null;
if ($medidaTexto !== '') {
    $medida = lerMedidaPneu($medidaTexto);
    if ($medida === null) {
        $erros[] = 'A medida do pneu deve seguir o padrão largura/perfilRaro. Ex.: 175/70R14.';
    }
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível salvar o veículo.', $erros);
}

$valores = [$cliente['id'], $placa, $marca, $modelo, (int) $ano, $medida['texto']];

if ($id === 0) {
    $id = inserir(
        'INSERT INTO veiculos (cliente_id, placa, marca, modelo, ano, medida_pneu) VALUES (?, ?, ?, ?, ?, ?)',
        $valores
    );
    $mensagem = 'Veículo cadastrado com sucesso.';
} else {
    $valores[] = $id;
    $alterados = executar(
        'UPDATE veiculos SET cliente_id = ?, placa = ?, marca = ?, modelo = ?, ano = ?, medida_pneu = ? WHERE id = ?',
        $valores
    );
    if ($alterados === 0) {
        responder(false, 'Não foi possível salvar o veículo.', ['Veículo não encontrado.']);
    }
    $mensagem = 'Veículo alterado com sucesso.';
}

// logica adicional 2: calcula a altura do pneu montado
// altura da lateral = largura x perfil / 100
// diametro total = aro em mm + 2 laterais
$lateral = $medida['largura'] * $medida['perfil'] / 100;
$diametroRoda = $medida['aro'] * 25.4;
$diametroTotal = $diametroRoda + 2 * $lateral;

$idadeVeiculo = $anoAtual - (int) $ano;

// logica adicional 3: pneus em estoque que servem nesse veiculo
$estoque = consultarUm(
    'SELECT COUNT(*) AS modelos, COALESCE(SUM(quantidade), 0) AS unidades
       FROM pneus WHERE medida = ? AND quantidade > 0',
    [$medida['texto']]
);

responder(true, $mensagem, [], [
    'Código' => $id,
    'Dono' => $cliente['nome'] . ' (CPF ' . formatarCpf($cpf) . ')',
    'Placa' => "$placa ($tipoPlaca)",
    'Veículo' => "$marca $modelo $ano",
    'Idade do veículo' => ($idadeVeiculo <= 0) ? 'Menos de 1 ano' : "$idadeVeiculo anos",
    'Medida do pneu' => $medida['texto'],
    'Altura da lateral do pneu' => number_format($lateral, 1, ',', '.') . ' mm',
    'Diâmetro total do pneu' => number_format($diametroTotal, 1, ',', '.') . ' mm',
    'Pneus dessa medida no estoque' => ($estoque['unidades'] > 0)
        ? $estoque['unidades'] . ' unidades (' . $estoque['modelos'] . ' modelo(s))'
        : 'Nenhum. Fazer pedido ao fornecedor.'
]);
