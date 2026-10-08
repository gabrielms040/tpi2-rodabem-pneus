<?php
// recebe o cadastro de cliente enviado pelo JavaScript
require_once 'validacoes.php';

aceitarSomentePost();

$erros = [];

verificarObrigatorios([
    'nome' => 'Nome completo',
    'cpf' => 'CPF',
    'data_nascimento' => 'Data de nascimento',
    'telefone' => 'Telefone',
    'email' => 'E-mail',
    'cidade' => 'Cidade'
], $erros);

$nome = campo('nome');
$cpf = campo('cpf');
$nascimento = campo('data_nascimento');
$telefone = campo('telefone');
$email = campo('email');
$cidade = campo('cidade');

if ($nome !== '' && count(explode(' ', $nome)) < 2) {
    $erros[] = 'Informe o nome e o sobrenome do cliente.';
}

if ($cpf !== '' && !validarCpf($cpf)) {
    $erros[] = 'O CPF informado não é válido.';
}

if ($telefone !== '' && !validarTelefone($telefone)) {
    $erros[] = 'O telefone deve ter DDD e 8 ou 9 dígitos.';
}

if ($email !== '' && !validarEmail($email)) {
    $erros[] = 'O e-mail informado não é válido.';
}

// logica adicional: calcula a idade e exige que o cliente seja maior de idade
$idade = null;
if ($nascimento !== '') {
    $data = DateTime::createFromFormat('Y-m-d', $nascimento);
    if (!$data) {
        $erros[] = 'A data de nascimento não é válida.';
    } else {
        $hoje = new DateTime();
        if ($data > $hoje) {
            $erros[] = 'A data de nascimento não pode ser no futuro.';
        } else {
            $idade = $data->diff($hoje)->y;
            if ($idade < 18) {
                $erros[] = "O cliente tem $idade anos. Só é possível cadastrar clientes com 18 anos ou mais.";
            }
        }
    }
}

if (count($erros) > 0) {
    responder(false, 'Não foi possível salvar o cliente.', $erros);
}

// deixa o nome com a primeira letra de cada palavra maiuscula
$nomeFormatado = ucwords(strtolower($nome));

responder(true, 'Cliente validado com sucesso.', [], [
    'Nome' => $nomeFormatado,
    'CPF' => formatarCpf($cpf),
    'Idade' => "$idade anos",
    'Telefone' => formatarTelefone($telefone),
    'E-mail' => strtolower($email),
    'Cidade' => $cidade
]);
