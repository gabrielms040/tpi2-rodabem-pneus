<?php
// funcoes usadas por todos os arquivos PHP do sistema
// o PHP so devolve JSON, quem monta a tela e o JavaScript

header('Content-Type: application/json; charset=utf-8');

// envia a resposta para o JavaScript e encerra o script
function responder($sucesso, $mensagem, $erros = [], $dados = [])
{
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'erros' => $erros,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// os formularios so podem ser enviados por POST
function aceitarSomentePost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        responder(false, 'Requisição inválida.', ['Os dados devem ser enviados pelo método POST.']);
    }
}

// pega um campo do $_POST sem espacos no comeco e no fim
function campo($nome)
{
    if (isset($_POST[$nome])) {
        return trim($_POST[$nome]);
    }
    return '';
}

// confere se os campos obrigatorios foram preenchidos
function verificarObrigatorios($campos, &$erros)
{
    foreach ($campos as $nome => $rotulo) {
        if (campo($nome) === '') {
            $erros[] = "O campo $rotulo é obrigatório.";
        }
    }
}

function somenteNumeros($texto)
{
    return preg_replace('/\D/', '', $texto);
}

// aceita numero com virgula ou ponto (ex.: 350,90 ou 350.90)
function paraNumero($texto)
{
    $texto = str_replace(' ', '', $texto);
    if (strpos($texto, ',') !== false) {
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    }
    if (!is_numeric($texto)) {
        return null;
    }
    return (float) $texto;
}

function dinheiro($valor)
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// valida o CPF calculando os dois digitos verificadores
function validarCpf($cpf)
{
    $cpf = somenteNumeros($cpf);
    if (strlen($cpf) != 11) {
        return false;
    }
    // CPFs com todos os numeros iguais passam na conta, mas nao existem
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += $cpf[$i] * (($posicao + 1) - $i);
        }
        $digito = ($soma * 10) % 11;
        if ($digito == 10) {
            $digito = 0;
        }
        if ($cpf[$posicao] != $digito) {
            return false;
        }
    }
    return true;
}

// valida o CNPJ calculando os dois digitos verificadores
function validarCnpj($cnpj)
{
    $cnpj = somenteNumeros($cnpj);
    if (strlen($cnpj) != 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    $soma = 0;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $pesos1[$i];
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;

    $soma = 0;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $pesos2[$i];
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;

    return $cnpj[12] == $digito1 && $cnpj[13] == $digito2;
}

function validarEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// telefone fixo (10 digitos) ou celular (11 digitos) com DDD
function validarTelefone($telefone)
{
    $numeros = somenteNumeros($telefone);
    return strlen($numeros) == 10 || strlen($numeros) == 11;
}

function formatarCpf($cpf)
{
    $c = somenteNumeros($cpf);
    return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2);
}

function formatarCnpj($cnpj)
{
    $c = somenteNumeros($cnpj);
    return substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 3) . '/' . substr($c, 8, 4) . '-' . substr($c, 12, 2);
}

function formatarTelefone($telefone)
{
    $t = somenteNumeros($telefone);
    if (strlen($t) == 11) {
        return '(' . substr($t, 0, 2) . ') ' . substr($t, 2, 5) . '-' . substr($t, 7);
    }
    return '(' . substr($t, 0, 2) . ') ' . substr($t, 2, 4) . '-' . substr($t, 6);
}

// separa a medida do pneu. Ex.: 205/55R16 -> largura 205, perfil 55, aro 16
// devolve null se a medida estiver fora do padrao
function lerMedidaPneu($medida)
{
    $medida = strtoupper(str_replace(' ', '', $medida));
    if (!preg_match('/^(\d{3})\/(\d{2})R(\d{2})$/', $medida, $partes)) {
        return null;
    }
    return [
        'largura' => (int) $partes[1],
        'perfil' => (int) $partes[2],
        'aro' => (int) $partes[3],
        'texto' => $medida
    ];
}
