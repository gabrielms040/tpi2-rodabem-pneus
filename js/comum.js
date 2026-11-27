// funcoes usadas por todas as paginas do sistema

// caminho da raiz do sistema, descoberto pelo endereco deste arquivo:
// no index.html e "" e nas paginas da pasta paginas/ e "../".
// Assim nenhum link depende do nome da pasta onde o sistema foi colocado dentro do htdocs.
const RAIZ = document.currentScript.getAttribute('src').replace('js/comum.js', '');

// ---------------------------------------------------------------
// sessao: toda pagina (menos a de login) confere se o usuario fez login
// ---------------------------------------------------------------
if (!document.body.hasAttribute('data-publica')) {
    verificarSessao();
}

async function verificarSessao() {
    try {
        const resposta = await fetch(RAIZ + 'php/sessao_status.php');
        const json = await resposta.json();
        if (!json.sucesso) {
            irParaLogin();
            return;
        }
        mostrarUsuario(json.dados);
    } catch (erro) {
        console.log(erro);
        alert('Não foi possível falar com o servidor. Confira se o Apache/PHP está rodando.');
    }
    document.documentElement.classList.add('sessao-ok');
}

function irParaLogin() {
    window.location.href = RAIZ + 'login.html';
}

// mostra no topo o nome de quem esta logado e o botao de sair
function mostrarUsuario(usuario) {
    const topo = document.querySelector('.topo-conteudo');
    const caixa = document.createElement('div');
    caixa.className = 'usuario-logado';

    const nome = document.createElement('span');
    nome.textContent = 'Olá, ' + usuario.nome;
    caixa.appendChild(nome);

    const sair = document.createElement('button');
    sair.type = 'button';
    sair.className = 'botao-sair';
    sair.textContent = 'Sair';
    sair.addEventListener('click', sairDoSistema);
    caixa.appendChild(sair);

    topo.appendChild(caixa);

    // itens que so o administrador pode ver (ex.: cadastro de usuarios)
    if (usuario.perfil !== 'administrador') {
        for (const item of document.querySelectorAll('.so-admin')) {
            item.remove();
        }
    }
}

async function sairDoSistema() {
    try {
        await fetch(RAIZ + 'php/logout.php', { method: 'POST' });
    } finally {
        irParaLogin();
    }
}

// ---------------------------------------------------------------
// envio dos formularios
// ---------------------------------------------------------------

// liga o formulario ao arquivo PHP. Quando o usuario clica em salvar,
// os dados sao enviados com fetch (sem recarregar a pagina).
// aoSalvar (opcional) e chamada quando o PHP responde com sucesso
function prepararFormulario(idFormulario, arquivoPhp, aoSalvar) {
    const formulario = document.getElementById(idFormulario);

    formulario.addEventListener('submit', function (evento) {
        evento.preventDefault(); // impede o envio padrao do navegador
        enviarDados(formulario, arquivoPhp, aoSalvar);
    });

    // so limpa a resposta quando o proprio usuario clica em "Limpar"
    formulario.addEventListener('reset', function () {
        if (!formulario.dataset.manterResultado) {
            limparResultado();
        }
    });
}

// limpa os campos do formulario sem apagar a resposta do PHP que esta na tela
function limparFormulario(formulario) {
    formulario.dataset.manterResultado = 'sim';
    formulario.reset();
    delete formulario.dataset.manterResultado;
}

async function enviarDados(formulario, arquivoPhp, aoSalvar) {
    const caixa = document.getElementById('resultado');
    const dados = new FormData(formulario);

    caixa.className = 'resultado';
    caixa.textContent = 'Enviando...';

    try {
        const json = await chamarPhp(arquivoPhp, { method: 'POST', body: dados });
        mostrarResultado(json);
        if (json.sucesso && aoSalvar) {
            aoSalvar(json);
        }
    } catch (erro) {
        caixa.className = 'resultado erro';
        caixa.textContent = 'Não foi possível enviar os dados. Confira se o servidor PHP está rodando.';
        console.log(erro);
    }
}

