// pagina de login

// se ja existe alguem logado, vai direto para a pagina inicial
fetch('php/sessao_status.php')
    .then(function (resposta) { return resposta.json(); })
    .then(function (json) {
        if (json.sucesso) {
            window.location.href = 'index.html';
        }
    })
    .catch(function (erro) { console.log(erro); });

// envia usuario e senha; se o PHP aceitar, abre a pagina inicial
prepararFormulario('form-login', 'php/login.php', function () {
    window.location.href = 'index.html';
});
