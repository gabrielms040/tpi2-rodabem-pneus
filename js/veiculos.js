// pagina de cadastro de veiculos
prepararFormulario('form-veiculo', '../php/veiculos.php');
mascaraCpf(document.getElementById('cpf_dono'));

// deixa a placa e a medida sempre em letra maiuscula enquanto digita
const campoPlaca = document.getElementById('placa');
campoPlaca.addEventListener('input', function () {
    campoPlaca.value = campoPlaca.value.toUpperCase();
});

const campoMedida = document.getElementById('medida_pneu');
campoMedida.addEventListener('input', function () {
    campoMedida.value = campoMedida.value.toUpperCase();
});