// faz a requisicao e devolve o JSON; se a sessao acabou, volta para o login
async function chamarPhp(endereco, opcoes) {
    const resposta = await fetch(endereco, opcoes);
    if (resposta.status === 401) {
        irParaLogin();
        throw new Error('Sessão expirada');
    }
    return resposta.json();
}

// monta na tela a resposta que o PHP devolveu
// formato esperado: { sucesso, mensagem, erros: [], dados: { rotulo: valor } }
function mostrarResultado(json) {
    const caixa = document.getElementById('resultado');
    caixa.innerHTML = '';

    const titulo = document.createElement('h2');
    titulo.textContent = json.mensagem;
    caixa.appendChild(titulo);

    if (json.sucesso) {
        caixa.className = 'resultado sucesso';
        const tabela = document.createElement('table');
        for (const rotulo in json.dados) {
            const linha = tabela.insertRow();
            linha.insertCell().textContent = rotulo;
            linha.insertCell().textContent = json.dados[rotulo];
        }
        caixa.appendChild(tabela);
    } else {
        caixa.className = 'resultado erro';
        const lista = document.createElement('ul');
        for (const erro of json.erros) {
            const item = document.createElement('li');
            item.textContent = erro;
            lista.appendChild(item);
        }
        caixa.appendChild(lista);
    }
}

function limparResultado() {
    const caixa = document.getElementById('resultado');
    caixa.className = 'resultado';
    caixa.innerHTML = '';
}

// ---------------------------------------------------------------
// cadastro completo (CRUD): formulario + tabela com alterar e excluir
// ---------------------------------------------------------------

// config: { formulario: id do form, php: arquivo PHP, nome: "cliente", ... }
function iniciarCadastro(config) {
    const formulario = document.getElementById(config.formulario);
    const botaoSalvar = formulario.querySelector('button[type="submit"]');
    const textoSalvar = botaoSalvar.textContent;
    const aviso = document.getElementById('aviso-edicao');

    // depois de salvar: limpa o formulario, sai do modo de edicao e atualiza a tabela
    prepararFormulario(config.formulario, config.php, function () {
        limparFormulario(formulario);
        sairDaEdicao();
        carregarLista();
    });

    // o botao "Limpar" tambem cancela a alteracao
    formulario.addEventListener('reset', function () {
        sairDaEdicao();
    });

    function sairDaEdicao() {
        formulario.elements['id'].value = '';
        botaoSalvar.textContent = textoSalvar;
        aviso.hidden = true;
    }

    async function carregarLista() {
        const area = document.getElementById('lista');
        try {
            const json = await chamarPhp(config.php + '?acao=listar');
            if (!json.sucesso) {
                area.textContent = json.erros.join(' ');
                return;
            }
            montarTabela(area, json.dados, alterar, excluir);
        } catch (erro) {
            area.textContent = 'Não foi possível carregar a lista.';
            console.log(erro);
        }
    }

    // busca o registro no banco e coloca os valores no formulario
    async function alterar(id) {
        const json = await chamarPhp(config.php + '?acao=buscar&id=' + encodeURIComponent(id));
        if (!json.sucesso) {
            mostrarResultado(json);
            return;
        }
        limparFormulario(formulario);
        preencherFormulario(formulario, json.dados);
        limparResultado();
        botaoSalvar.textContent = 'Salvar alterações';
        aviso.textContent = 'Alterando o ' + config.nome + ' nº ' + id + '. Clique em "Limpar" para cancelar.';
        aviso.hidden = false;
        if (config.aoAlterar) {
            config.aoAlterar(json.dados);
        }
        formulario.scrollIntoView({ behavior: 'smooth' });
    }

    async function excluir(id, descricao) {
        if (!confirm('Excluir o ' + config.nome + ' "' + descricao + '"? Essa ação não pode ser desfeita.')) {
            return;
        }
        const dados = new FormData();
        dados.append('acao', 'excluir');
        dados.append('id', id);
        const json = await chamarPhp(config.php, { method: 'POST', body: dados });
        mostrarResultado(json);
        if (json.sucesso) {
            if (formulario.elements['id'].value === String(id)) {
                limparFormulario(formulario);
                sairDaEdicao();
            }
            carregarLista();
        }
    }

    carregarLista();
}

