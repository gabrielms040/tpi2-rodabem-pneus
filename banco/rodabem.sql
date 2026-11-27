-- RodaBem Pneus - script de criacao do banco de dados (PostgreSQL)
--
-- Como executar (cria o banco do zero, apagando o anterior se existir):
--   psql -U postgres -f banco/rodabem.sql
--
-- No pgAdmin: rode primeiro as duas linhas do CREATE/DROP DATABASE,
-- conecte no banco "rodabem" e rode o resto do arquivo (a partir das tabelas).
--
-- Usuario de acesso ao sistema criado no final: login "admin", senha "admin123".

DROP DATABASE IF EXISTS rodabem;
CREATE DATABASE rodabem ENCODING 'UTF8';

\c rodabem

-- ---------------------------------------------------------------
-- usuarios do sistema (tela de login)
-- ---------------------------------------------------------------
CREATE TABLE usuarios (
    id            SERIAL PRIMARY KEY,
    nome          VARCHAR(100) NOT NULL,
    login         VARCHAR(30)  NOT NULL UNIQUE,
    email         VARCHAR(120) NOT NULL,
    senha_hash    VARCHAR(255) NOT NULL,
    perfil        VARCHAR(20)  NOT NULL DEFAULT 'operador'
                  CHECK (perfil IN ('administrador', 'operador')),
    ativo         BOOLEAN      NOT NULL DEFAULT TRUE,
    ultimo_acesso TIMESTAMP,
    criado_em     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- vendedores da loja (usados no registro de vendas)
-- ---------------------------------------------------------------
CREATE TABLE vendedores (
    id        SERIAL PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL,
    cpf       CHAR(11)     NOT NULL UNIQUE,
    telefone  VARCHAR(11)  NOT NULL,
    email     VARCHAR(120) NOT NULL,
    comissao  NUMERIC(5,2) NOT NULL CHECK (comissao BETWEEN 0 AND 15),
    ativo     BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- clientes
-- ---------------------------------------------------------------
CREATE TABLE clientes (
    id              SERIAL PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    cpf             CHAR(11)     NOT NULL UNIQUE,
    data_nascimento DATE         NOT NULL,
    telefone        VARCHAR(11)  NOT NULL,
    email           VARCHAR(120) NOT NULL,
    cidade          VARCHAR(80)  NOT NULL,
    criado_em       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- veiculos (cada veiculo pertence a um cliente)
-- ---------------------------------------------------------------
CREATE TABLE veiculos (
    id          SERIAL PRIMARY KEY,
    cliente_id  INT         NOT NULL REFERENCES clientes(id) ON DELETE RESTRICT,
    placa       CHAR(7)     NOT NULL UNIQUE,
    marca       VARCHAR(50) NOT NULL,
    modelo      VARCHAR(60) NOT NULL,
    ano         INT         NOT NULL,
    medida_pneu VARCHAR(10) NOT NULL,
    criado_em   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- fornecedores de pneus
-- ---------------------------------------------------------------
CREATE TABLE fornecedores (
    id            SERIAL PRIMARY KEY,
    razao_social  VARCHAR(120) NOT NULL,
    cnpj          CHAR(14)     NOT NULL UNIQUE,
    telefone      VARCHAR(11)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    cidade        VARCHAR(80)  NOT NULL,
    uf            CHAR(2)      NOT NULL,
    prazo_entrega INT          NOT NULL CHECK (prazo_entrega BETWEEN 1 AND 60),
    criado_em     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- pneus do estoque (o fornecedor e opcional)
-- ---------------------------------------------------------------
CREATE TABLE pneus (
    id                SERIAL PRIMARY KEY,
    fornecedor_id     INT           REFERENCES fornecedores(id) ON DELETE SET NULL,
    marca             VARCHAR(40)   NOT NULL,
    modelo            VARCHAR(60)   NOT NULL,
    medida            VARCHAR(10)   NOT NULL,
    indice_carga      INT           NOT NULL CHECK (indice_carga BETWEEN 70 AND 110),
    indice_velocidade CHAR(1)       NOT NULL,
    preco_custo       NUMERIC(10,2) NOT NULL CHECK (preco_custo > 0),
    margem            NUMERIC(5,1)  NOT NULL CHECK (margem BETWEEN 0 AND 200),
    preco_venda       NUMERIC(10,2) NOT NULL,
    quantidade        INT           NOT NULL DEFAULT 0 CHECK (quantidade >= 0),
    criado_em         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- vendas
-- ---------------------------------------------------------------
CREATE TABLE vendas (
    id              SERIAL PRIMARY KEY,
    cliente_id      INT           NOT NULL REFERENCES clientes(id)   ON DELETE RESTRICT,
    vendedor_id     INT           NOT NULL REFERENCES vendedores(id) ON DELETE RESTRICT,
    pneu_id         INT           NOT NULL REFERENCES pneus(id)      ON DELETE RESTRICT,
    usuario_id      INT           REFERENCES usuarios(id)            ON DELETE SET NULL,
    quantidade      INT           NOT NULL CHECK (quantidade BETWEEN 1 AND 20),
    preco_unitario  NUMERIC(10,2) NOT NULL,
    forma_pagamento VARCHAR(10)   NOT NULL
                    CHECK (forma_pagamento IN ('dinheiro', 'pix', 'debito', 'credito')),
    parcelas        INT           NOT NULL DEFAULT 1 CHECK (parcelas BETWEEN 1 AND 10),
    montagem        BOOLEAN       NOT NULL DEFAULT FALSE,
    desconto        NUMERIC(10,2) NOT NULL DEFAULT 0,
    acrescimo       NUMERIC(10,2) NOT NULL DEFAULT 0,
    total           NUMERIC(10,2) NOT NULL,
    data_venda      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- dados iniciais
-- ---------------------------------------------------------------

-- senha "admin123" (gerada com password_hash do PHP)
INSERT INTO usuarios (nome, login, email, senha_hash, perfil) VALUES
('Administrador', 'admin', 'admin@rodabem.com.br',
 '$2y$10$X0Ur4weyZmG6qdyCiLKGWO9g6qVeEXOvULRiPOUydSJcGyadTsTc.', 'administrador');

INSERT INTO vendedores (nome, cpf, telefone, email, comissao) VALUES
('Carlos Henrique', '52998224725', '34991112222', 'carlos@rodabem.com.br', 3.00),
('Juliana Martins', '12345678909', '34992223333', 'juliana@rodabem.com.br', 3.50),
('Rafael Souza',    '98765432100', '34993334444', 'rafael@rodabem.com.br', 2.50);

INSERT INTO clientes (nome, cpf, data_nascimento, telefone, email, cidade) VALUES
('Ana Paula Ferreira', '11144477735', '1990-05-14', '34998765432', 'ana.ferreira@email.com', 'Uberlândia'),
('Marcos Vinicius Lima', '93541134780', '1985-11-02', '34997654321', 'marcos.lima@email.com', 'Uberaba');

INSERT INTO veiculos (cliente_id, placa, marca, modelo, ano, medida_pneu) VALUES
(1, 'RFT2B45', 'Volkswagen', 'Gol 1.0', 2019, '175/70R14'),
(2, 'HMK4821', 'Chevrolet', 'Onix LT', 2014, '185/65R15');

INSERT INTO fornecedores (razao_social, cnpj, telefone, email, cidade, uf, prazo_entrega) VALUES
('Distribuidora Triângulo Pneus Ltda', '11222333000181', '3432105500', 'vendas@triangulopneus.com.br', 'Uberlândia', 'MG', 2),
('Paulista Importadora de Pneus S.A.', '45678912000155', '1130405060', 'comercial@paulistapneus.com.br', 'São Paulo', 'SP', 6);

INSERT INTO pneus (fornecedor_id, marca, modelo, medida, indice_carga, indice_velocidade,
                   preco_custo, margem, preco_venda, quantidade) VALUES
(1, 'Pirelli', 'Cinturato P1', '175/70R14', 84, 'T', 280.00, 35.0, 378.00, 12),
(2, 'Michelin', 'Primacy 4', '185/65R15', 88, 'H', 390.00, 30.0, 507.00, 6);
