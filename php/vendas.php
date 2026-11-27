<?php
// registro de vendas: listar, registrar e excluir (cancelar)
// a venda nao pode ser alterada: para corrigir, cancela e registra de novo
require_once 'sessao.php';

exigirLogin();

$formas = [
    'dinheiro' => 'Dinheiro',
    'pix' => 'Pix',
    'debito' => 'Cartão de débito',
    'credito' => 'Cartão de crédito'
];

$acao = acaoPedida();

// lista as vendas, da mais nova para a mais antiga
if ($acao === 'listar') {
    $linhas = consultar(
        'SELECT v.id, v.data_venda, v.quantidade, v.total, v.forma_pagamento, v.parcelas,
                c.nome AS cliente, ve.nome AS vendedor,
                p.marca, p.modelo, p.medida
           FROM vendas v
           JOIN clientes c ON c.id = v.cliente_id
           JOIN vendedores ve ON ve.id = v.vendedor_id
           JOIN pneus p ON p.id = v.pneu_id
          ORDER BY v.data_venda DESC, v.id DESC'
    );

    $lista = [];
    foreach ($linhas as $v) {
        $pagamento = $formas[$v['forma_pagamento']];
        if ($v['parcelas'] > 1) {
            $pagamento .= ' em ' . $v['parcelas'] . 'x';
        }
        $lista[] = [
            'id' => $v['id'],
            'Data' => (new DateTime($v['data_venda']))->format('d/m/Y H:i'),
            'Cliente' => $v['cliente'],
            'Pneu' => $v['marca'] . ' ' . $v['modelo'] . ' ' . $v['medida'],
            'Qtd.' => $v['quantidade'],
            'Total' => dinheiro($v['total']),
            'Pagamento' => $pagamento,
            'Vendedor' => $v['vendedor']
        ];
    }
    responder(true, 'Vendas registradas', [], $lista);
}

aceitarSomentePost();

// cancela a venda: apaga o registro e devolve os pneus ao estoque
// as duas coisas ficam numa transacao (ou faz tudo, ou nao faz nada)
if ($acao === 'excluir') {
    $banco = conectar();
    $banco->beginTransaction();
    try {
        $venda = consultarUm('SELECT pneu_id, quantidade FROM vendas WHERE id = ? FOR UPDATE', [idPedido()]);
        if ($venda === null) {
            $banco->rollBack();
            responder(false, 'Não foi possível cancelar a venda.', ['Venda não encontrada.']);
        }
        executar('DELETE FROM vendas WHERE id = ?', [idPedido()]);
        executar('UPDATE pneus SET quantidade = quantidade + ? WHERE id = ?', [$venda['quantidade'], $venda['pneu_id']]);
        $banco->commit();
    } catch (PDOException $e) {
        $banco->rollBack();
        throw $e;
    }

    $pneu = consultarUm('SELECT quantidade FROM pneus WHERE id = ?', [$venda['pneu_id']]);
    responder(true, 'Venda cancelada.', [], [
        'Devolvido ao estoque' => $venda['quantidade'] . ' pneu(s)',
        'Estoque atual do pneu' => $pneu['quantidade'] . ' unidades'
    ]);
}

// a partir daqui: registrar a venda
$erros = [];

verificarObrigatorios([
    'cpf_cliente' => 'CPF do cliente',
    'vendedor' => 'Vendedor',
    'pneu' => 'Pneu',
    'quantidade' => 'Quantidade',
    'forma_pagamento' => 'Forma de pagamento'
], $erros);

$cpf = campo('cpf_cliente');
$vendedorId = campo('vendedor');
$pneuId = campo('pneu');
$quantidade = campo('quantidade');
$pagamento = campo('forma_pagamento');
$parcelas = campo('parcelas');
$montagem = campo('montagem') === 'sim';

