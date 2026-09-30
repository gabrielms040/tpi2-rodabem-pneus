<?php
// recebe o cadastro de fornecedor enviado pelo JavaScript
require_once 'validacoes.php';

aceitarSomentePost();

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

responder(true, 'Fornecedor validado com sucesso.', [], [
    'Razão social' => $razao,
    'CNPJ' => formatarCnpj($cnpj),
    'Contato' => formatarTelefone($telefone) . ' / ' . strtolower($email),
    'Local' => "$cidade - $uf",
    'Região' => $regiao,
    'Prazo' => "$prazo dias úteis ($classificacao)",
    'Pedido feito hoje chega em' => $data->format('d/m/Y')
]);
