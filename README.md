# ēkhos - Gerenciador de Loja de Discos e CDs

## Funcionalidades

- **Visualização da Coleção**: Exibe todos os álbuns em um grid responsivo.
- **Busca Dinâmica**: Filtra álbuns em tempo real por título, artista ou gênero.
- **Detalhes do Álbum**: Clique em um álbum para ver informações detalhadas em um modal, como gravadora, ano, duração e exemplares disponíveis.
- **Adicionar Álbum**: Formulário para adicionar novos álbuns à coleção, incluindo upload de imagem da capa.
- **Editar Álbum**: Formulário para editar informações de um álbum existente.
- **Estrutura de Dados Relacional**: Utiliza agregações do MongoDB para juntar dados de diferentes coleções (álbuns, artistas, gêneros, gravadoras).

## Tecnologias Utilizadas

- **Backend**: PHP 8.0+
- **Banco de Dados**: MongoDB
- **Servidor Web**: Apache (utilizado com XAMPP no desenvolvimento)
- **Frontend**: HTML5, CSS3, JavaScript (vanilla)
- **Dependências PHP**: `mongodb/mongodb` (gerenciado via Composer)

---

## Como Executar o Projeto

Siga os passos abaixo para configurar e rodar a aplicação em seu ambiente local.

### 1. Pré-requisitos

Antes de começar, garanta que você tenha os seguintes softwares instalados:

- **Ambiente de Servidor Local**: XAMPP, WAMP ou similar, que inclua Apache e PHP.
- **PHP**: Versão 8.0 ou superior.
- **Extensão MongoDB para PHP**: A extensão `mongodb` deve estar habilitada no seu `php.ini`.
- **Composer**: Para gerenciar as dependências do PHP. Instale o Composer.
- **Servidor MongoDB**: Uma instância do MongoDB deve estar rodando localmente (ou acessível pela rede).

### 2. Instalação

**a. Clone o Repositório**

Clone ou baixe este projeto para o diretório raiz do seu servidor web (ex: `C:\xampp\htdocs\` no XAMPP).

```bash
git clone <url-do-repositorio> disco_imaginario_site
cd disco_imaginario_site
```

**b. Instale as Dependências**

Abra o terminal na pasta do projeto e execute o Composer para instalar a biblioteca do MongoDB.

```bash
composer install
```

Isso criará a pasta `vendor/` com os arquivos necessários.

**c. Crie o Diretório de Imagens**

Crie uma pasta chamada `imagens` na raiz do projeto. O servidor precisa de permissão de escrita nesta pasta para salvar as capas dos álbuns.

### 3. Configuração do Banco de Dados

A aplicação espera um banco de dados chamado `CDs_&_vinil` com coleções e dados específicos.

**a. Crie o Banco de Dados e as Coleções**

Conecte-se ao seu servidor MongoDB e crie o banco de dados e as seguintes coleções: `artistas`, `generos_musicais`, e `gravadoras`.

**b. Insira os Dados Iniciais (Exemplo)**

Para que a aplicação funcione, você precisa de alguns dados iniciais. Use os exemplos abaixo para popular suas coleções.

**Coleção: `artistas`**
```json
[
  { "_id": 1, "nome_artista": "Daft Punk" },
  { "_id": 2, "nome_artista": "Pink Floyd" }
]
```

**Coleção: `generos_musicais`**
```json
[
  { "_id": 1, "nome_genero_musical": "Eletrônica" },
  { "_id": 2, "nome_genero_musical": "House" },
  { "_id": 3, "nome_genero_musical": "Rock Progressivo" }
]
```

**Coleção: `gravadoras`** (Note que os álbuns são subdocumentos)
```json
[
  {
    "_id": 101,
    "nome_gravadora": "Columbia Records",
    "albuns": []
  }
]
```

### 4. Verificação e Acesso

**a. Verifique o Ambiente**
Acesse `http://localhost/disco_imaginario_site/check_env.php` no seu navegador para diagnosticar se o PHP, a extensão do MongoDB e o autoloader do Composer estão configurados corretamente.

**b. Teste a Conexão com o Banco**
Acesse `http://localhost/disco_imaginario_site/mongo_test.php` para verificar se a aplicação consegue se conectar ao banco de dados e listar as coleções.

**c. Inicie a Aplicação**
Se tudo estiver correto, acesse a página principal:
**`http://localhost/disco_imaginario_site/`**

Agora você pode visualizar, adicionar e editar os álbuns da sua coleção!
