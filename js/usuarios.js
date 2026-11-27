// pagina de cadastro de usuarios do sistema
const dicaSenha = document.getElementById('dica-senha');
const textoDicaSenha = dicaSenha.textContent;

iniciarCadastro({
    formulario: 'form-usuario',
    php: '../php/usuarios.php',
    nome: 'usuário',
    // na alteracao a senha e opcional
    aoAlterar: function () {
        dicaSenha.textContent = 'Deixe em branco para manter a senha atual.';
    }
});

document.getElementById('form-usuario').addEventListener('reset', function () {
    dicaSenha.textContent = textoDicaSenha;
});

// o login fica sempre em minusculas e sem espacos
const campoLogin = document.getElementById('login');
campoLogin.addEventListener('input', function () {
    campoLogin.value = campoLogin.value.toLowerCase().replace(/\s/g, '');
});
