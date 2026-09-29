# RodaBem Pneus

Sistema administrativo de uma loja de pneus, desenvolvido para o Trabalho (Momento I) da disciplina Tecnologias para Internet II - Uniube.

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
| Pneus | Lucas | Cadastro de pneus, calcula o preço de venda e converte os índices de carga e velocidade |
| Vendas | Lucas | Registro de venda com descontos, montagem, parcelamento e brinde |

## Como executar

O PHP precisa de um servidor para rodar. Abrir o `index.html` direto no navegador não funciona para enviar os formulários.

Com o PHP instalado, dentro da pasta do projeto:

```
php -S localhost:8000
```

Depois é só acessar `http://localhost:8000` no navegador.

Também funciona com o XAMPP, copiando a pasta para dentro de `htdocs`.

## Estrutura

```
index.html          página inicial com o menu
css/estilo.css      estilos de todas as páginas
js/comum.js         envio dos formulários com fetch e exibição da resposta
js/<página>.js      código de cada página
php/validacoes.php  funções de validação usadas por todos os formulários
php/<página>.php    recebe e valida os dados de cada formulário (responde em JSON)
paginas/            páginas dos formulários
questionarios/      questionário de desenvolvimento de cada aluno
```

Nesta etapa o sistema não grava em banco de dados. O PHP apenas recebe, valida e processa os dados.
