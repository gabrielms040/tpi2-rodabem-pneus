<?php
// cadastro de pneus: listar, buscar, salvar (incluir ou alterar) e excluir
require_once 'sessao.php';

exigirLogin();

// tabela do indice de carga: indice => quilos que cada pneu suporta
$tabelaCarga = [
    70 => 335, 71 => 345, 72 => 355, 73 => 365, 74 => 375, 75 => 387, 76 => 400,
    77 => 412, 78 => 425, 79 => 437, 80 => 450, 81 => 462, 82 => 475, 83 => 487,
    84 => 500, 85 => 515, 86 => 530, 87 => 545, 88 => 560, 89 => 580, 90 => 600,
    91 => 615, 92 => 630, 93 => 650, 94 => 670, 95 => 690, 96 => 710, 97 => 730,
    98 => 750, 99 => 775, 100 => 800, 101 => 825, 102 => 850, 103 => 875, 104 => 900,
    105 => 925, 106 => 950, 107 => 975, 108 => 1000, 109 => 1030, 110 => 1060
];

// tabela do indice de velocidade: letra => velocidade maxima em km/h
$tabelaVelocidade = [
    'N' => 140, 'P' => 150, 'Q' => 160, 'R' => 170, 'S' => 180, 'T' => 190,
    'U' => 200, 'H' => 210, 'V' => 240, 'W' => 270, 'Y' => 300
];

// logica adicional: situacao do estoque (avisa quando nao da para montar dois jogos de 4)
function situacaoEstoque($quantidade)
{
    if ($quantidade == 0) {
        return 'Sem estoque';
    }
    if ($quantidade < 8) {
        return 'Estoque baixo (menos de 2 jogos de 4 pneus)';
    }
    return 'Estoque normal';
}

$acao = acaoPedida();

// lista todos os pneus para a tabela da tela
if ($acao === 'listar') {
    $linhas = consultar(
        'SELECT p.*, f.razao_social
           FROM pneus p
           LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
          ORDER BY p.marca, p.modelo, p.medida'
    );

    $lista = [];
    foreach ($linhas as $p) {
        $lista[] = [
            'id' => $p['id'],
            'Pneu' => $p['marca'] . ' ' . $p['modelo'] . ' ' . $p['medida'] . ' ' . $p['indice_carga'] . $p['indice_velocidade'],
            'Fornecedor' => $p['razao_social'] !== null ? $p['razao_social'] : '-',
            'Custo' => dinheiro($p['preco_custo']),
            'Venda' => dinheiro($p['preco_venda']),
            'Estoque' => $p['quantidade'] . ' un. - ' . situacaoEstoque($p['quantidade'])
        ];
    }
    responder(true, 'Pneus cadastrados', [], $lista);
}

// usado pela tela de vendas para montar o select de pneus (so os que tem estoque)
if ($acao === 'opcoes') {
    $linhas = consultar(
        'SELECT id, marca, modelo, medida, preco_venda, quantidade
           FROM pneus
          WHERE quantidade > 0
          ORDER BY marca, modelo, medida'
    );

    $lista = [];
    foreach ($linhas as $p) {
        $lista[] = [
            'id' => $p['id'],
            'nome' => $p['marca'] . ' ' . $p['modelo'] . ' ' . $p['medida'] . ' - ' . dinheiro($p['preco_venda']) . ' (' . $p['quantidade'] . ' un.)',
            'preco' => (float) $p['preco_venda'],
            'estoque' => (int) $p['quantidade']
        ];
    }
    responder(true, 'Pneus em estoque', [], $lista);
}

// devolve um pneu para preencher o formulario na hora de alterar
if ($acao === 'buscar') {
    $p = consultarUm('SELECT * FROM pneus WHERE id = ?', [idPedido()]);
    if ($p === null) {
        responder(false, 'Pneu não encontrado.', ['O pneu pode ter sido excluído.']);
    }
    responder(true, 'Pneu encontrado.', [], [
        'id' => $p['id'],
        'marca' => $p['marca'],
        'modelo' => $p['modelo'],
        'medida' => $p['medida'],
        'indice_carga' => $p['indice_carga'],
        'indice_velocidade' => $p['indice_velocidade'],
        'preco_custo' => number_format($p['preco_custo'], 2, ',', ''),
        'margem' => $p['margem'],
        'quantidade' => $p['quantidade'],
        'fornecedor_id' => $p['fornecedor_id'] !== null ? $p['fornecedor_id'] : ''
    ]);
}

aceitarSomentePost();

if ($acao === 'excluir') {
    excluirRegistro('pneus', idPedido(), 'Este pneu já tem vendas registradas. Para tirar do estoque, deixe a quantidade em 0.');
}

// a partir daqui: salvar o formulario
$id = idPedido();
$erros = [];

verificarObrigatorios([
    'marca' => 'Marca',
    'modelo' => 'Modelo',
    'medida' => 'Medida',
    'indice_carga' => 'Índice de carga',
    'indice_velocidade' => 'Índice de velocidade',
    'preco_custo' => 'Preço de custo',
    'margem' => 'Margem de lucro',
    'quantidade' => 'Quantidade em estoque'
], $erros);

