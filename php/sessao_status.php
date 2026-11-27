<?php
// diz ao JavaScript se existe alguem logado (usado ao abrir cada pagina)
require_once 'sessao.php';

$usuario = usuarioLogado();

if ($usuario === null) {
    responder(false, 'Ninguém logado.');
}

responder(true, 'Usuário logado.', [], $usuario);