// coloca cada valor no campo de mesmo nome
function preencherFormulario(formulario, dados) {
    for (const nome in dados) {
        const campo = formulario.elements[nome];
        if (!campo) {
            continue;
        }
        if (campo.type === 'checkbox') {
            campo.checked = (dados[nome] === 'sim');
        } else {
            campo.value = dados[nome];
        }
    }
}

// monta a tabela com os registros que vieram do banco
// cada linha tem os botoes "Alterar" e "Excluir"
function montarTabela(area, registros, aoAlterar, aoExcluir) {
    area.innerHTML = '';

    if (registros.length === 0) {
        const vazio = document.createElement('p');
        vazio.className = 'descricao';
        vazio.textContent = 'Nenhum registro cadastrado ainda.';
        area.appendChild(vazio);
        return;
    }

    const tabela = document.createElement('table');
    tabela.className = 'tabela';

    const colunas = Object.keys(registros[0]).filter(function (c) { return c !== 'id'; });
    const cabecalho = tabela.createTHead().insertRow();
    for (const coluna of colunas) {
        const th = document.createElement('th');
        th.textContent = coluna;
        cabecalho.appendChild(th);
    }
    const thAcoes = document.createElement('th');
    thAcoes.textContent = 'Ações';
    cabecalho.appendChild(thAcoes);

    const corpo = tabela.createTBody();
    for (const registro of registros) {
        const linha = corpo.insertRow();
        for (const coluna of colunas) {
            const celula = linha.insertCell();
            celula.textContent = registro[coluna];
            celula.dataset.rotulo = coluna; // usado no celular, onde a tabela vira lista
        }

        const acoes = linha.insertCell();
        acoes.className = 'acoes-linha';

        const botaoAlterar = document.createElement('button');
        botaoAlterar.type = 'button';
        botaoAlterar.className = 'botao-secundario botao-pequeno';
        botaoAlterar.textContent = 'Alterar';
        botaoAlterar.addEventListener('click', function () { aoAlterar(registro.id); });
        acoes.appendChild(botaoAlterar);

        const botaoExcluir = document.createElement('button');
        botaoExcluir.type = 'button';
        botaoExcluir.className = 'botao-perigo botao-pequeno';
        botaoExcluir.textContent = 'Excluir';
        botaoExcluir.addEventListener('click', function () {
            aoExcluir(registro.id, registro[colunas[0]]);
        });
        acoes.appendChild(botaoExcluir);
    }

    area.appendChild(tabela);
}

// preenche um <select> com registros do banco (ex.: vendedores ativos na tela de vendas)
// o PHP deve devolver uma lista de { id, nome }
async function carregarOpcoes(idSelect, endereco) {
    const select = document.getElementById(idSelect);
    const json = await chamarPhp(endereco);
    for (const item of json.dados) {
        const opcao = document.createElement('option');
        opcao.value = item.id;
        opcao.textContent = item.nome;
        select.appendChild(opcao);
    }
}

// ---------------------------------------------------------------
// mascaras simples para ajudar na digitacao
// ---------------------------------------------------------------
function mascaraCpf(campo) {
    campo.addEventListener('input', function () {
        let v = campo.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        campo.value = v;
    });
}

function mascaraTelefone(campo) {
    campo.addEventListener('input', function () {
        let v = campo.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) {
            v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
        }
        campo.value = v;
    });
}