$marca = campo('marca');
$modelo = campo('modelo');
$medidaTexto = campo('medida');
$indiceCarga = campo('indice_carga');
$indiceVelocidade = strtoupper(campo('indice_velocidade'));
$custo = paraNumero(campo('preco_custo'));
$margem = paraNumero(campo('margem'));
$quantidade = campo('quantidade');
$fornecedorId = campo('fornecedor_id');

$medida = null;
if ($medidaTexto !== '') {
    $medida = lerMedidaPneu($medidaTexto);
    if ($medida === null) {
        $erros[] = 'A medida deve seguir o padrão largura/perfilRaro. Ex.: 175/70R14.';
    }
}

if ($indiceCarga !== '' && !isset($tabelaCarga[(int) $indiceCarga])) {
    $erros[] = 'O índice de carga deve ser um número de 70 a 110.';
}

if ($indiceVelocidade !== '' && !isset($tabelaVelocidade[$indiceVelocidade])) {
    $erros[] = 'Índice de velocidade inválido.';
}

if (campo('preco_custo') !== '' && ($custo === null || $custo <= 0)) {
    $erros[] = 'O preço de custo deve ser um valor maior que zero.';
}

if (campo('margem') !== '' && ($margem === null || $margem < 0 || $margem > 200)) {
    $erros[] = 'A margem de lucro deve ficar entre 0% e 200%.';
}

if ($quantidade !== '' && !ctype_digit($quantidade)) {
    $erros[] = 'A quantidade deve ser um número inteiro (0 ou mais).';
}

// o fornecedor e opcional, mas se vier tem que existir no banco
$fornecedor = null;
if ($fornecedorId !== '') {
    if (ctype_digit($fornecedorId)) {
        $fornecedor = consultarUm('SELECT id, razao_social FROM fornecedores WHERE id = ?', [(int) $fornecedorId]);
    }
    if ($fornecedor === null) {
        $erros[] = 'Fornecedor inválido.';
    }
}

// nao pode ter dois pneus com a mesma marca, modelo e medida (na alteracao, ignora o proprio pneu)
if ($marca !== '' && $modelo !== '' && $medida !== null) {
    $outro = consultarUm(
        'SELECT id FROM pneus WHERE LOWER(marca) = LOWER(?) AND LOWER(modelo) = LOWER(?) AND medida = ? AND id <> ?',
        [$marca, $modelo, $medida['texto'], $id]
    );
    if ($outro !== null) {
        $erros[] = 'Este pneu já está cadastrado (código ' . $outro['id'] . '). Altere o cadastro existente.';
    }
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível salvar o pneu.', $erros);
}

// logica adicional 1: preco de venda a partir do custo e da margem
$precoVenda = round($custo * (1 + $margem / 100), 2);
$lucroUnidade = $precoVenda - $custo;

// logica adicional 2: converte os indices para valores que o cliente entende
$cargaPneu = $tabelaCarga[(int) $indiceCarga];
$cargaCarro = $cargaPneu * 4;
$velocidade = $tabelaVelocidade[$indiceVelocidade];

// logica adicional 3: situacao do estoque
$quantidade = (int) $quantidade;
$situacao = situacaoEstoque($quantidade);

$valores = [
    $fornecedor !== null ? $fornecedor['id'] : null,
    $marca,
    $modelo,
    $medida['texto'],
    (int) $indiceCarga,
    $indiceVelocidade,
    $custo,
    $margem,
    $precoVenda,
    $quantidade
];

if ($id === 0) {
    $id = inserir(
        'INSERT INTO pneus (fornecedor_id, marca, modelo, medida, indice_carga, indice_velocidade,
                            preco_custo, margem, preco_venda, quantidade)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $valores
    );
    $mensagem = 'Pneu cadastrado com sucesso.';
} else {
    $valores[] = $id;
    $alterados = executar(
        'UPDATE pneus SET fornecedor_id = ?, marca = ?, modelo = ?, medida = ?, indice_carga = ?, indice_velocidade = ?,
                          preco_custo = ?, margem = ?, preco_venda = ?, quantidade = ?
          WHERE id = ?',
        $valores
    );
    if ($alterados === 0) {
        responder(false, 'Não foi possível salvar o pneu.', ['Pneu não encontrado.']);
    }
    $mensagem = 'Pneu alterado com sucesso.';
}

responder(true, $mensagem, [], [
    'Código' => $id,
    'Pneu' => "$marca $modelo " . $medida['texto'] . " $indiceCarga$indiceVelocidade",
    'Fornecedor' => $fornecedor !== null ? $fornecedor['razao_social'] : 'Não informado',
    'Preço de custo' => dinheiro($custo),
    'Preço de venda' => dinheiro($precoVenda) . ' (margem de ' . number_format($margem, 1, ',', '.') . '%)',
    'Lucro por pneu' => dinheiro($lucroUnidade),
    'Preço do jogo com 4' => dinheiro($precoVenda * 4),
    'Carga máxima' => "$cargaPneu kg por pneu ($cargaCarro kg nos 4 pneus)",
    'Velocidade máxima' => "$velocidade km/h",
    'Estoque' => "$quantidade unidades - $situacao"
]);
