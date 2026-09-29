// funcoes usadas por todas as paginas de formulario

// liga o formulario ao arquivo PHP. Quando o usuario clica em salvar,
// os dados sao enviados com fetch (sem recarregar a pagina)
function prepararFormulario(idFormulario, arquivoPhp) {
    const formulario = document.getElementById(idFormulario);

    formulario.addEventListener('submit', function (evento) {
        evento.preventDefault(); // impede o envio padrao do navegador
        enviarDados(formulario, arquivoPhp);
    });

    formulario.addEventListener('reset', function () {
        limparResultado();
    });
}

async function enviarDados(formulario, arquivoPhp) {
    const caixa = document.getElementById('resultado');
    const dados = new FormData(formulario);

    caixa.className = 'resultado';
    caixa.textContent = 'Enviando...';

    try {
        const resposta = await fetch(arquivoPhp, {
            method: 'POST',
            body: dados
        });
        const json = await resposta.json();
        mostrarResultado(json);
    } catch (erro) {
        caixa.className = 'resultado erro';
        caixa.textContent = 'Não foi possível enviar os dados. Confira se o servidor PHP está rodando.';
        console.log(erro);
    }
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

// mascaras simples para ajudar na digitacao
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
