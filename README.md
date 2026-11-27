# RodaBem Pneus

Sistema administrativo de uma loja de pneus, desenvolvido para os Trabalhos (Momento I e Momento II) da disciplina Tecnologias para Internet II - Uniube.

No Momento II o sistema passou a gravar tudo em banco de dados (PostgreSQL) e ganhou login com sessão.

**Dupla:**
- Gabriel Morais da Silva - RA 5171798 (branch-aluno-1)
- Lucas Rocha Rodrigues - RA 5173899 (branch-aluno-2)

## Funcionalidades

| Página | Responsável | O que faz |
|---|---|---|
| Página inicial | Gabriel | Menu de acesso a todas as páginas |
| Clientes | Gabriel | Cadastro de clientes, valida CPF e calcula a idade (mínimo 18 anos) |
| Veículos | Gabriel | Cadastro do veículo do cliente, identifica o padrão da placa e calcula o diâmetro do pneu |
| Fornecedores | Gabriel | Cadastro de fornecedores, valida CNPJ e calcula a data prevista de entrega |
| Pneus | Lucas | CRUD de pneus com fornecedor, calcula o preço de venda, converte os índices de carga e velocidade e não deixa cadastrar pneu repetido |
| Vendas | Lucas | Registro de venda com cliente, vendedor e pneu do banco, descontos, montagem, parcelamento e brinde; baixa o estoque e cancelar a venda devolve os pneus |
| Login | Gabriel | Confere usuário e senha no banco e guarda o usuário logado na sessão |
| Usuários | Gabriel | CRUD de quem pode entrar no sistema (só o administrador acessa), senha criptografada |
| Vendedores | Gabriel | CRUD dos vendedores, com comissão e total vendido no mês |

Todos os cadastros têm listagem, inclusão, alteração e exclusão, e só funcionam depois do login. A venda não é alterada: para corrigir, ela é cancelada (excluída) e registrada de novo.

## Como executar

1. **Banco de dados (PostgreSQL).** Execute o script, que cria o banco `rodabem`, as 7 tabelas e alguns dados de exemplo:

   ```
   psql -U postgres -f banco/rodabem.sql
   ```

   No pgAdmin: rode as linhas do `DROP/CREATE DATABASE`, conecte no banco `rodabem` e rode o resto do arquivo.

2. **Conexão.** Os dados de acesso ficam em `php/conexao.php` (padrão: `localhost`, porta `5432`, usuário `postgres`, senha `uniube`). Se o seu PostgreSQL usar outra senha ou porta, altere só esse arquivo.

3. **PHP com PostgreSQL.** No XAMPP, habilite no `php.ini` as extensões `extension=pdo_pgsql` e `extension=pgsql` (tire o `;` do começo da linha) e reinicie o Apache.

4. **Sistema.** Descompacte a pasta do projeto em qualquer diretório dentro do `htdocs` e acesse pelo navegador. Os links são todos relativos, então o nome da pasta não importa.

   Sem XAMPP, também funciona com `php -S localhost:8000` dentro da pasta do projeto.

5. **Login.** Usuário `admin`, senha `admin123`.

## Estrutura

```
banco/rodabem.sql   script que cria o banco de dados inteiro
login.html          tela de login
index.html          página inicial com o menu
css/estilo.css      estilos de todas as páginas
js/comum.js         envio dos formulários com fetch, checagem do login, tabela com alterar/excluir
js/<página>.js      código de cada página
php/validacoes.php  funções de validação usadas por todos os formulários
php/conexao.php     conexão com o banco (PDO) e funções de consulta
php/sessao.php      sessão do usuário: exigirLogin() e exigirAdministrador()
php/login.php       entrada no sistema; logout.php sai; sessao_status.php diz quem está logado
php/<página>.php    recebe e valida os dados de cada formulário (responde em JSON)
paginas/            páginas dos formulários
questionarios/      questionário de desenvolvimento de cada aluno
```

## Tabelas do banco

`usuarios`, `vendedores`, `clientes`, `veiculos` (ligada a clientes), `fornecedores`, `pneus` (ligada a fornecedores) e `vendas` (ligada a clientes, vendedores, pneus e usuários).

Todas as consultas usam *prepared statements* do PDO (os valores vão separados do SQL), e as senhas são gravadas com `password_hash`.
