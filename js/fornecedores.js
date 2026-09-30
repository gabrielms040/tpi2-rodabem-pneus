// pagina de cadastro de fornecedores
prepararFormulario('form-fornecedor', '../php/fornecedores.php');
mascaraTelefone(document.getElementById('telefone'));

// mascara do CNPJ: 00.000.000/0000-00
const campoCnpj = document.getElementById('cnpj');
campoCnpj.addEventListener('input', function () {
    let v = campoCnpj.value.replace(/\D/g, '').slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d)/, '$1-$2');
    campoCnpj.value = v;
});
