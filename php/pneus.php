<?php
// recebe o cadastro de pneu enviado pelo JavaScript
require_once 'validacoes.php';

aceitarSomentePost();

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

// logica adicional 3: avisa quando nao da para montar dois jogos completos
$quantidade = (int) $quantidade;
if ($quantidade == 0) {
    $situacao = 'Sem estoque';
} elseif ($quantidade < 8) {
    $situacao = 'Estoque baixo (menos de 2 jogos de 4 pneus)';
} else {
    $situacao = 'Estoque normal';
}

responder(true, 'Pneu validado com sucesso.', [], [
    'Pneu' => "$marca $modelo " . $medida['texto'] . " $indiceCarga$indiceVelocidade",
    'Preço de custo' => dinheiro($custo),
    'Preço de venda' => dinheiro($precoVenda) . ' (margem de ' . number_format($margem, 1, ',', '.') . '%)',
    'Lucro por pneu' => dinheiro($lucroUnidade),
    'Preço do jogo com 4' => dinheiro($precoVenda * 4),
    'Carga máxima' => "$cargaPneu kg por pneu ($cargaCarro kg nos 4 pneus)",
    'Velocidade máxima' => "$velocidade km/h",
    'Estoque' => "$quantidade unidades - $situacao"
]);
