<?php
// recebe o registro de venda enviado pelo JavaScript
require_once 'validacoes.php';

aceitarSomentePost();

$erros = [];

verificarObrigatorios([
    'cpf_cliente' => 'CPF do cliente',
    'vendedor' => 'Vendedor',
    'pneu' => 'Pneu',
    'quantidade' => 'Quantidade',
    'preco_unitario' => 'Preço unitário',
    'forma_pagamento' => 'Forma de pagamento'
], $erros);

$cpf = campo('cpf_cliente');
$vendedor = campo('vendedor');
$pneu = campo('pneu');
$quantidade = campo('quantidade');
$preco = paraNumero(campo('preco_unitario'));
$pagamento = campo('forma_pagamento');
$parcelas = campo('parcelas');
$montagem = campo('montagem') === 'sim';

// por enquanto os vendedores ficam fixos aqui (depois virao do banco de dados)
$vendedores = [
    '1' => 'Carlos Henrique',
    '2' => 'Juliana Martins',
    '3' => 'Rafael Souza'
];

$formas = [
    'dinheiro' => 'Dinheiro',
    'pix' => 'Pix',
    'debito' => 'Cartão de débito',
    'credito' => 'Cartão de crédito'
];

if ($cpf !== '' && !validarCpf($cpf)) {
    $erros[] = 'O CPF do cliente não é válido.';
}

if ($vendedor !== '' && !isset($vendedores[$vendedor])) {
    $erros[] = 'Vendedor inválido.';
}

if ($quantidade !== '' && (!ctype_digit($quantidade) || $quantidade < 1 || $quantidade > 20)) {
    $erros[] = 'A quantidade deve ser de 1 a 20 pneus.';
}

if (campo('preco_unitario') !== '' && ($preco === null || $preco <= 0)) {
    $erros[] = 'O preço unitário deve ser maior que zero.';
}

if ($pagamento !== '' && !isset($formas[$pagamento])) {
    $erros[] = 'Forma de pagamento inválida.';
}

// so o cartao de credito pode ser parcelado
if ($pagamento === 'credito') {
    if (!ctype_digit($parcelas) || $parcelas < 1 || $parcelas > 10) {
        $erros[] = 'No cartão de crédito o parcelamento vai de 1x a 10x.';
    }
} else {
    $parcelas = '1';
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível registrar a venda.', $erros);
}

$quantidade = (int) $quantidade;
$parcelas = (int) $parcelas;
$jogoCompleto = $quantidade >= 4;

// logica adicional: calculo do valor da venda
$subtotal = $quantidade * $preco;

// montagem custa R$ 20,00 por pneu, mas e gratis na compra de 4 ou mais
$valorMontagem = 0;
if ($montagem && !$jogoCompleto) {
    $valorMontagem = 20 * $quantidade;
}

// desconto so sobre os pneus: 5% a vista e mais 3% no jogo completo
$percentualDesconto = 0;
if ($pagamento === 'dinheiro' || $pagamento === 'pix') {
    $percentualDesconto += 5;
}
if ($jogoCompleto) {
    $percentualDesconto += 3;
}
$desconto = $subtotal * $percentualDesconto / 100;

$total = $subtotal - $desconto + $valorMontagem;

// de 7x a 10x no credito tem acrescimo de 5%
$acrescimo = 0;
if ($parcelas > 6) {
    $acrescimo = $total * 0.05;
    $total = $total + $acrescimo;
}

$valorParcela = $total / $parcelas;

// parcela minima de R$ 50,00
if ($parcelas > 1 && $valorParcela < 50) {
    $maximo = max(1, (int) floor($total / 50));
    responder(false, 'Não foi possível registrar a venda.', [
        'A parcela mínima é de R$ 50,00. Para esse valor, o máximo é ' . $maximo . 'x.'
    ]);
}

$dados = [
    'Cliente (CPF)' => formatarCpf($cpf),
    'Vendedor' => $vendedores[$vendedor],
    'Pneu' => "$quantidade x $pneu",
    'Subtotal dos pneus' => dinheiro($subtotal)
];

if ($desconto > 0) {
    $dados['Desconto'] = "$percentualDesconto% (-" . dinheiro($desconto) . ')';
}

if ($montagem) {
    $dados['Montagem e balanceamento'] = ($valorMontagem > 0) ? dinheiro($valorMontagem) : 'Grátis (jogo completo)';
}

if ($acrescimo > 0) {
    $dados['Acréscimo do parcelamento'] = dinheiro($acrescimo);
}

$dados['Total'] = dinheiro($total);
$dados['Pagamento'] = $formas[$pagamento] . ' em ' . $parcelas . 'x de ' . dinheiro($valorParcela);

if ($jogoCompleto) {
    $dados['Brinde'] = 'Alinhamento grátis';
}

responder(true, 'Venda registrada com sucesso.', [], $dados);
