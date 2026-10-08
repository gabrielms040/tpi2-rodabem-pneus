// pagina de registro de vendas
prepararFormulario('form-venda', '../php/vendas.php');
mascaraCpf(document.getElementById('cpf_cliente'));

const campoPagamento = document.getElementById('forma_pagamento');
const blocoParcelas = document.getElementById('bloco-parcelas');
const campoParcelas = document.getElementById('parcelas');
const campoQuantidade = document.getElementById('quantidade');
const campoPreco = document.getElementById('preco_unitario');
const previa = document.getElementById('previa');

// o campo de parcelas so aparece quando a forma de pagamento e cartao de credito
campoPagamento.addEventListener('change', function () {
    if (campoPagamento.value === 'credito') {
        blocoParcelas.hidden = false;
    } else {
        blocoParcelas.hidden = true;
        campoParcelas.value = '1';
    }
});

// mostra o subtotal enquanto o usuario digita (o total final e calculado no PHP)
function atualizarPrevia() {
    let texto = campoPreco.value.trim();
    if (texto.includes(',')) {
        texto = texto.replace(/\./g, '').replace(',', '.');
    }
    const preco = parseFloat(texto);
    const quantidade = parseInt(campoQuantidade.value);
    if (isNaN(preco) || isNaN(quantidade)) {
        previa.textContent = '';
        return;
    }
    const subtotal = preco * quantidade;
    previa.textContent = 'Subtotal dos pneus: R$ ' + subtotal.toFixed(2).replace('.', ',');
}

campoPreco.addEventListener('input', atualizarPrevia);
campoQuantidade.addEventListener('input', atualizarPrevia);

document.getElementById('form-venda').addEventListener('reset', function () {
    blocoParcelas.hidden = true;
    previa.textContent = '';
});
