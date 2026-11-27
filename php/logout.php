<?php
// encerra a sessao do usuario
require_once 'sessao.php';

$_SESSION = [];
session_destroy();

responder(true, 'Você saiu do sistema.');
