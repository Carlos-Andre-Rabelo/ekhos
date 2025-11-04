# ēkhos - Gerenciador de Loja de Discos e CDs

## Funcionalidades

- **Visualização da Coleção**: Exibe todos os álbuns em um grid responsivo.
- **Busca Dinâmica**: Filtra álbuns em tempo real por título, artista ou gênero.
- **Detalhes do Álbum**: Clique em um álbum para ver informações detalhadas em um modal, como gravadora, ano, duração, formatos disponíveis e preços.
- **Adicionar Álbum**: Formulário completo para adicionar novos álbuns, incluindo upload de imagem da capa e múltiplos formatos (CD, Vinil) com preço e estoque.
- **Adição de Entidades Relacionadas**: Adicione novas gravadoras, artistas e gêneros diretamente pela página de adição de álbuns, sem interromper o fluxo de trabalho.
- **Editar Álbum**: Formulário para editar informações de um álbum existente.
- **Estrutura de Dados Desnormalizada**: Utiliza agregações (`$lookup`) do MongoDB para juntar dados de coleções separadas (`albuns`, `artistas`, `generos_musicais`, `gravadoras`), simulando relacionamentos.

## Tecnologias Utilizadas

- **Backend**: PHP 8.0+
- **Banco de Dados**: MongoDB
- **Servidor Web**: Apache (utilizado com XAMPP no desenvolvimento)
- **Frontend**: HTML5, CSS3, JavaScript (vanilla)
- **Dependências PHP**: `mongodb/mongodb` (gerenciado via Composer)

---

## Como Executar o Projeto

Siga os passos abaixo para configurar e rodar a aplicação em seu ambiente local.

### 1. Pré-requisitos Iniciais

Antes de começar, garanta que você tenha os seguintes softwares instalados:

