// pagina de registro de vendas
const PHP_VENDAS = '../php/vendas.php';
const formularioVenda = document.getElementById('form-venda');

const campoPagamento = document.getElementById('forma_pagamento');
const blocoParcelas = document.getElementById('bloco-parcelas');
const campoParcelas = document.getElementById('parcelas');
const campoPneu = document.getElementById('pneu');
const campoQuantidade = document.getElementById('quantidade');
const campoPreco = document.getElementById('preco_unitario');
const previa = document.getElementById('previa');

// depois de registrar: limpa o formulario e atualiza a lista e o estoque dos pneus
prepararFormulario('form-venda', PHP_VENDAS, function () {
    limparFormulario(formularioVenda);
    carregarVendas();
    carregarPneus();
});
mascaraCpf(document.getElementById('cpf_cliente'));

// preco e estoque de cada pneu, guardados quando o select e carregado
let pneus = {};

// vendedores ativos vem do banco
carregarOpcoes('vendedor', '../php/vendedores.php?acao=ativos').catch(console.log);

// pneus com estoque vem do banco (recarrega depois de cada venda ou cancelamento)
async function carregarPneus() {
    let json;
    try {
        json = await chamarPhp('../php/pneus.php?acao=opcoes');
    } catch (erro) {
        console.log(erro);
        return;
    }
    campoPneu.length = 1; // deixa so a opcao "Selecione"
    pneus = {};
    for (const item of json.dados) {
        const opcao = document.createElement('option');
        opcao.value = item.id;
        opcao.textContent = item.nome;
        campoPneu.appendChild(opcao);
        pneus[item.id] = item;
    }
    mostrarPreco();
}

// ao escolher o pneu, mostra o preco do cadastro (so para leitura)
function mostrarPreco() {
    const pneu = pneus[campoPneu.value];
    campoPreco.value = pneu ? pneu.preco.toFixed(2).replace('.', ',') : '';
    atualizarPrevia();
}

// o campo de parcelas so aparece quando a forma de pagamento e cartao de credito
campoPagamento.addEventListener('change', function () {
    if (campoPagamento.value === 'credito') {
        blocoParcelas.hidden = false;
    } else {
        blocoParcelas.hidden = true;
        campoParcelas.value = '1';
    }
});

// mostra o subtotal enquanto o usuario escolhe (o total final e calculado no PHP)
function atualizarPrevia() {
    const pneu = pneus[campoPneu.value];
    const quantidade = parseInt(campoQuantidade.value);
    if (!pneu || isNaN(quantidade)) {
        previa.textContent = '';
        return;
    }
    const subtotal = pneu.preco * quantidade;
    let texto = 'Subtotal dos pneus: R$ ' + subtotal.toFixed(2).replace('.', ',');
    if (quantidade > pneu.estoque) {
        texto += ' (atenção: só há ' + pneu.estoque + ' em estoque)';
    }
    previa.textContent = texto;
}

campoPneu.addEventListener('change', mostrarPreco);
campoQuantidade.addEventListener('input', atualizarPrevia);

formularioVenda.addEventListener('reset', function () {
    blocoParcelas.hidden = true;
    previa.textContent = '';
});

// ---------------------------------------------------------------
// lista de vendas: venda nao se altera, so tem o botao de cancelar
// ---------------------------------------------------------------
async function carregarVendas() {
    const area = document.getElementById('lista');
    try {
        const json = await chamarPhp(PHP_VENDAS + '?acao=listar');
        if (!json.sucesso) {
            area.textContent = json.erros.join(' ');
            return;
        }
        montarListaVendas(area, json.dados);
    } catch (erro) {
        area.textContent = 'Não foi possível carregar a lista.';
        console.log(erro);
    }
}

function montarListaVendas(area, vendas) {
    area.innerHTML = '';

    if (vendas.length === 0) {
        const vazio = document.createElement('p');
        vazio.className = 'descricao';
        vazio.textContent = 'Nenhuma venda registrada ainda.';
        area.appendChild(vazio);
        return;
    }

    const tabela = document.createElement('table');
    tabela.className = 'tabela';

    const colunas = Object.keys(vendas[0]).filter(function (c) { return c !== 'id'; });
    const cabecalho = tabela.createTHead().insertRow();
    for (const coluna of colunas.concat(['Ações'])) {
        const th = document.createElement('th');
        th.textContent = coluna;
        cabecalho.appendChild(th);
    }

    const corpo = tabela.createTBody();
    for (const venda of vendas) {
        const linha = corpo.insertRow();
        for (const coluna of colunas) {
            const celula = linha.insertCell();
            celula.textContent = venda[coluna];
            celula.dataset.rotulo = coluna;
        }

        const acoes = linha.insertCell();
        acoes.className = 'acoes-linha';
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'botao-perigo botao-pequeno';
        botao.textContent = 'Excluir';
        botao.title = 'Cancela a venda e devolve os pneus ao estoque';
        botao.addEventListener('click', function () { cancelarVenda(venda); });
        acoes.appendChild(botao);
    }

    area.appendChild(tabela);
}

// apaga a venda e devolve os pneus ao estoque (feito no PHP)
async function cancelarVenda(venda) {
    const pergunta = 'Cancelar a venda de ' + venda['Qtd.'] + ' x ' + venda['Pneu'] + ' para ' + venda['Cliente'] + '?\n'
        + 'Os pneus voltam para o estoque.';
    if (!confirm(pergunta)) {
        return;
    }
    const dados = new FormData();
    dados.append('acao', 'excluir');
    dados.append('id', venda.id);
    const json = await chamarPhp(PHP_VENDAS, { method: 'POST', body: dados });
    mostrarResultado(json);
    if (json.sucesso) {
        carregarVendas();
        carregarPneus();
    }
}

carregarPneus();
carregarVendas();
