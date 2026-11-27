<?php
// controle da sessao do usuario logado
// todo arquivo PHP que precisa de login inclui este arquivo e chama exigirLogin()
require_once __DIR__ . '/conexao.php';

session_start();

// devolve os dados do usuario guardado na sessao, ou null se ninguem fez login
function usuarioLogado()
{
    if (isset($_SESSION['usuario'])) {
        return $_SESSION['usuario'];
    }
    return null;
}

// bloqueia a requisicao de quem nao fez login
// o JavaScript recebe o codigo 401 e manda o usuario para a tela de login
function exigirLogin()
{
    if (usuarioLogado() === null) {
        http_response_code(401);
        responder(false, 'Faça login para continuar.', ['Você não está logado ou a sua sessão expirou.']);
    }
}

// algumas acoes (cadastro de usuarios) so o administrador pode fazer
function exigirAdministrador()
{
    exigirLogin();
    if ($_SESSION['usuario']['perfil'] !== 'administrador') {
        http_response_code(403);
        responder(false, 'Acesso negado.', ['Somente administradores podem fazer isso.']);
    }
}
