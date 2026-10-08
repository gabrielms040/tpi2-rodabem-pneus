// pagina de cadastro de pneus
prepararFormulario('form-pneu', '../php/pneus.php');

const campoMedida = document.getElementById('medida');
campoMedida.addEventListener('input', function () {
    campoMedida.value = campoMedida.value.toUpperCase();
});

// mostra uma previa do preco de venda enquanto o usuario digita
// (o calculo oficial e feito no PHP)
const campoCusto = document.getElementById('preco_custo');
const campoMargem = document.getElementById('margem');
const previa = document.getElementById('previa');

function atualizarPrevia() {
    let texto = campoCusto.value.trim();
    if (texto.includes(',')) {
        texto = texto.replace(/\./g, '').replace(',', '.');
    }
    const custo = parseFloat(texto);
    const margem = parseFloat(campoMargem.value);
    if (isNaN(custo) || isNaN(margem)) {
        previa.textContent = '';
        return;
    }
    const venda = custo * (1 + margem / 100);
    previa.textContent = 'Prévia do preço de venda: R$ ' + venda.toFixed(2).replace('.', ',');
}

campoCusto.addEventListener('input', atualizarPrevia);
campoMargem.addEventListener('input', atualizarPrevia);
document.getElementById('form-pneu').addEventListener('reset', function () {
    previa.textContent = '';
});