- **Ambiente de Servidor Local**: **XAMPP** (ou similar) com Apache e PHP 8.0+.
- **Composer**: O [gerenciador de pacotes do PHP](https://getcomposer.org/download/).
- **Servidor MongoDB**: [MongoDB Community Server](https://www.mongodb.com/try/download/community) deve estar instalado e rodando (geralmente como um "Serviço" automático do Windows).

### 2. Configuração do Ambiente PHP (Passo Crítico)

Para o PHP se comunicar com o MongoDB, precisamos instalar a **extensão** correta antes de usar o Composer.

**a. Descubra a Versão do seu PHP**
Crie um arquivo `info.php` na pasta `htdocs` com o conteúdo `<?php phpinfo(); ?>` e acesse `http://localhost/info.php`. Anote três coisas:
1.  **PHP Version** (ex: `8.2.12`)
2.  **Architecture** (ex: `x64`)
3.  **Thread Safety** (ex: `TS` - Thread Safe)

**b. Baixe a Extensão (DLL)**
Vá ao [site do PECL para a extensão MongoDB](https://pecl.php.net/package/mongodb/windows) e baixe o arquivo `.zip` que corresponda **exatamente** às suas configurações (ex: `8.2 Thread Safe (TS) x64`).

**c. Instale a Extensão**
1.  Dentro do ZIP, pegue o arquivo `php_mongodb.dll`.
2.  Copie este arquivo para a pasta de extensões do XAMPP: `C:\xampp\php\ext\`
3.  Abra seu arquivo `php.ini` (no XAMPP: Apache -> Config -> `php.ini`).
4.  Adicione a seguinte linha no final do arquivo, na seção de extensões:
    ```ini
    extension=mongodb
    ```
5.  **Salve** o `php.ini` e **REINICIE O APACHE** (clique em "Stop" e "Start" no painel do XAMPP).

**d. Verifique a Instalação**
Atualize a página `info.php`. Se tudo deu certo, você verá uma seção inteira dedicada ao **"mongodb"**.

### 3. Instalação do Projeto

**a. Clone o Repositório**
Clone este projeto para o diretório `htdocs` do seu XAMPP.

```bash
cd C:\xampp\htdocs\
git clone <url-do-repositorio> ekhos
cd ekhos
```
**b. Instale as Dependências (com o PHP já configurado)**
Abra o terminal na pasta ekhos e rode:

```bash
composer install
```
Isso lerá o composer.json e instalará a biblioteca mongodb/mongodb dentro da pasta vendor/. Isso só funciona porque você completou o Passo 2.

**c. Crie o Diretório de Imagens**
Crie uma pasta chamada imagens na raiz do projeto (ekhos/imagens). O servidor precisa de permissão de escrita nesta pasta para salvar as capas dos álbuns.

### 4. Configuração do Banco de Dados
A aplicação espera um banco de dados chamado `CDs_&_vinil`. A forma mais fácil de populá-lo é usando os arquivos JSON fornecidos na pasta `MongoDB/` deste repositório.

Abra o Prompt de Comando (não o Git Bash) e use o `mongoimport` para carregar cada arquivo. Note como o nome da `--collection` está no plural, como na sua imagem do Compass:

```bash
# Sintaxe: mongoimport --db=<nome-db> --collection=<nome-colecao> --file=<caminho-json> --jsonArray

# Importa MongoDB/albuns.json para a coleção "albuns"
mongoimport --db=CDs_&_vinil --collection=albuns --file=MongoDB/albuns.json --jsonArray

# Importa MongoDB/artista.json para a coleção "artistas"
mongoimport --db=CDs_&_vinil --collection=artistas --file=MongoDB/artista.json --jsonArray

# Importa MongoDB/clientes.json para a coleção "clientes"
mongoimport --db=CDs_&_vinil --collection=clientes --file=MongoDB/clientes.json --jsonArray

# Importa MongoDB/fornecedores.json para a coleção "fornecedores"
mongoimport --db=CDs_&_vinil --collection=fornecedores --file=MongoDB/fornecedores.json --jsonArray

# Importa MongoDB/generos_musicais.json para a coleção "generos_musicais"
mongoimport --db=CDs_&_vinil --collection=generos_musicais --file=MongoDB/generos_musicais.json --jsonArray

# Importa MongoDB/gravadora.json para a coleção "gravadoras"
mongoimport --db=CDs_&_vinil --collection=gravadoras --file=MongoDB/gravadora.json --jsonArray
```
Nota: Você deve rodar esses comandos de dentro da pasta `ekhos` para que os caminhos (`MongoDB/albuns.json`) funcionem.

### 5. Verificação e Acesso
**a. Verifique o Ambiente**
Acesse `http://localhost/ekhos/check_env.php` no seu navegador para diagnosticar se o PHP, a extensão do MongoDB e o autoloader do Composer estão configurados corretamente.

**b. Teste a Conexão com o Banco**
Acesse `http://localhost/ekhos/mongo_test.php` para verificar se a aplicação consegue se conectar ao banco de dados `CDs_&_vinil` e listar as coleções.

**c. Inicie a Aplicação**
Se tudo estiver correto, acesse a página principal: `http://localhost/ekhos/`

Agora você pode visualizar, adicionar e editar os álbuns da sua coleção!

---

## ❓ FAQ - Possíveis Erros
**Erro: `composer require/install` falha com "ext-mongodb is missing" (ext-mongodb está faltando).**

**Causa**: Você tentou rodar o Composer antes de instalar a extensão `php_mongodb.dll`, ou pulou o Passo 2.

**Solução**: Siga o Passo 2 (Configuração do Ambiente PHP) cuidadosamente. Verifique se o `php.ini` foi salvo e se o Apache foi reiniciado.

**Erro: `Fatal error: Uncaught Error: Class "MongoDB\Client" not found in C:\...`**

**Causa 1**: Você esqueceu de rodar `composer install` na pasta do projeto.
**Solução 1**: Rode `composer install` no terminal.

**Causa 2**: Seu script PHP (ex: `index.php`) não incluiu o autoloader do Composer.
**Solução 2**: Garanta que a linha `require_once 'vendor/autoload.php';` esteja no topo do seu script PHP que precisa da conexão.

**Erro: `mongo_test.php` mostra "Failed to connect" (Falha na conexão) ou "Connection timed out" (Tempo de conexão esgotado).**

**Causa**: O seu servidor MongoDB não está rodando.

**Solução**: Abra o "Gerenciador de Tarefas" -> "Serviços" e procure por "MongoDB Server (MongoDB)". O status deve ser "Em execução". Se estiver parado, clique com o botão direito e "Iniciar".

**Erro: `check_env.php` diz que "Extensão MongoDB (ext-mongodb) NÃO está instalada."**

**Causa 1**: Você esqueceu de reiniciar o Apache depois de editar o `php.ini`.
**Solução 1**: Reinicie o Apache no painel do XAMPP (Stop/Start).

**Causa 2**: Você baixou o arquivo `.dll` errado (ex: versão NTS em vez de TS, ou x86 em vez de x64, ou para a versão errada do PHP).
**Solução 2**: Refaça o Passo 2a, verificando as 3 informações no `phpinfo()` com extrema atenção, e baixe o arquivo correto.

**Erro: `mongoimport` não é reconhecido como um comando.**

**Causa**: O local dos binários do MongoDB não está no "Path" do seu sistema.

**Solução**: Você precisa rodar o `mongoimport` de dentro da pasta onde ele foi instalado (ex: `C:\Program Files\MongoDB\Server\6.0\bin\`). A forma mais fácil é adicionar essa pasta às suas Variáveis de Ambiente do Windows.