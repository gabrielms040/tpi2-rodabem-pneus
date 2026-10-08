<?php
// recebe o cadastro de veiculo enviado pelo JavaScript
require_once 'validacoes.php';

aceitarSomentePost();

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

if ($cpf !== '' && !validarCpf($cpf)) {
    $erros[] = 'O CPF do dono não é válido.';
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

// logica adicional 2: calcula a altura do pneu montado
// altura da lateral = largura x perfil / 100
// diametro total = aro em mm + 2 laterais
$lateral = $medida['largura'] * $medida['perfil'] / 100;
$diametroRoda = $medida['aro'] * 25.4;
$diametroTotal = $diametroRoda + 2 * $lateral;

$idadeVeiculo = $anoAtual - (int) $ano;

responder(true, 'Veículo validado com sucesso.', [], [
    'Dono (CPF)' => formatarCpf($cpf),
    'Placa' => "$placa ($tipoPlaca)",
    'Veículo' => "$marca $modelo $ano",
    'Idade do veículo' => ($idadeVeiculo <= 0) ? 'Menos de 1 ano' : "$idadeVeiculo anos",
    'Medida do pneu' => $medida['texto'],
    'Altura da lateral do pneu' => number_format($lateral, 1, ',', '.') . ' mm',
    'Diâmetro total do pneu' => number_format($diametroTotal, 1, ',', '.') . ' mm'
]);
