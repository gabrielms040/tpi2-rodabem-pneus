<?php
// conexao com o banco de dados PostgreSQL usando PDO
// se o banco do seu computador usar outra senha ou porta, altere so aqui
require_once __DIR__ . '/validacoes.php';

define('DB_HOST', 'localhost');
define('DB_PORTA', '5432');
define('DB_NOME', 'rodabem');
define('DB_USUARIO', 'postgres');
define('DB_SENHA', 'uniube');

// abre a conexao uma vez so e reaproveita nas proximas chamadas
function conectar()
{
    static $conexao = null;

    if ($conexao === null) {
        try {
            $conexao = new PDO(
                'pgsql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME,
                DB_USUARIO,
                DB_SENHA
            );
            // qualquer erro de SQL vira uma excecao (PDOException)
            $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // os resultados vem indexados pelo nome da coluna
            $conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            responder(false, 'Não foi possível conectar ao banco de dados.', [
                'Confira se o PostgreSQL está rodando, se o script banco/rodabem.sql foi executado e os dados de php/conexao.php.'
            ]);
        }
    }

    return $conexao;
}

// SELECT: devolve todas as linhas
// os valores vao separados do SQL (prepared statement), isso evita SQL injection
function consultar($sql, $parametros = [])
{
    $comando = conectar()->prepare($sql);
    $comando->execute($parametros);
    return $comando->fetchAll();
}

// SELECT de uma linha so (ou null se nao achar)
function consultarUm($sql, $parametros = [])
{
    $linhas = consultar($sql, $parametros);
    return count($linhas) > 0 ? $linhas[0] : null;
}

// INSERT, UPDATE e DELETE: devolve quantas linhas foram afetadas
function executar($sql, $parametros = [])
{
    $comando = conectar()->prepare($sql);
    $comando->execute($parametros);
    return $comando->rowCount();
}

// INSERT que devolve o id gerado pelo SERIAL
function inserir($sql, $parametros = [])
{
    $comando = conectar()->prepare($sql . ' RETURNING id');
    $comando->execute($parametros);
    return (int) $comando->fetchColumn();
}

// exclui um registro e trata o caso dele estar sendo usado em outra tabela
function excluirRegistro($tabela, $id, $mensagemEmUso)
{
    try {
        $apagados = executar("DELETE FROM $tabela WHERE id = ?", [$id]);
    } catch (PDOException $e) {
        // 23503 = violacao de chave estrangeira (registro usado em outra tabela)
        if ($e->getCode() === '23503') {
            responder(false, 'Não foi possível excluir.', [$mensagemEmUso]);
        }
        throw $e;
    }

    if ($apagados === 0) {
        responder(false, 'Não foi possível excluir.', ['Registro não encontrado.']);
    }
    responder(true, 'Registro excluído.');
}