// o cliente tem que estar cadastrado
$cliente = null;
if ($cpf !== '') {
    if (!validarCpf($cpf)) {
        $erros[] = 'O CPF do cliente não é válido.';
    } else {
        $cliente = consultarUm('SELECT id, nome FROM clientes WHERE cpf = ?', [somenteNumeros($cpf)]);
        if ($cliente === null) {
            $erros[] = 'Nenhum cliente cadastrado com este CPF. Cadastre o cliente antes de registrar a venda.';
        }
    }
}

// o vendedor tem que existir e estar ativo
$vendedor = null;
if ($vendedorId !== '') {
    if (ctype_digit($vendedorId)) {
        $vendedor = consultarUm('SELECT id, nome, ativo FROM vendedores WHERE id = ?', [(int) $vendedorId]);
    }
    if ($vendedor === null) {
        $erros[] = 'Vendedor inválido.';
    } elseif (!$vendedor['ativo']) {
        $erros[] = 'O vendedor ' . $vendedor['nome'] . ' está inativo.';
    }
}

if ($quantidade !== '' && (!ctype_digit($quantidade) || $quantidade < 1 || $quantidade > 20)) {
    $erros[] = 'A quantidade deve ser de 1 a 20 pneus.';
}

// o pneu e o preco vem do banco, nao do formulario
$pneu = null;
if ($pneuId !== '') {
    if (ctype_digit($pneuId)) {
        $pneu = consultarUm('SELECT * FROM pneus WHERE id = ?', [(int) $pneuId]);
    }
    if ($pneu === null) {
        $erros[] = 'Pneu inválido.';
    } elseif (ctype_digit($quantidade) && (int) $quantidade > $pneu['quantidade']) {
        $erros[] = 'Estoque insuficiente: só há ' . $pneu['quantidade'] . ' unidade(s) deste pneu.';
    }
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
$preco = (float) $pneu['preco_venda'];
$nomePneu = $pneu['marca'] . ' ' . $pneu['modelo'] . ' ' . $pneu['medida'];
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
$desconto = round($subtotal * $percentualDesconto / 100, 2);

$total = $subtotal - $desconto + $valorMontagem;

// de 7x a 10x no credito tem acrescimo de 5%
$acrescimo = 0;
if ($parcelas > 6) {
    $acrescimo = round($total * 0.05, 2);
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

// grava a venda e baixa o estoque dentro de uma transacao
$banco = conectar();
$banco->beginTransaction();
try {
    // so baixa se ainda tiver estoque (outro usuario pode ter vendido no mesmo instante)
    $baixou = executar(
        'UPDATE pneus SET quantidade = quantidade - ? WHERE id = ? AND quantidade >= ?',
        [$quantidade, $pneu['id'], $quantidade]
    );
    if ($baixou === 0) {
        $banco->rollBack();
        responder(false, 'Não foi possível registrar a venda.', ['Estoque insuficiente para este pneu.']);
    }

    $idVenda = inserir(
        'INSERT INTO vendas (cliente_id, vendedor_id, pneu_id, usuario_id, quantidade, preco_unitario,
                             forma_pagamento, parcelas, montagem, desconto, acrescimo, total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $cliente['id'],
            $vendedor['id'],
            $pneu['id'],
            usuarioLogado()['id'],
            $quantidade,
            $preco,
            $pagamento,
            $parcelas,
            $montagem ? 'true' : 'false',
            $desconto,
            $acrescimo,
            round($total, 2)
        ]
    );
    $banco->commit();
} catch (PDOException $e) {
    $banco->rollBack();
    throw $e;
}

$dados = [
    'Código da venda' => $idVenda,
    'Cliente' => $cliente['nome'] . ' (' . formatarCpf($cpf) . ')',
    'Vendedor' => $vendedor['nome'],
    'Pneu' => "$quantidade x $nomePneu (" . dinheiro($preco) . ' cada)',
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

$dados['Estoque restante do pneu'] = ($pneu['quantidade'] - $quantidade) . ' unidades';

responder(true, 'Venda registrada com sucesso.', [], $dados);
