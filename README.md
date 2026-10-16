# Scan Magic

Aplicação web para catalogar coleções de cartas de Magic: The Gathering a partir de fotos. O usuário envia ou captura a imagem de uma carta, o texto é extraído via OCR (Tesseract), o nome é comparado com a base da Scryfall e, após a confirmação, a carta é adicionada à coleção.

## Funcionalidades

- Leitura de cartas por upload de imagem ou captura pela câmera do navegador
- Reconhecimento de texto com Tesseract OCR e correspondência aproximada de nomes (similaridade e distância de Levenshtein)
- Busca manual de cartas quando o reconhecimento automático não encontra um resultado confiável
- Fila de leituras pendentes para revisão e confirmação posterior
- Gestão da coleção, com quantidade por carta e página de detalhes
- Painel com resumo da coleção
- Importação de dados da Scryfall (tipos, cores, legalidades, rulings e nomes em outros idiomas)
- Autenticação, verificação de e-mail e configurações de perfil via Laravel Fortify
- Limpeza diária de imagens e leituras abandonadas

## Stack

| Camada     | Tecnologia                                        |
|------------|---------------------------------------------------|
| Backend    | PHP 8.4, Laravel 13, Laravel Fortify               |
| Frontend   | React 19, Inertia.js 3, TypeScript, Tailwind CSS 4 |
| Rotas      | Laravel Wayfinder                                  |
| OCR        | Tesseract                                          |
| Dados      | Scryfall API                                       |
| Banco      | SQLite (desenvolvimento), PostgreSQL (produção)    |
| Testes     | Pest, PHPStan (Larastan), Pint                     |

## Requisitos

- PHP 8.4 com Composer 2
- Node.js 22 e npm
- Tesseract OCR com o pacote de idioma inglês (`eng`)

Instalação do Tesseract:

```bash
# Debian/Ubuntu
sudo apt-get install tesseract-ocr tesseract-ocr-eng

# macOS
brew install tesseract
```

No Windows, use o instalador oficial. O caminho padrão esperado é `C:\Program Files\Tesseract-OCR\tesseract.exe`; caso o binário não seja encontrado nesse caminho, a aplicação utiliza `tesseract` disponível no `PATH`.

## Instalação

```bash
git clone <url-do-repositorio> scan-magic
cd scan-magic
composer setup
```

O script `setup` instala as dependências PHP e JavaScript, cria o arquivo `.env`, gera a chave da aplicação, executa as migrations e compila os assets.

## Desenvolvimento

```bash
composer dev
```

Inicia em paralelo o servidor HTTP, o worker de filas e o Vite. A aplicação fica disponível em `http://localhost:8000`.

Para executar as tarefas agendadas localmente:

```bash
php artisan schedule:work
```

## Configuração

Variáveis específicas do projeto, definidas no `.env`:

| Variável                       | Padrão                     | Descrição                                          |
|--------------------------------|----------------------------|----------------------------------------------------|
| `SCRYFALL_API_BASE_URL`        | `https://api.scryfall.com` | URL base da API da Scryfall                        |
| `SCRYFALL_API_TIMEOUT`         | `10`                       | Tempo limite das requisições, em segundos          |
| `SCRYFALL_API_CONNECT_TIMEOUT` | `3`                        | Tempo limite de conexão, em segundos               |
| `SCRYFALL_API_CACHE_TTL`       | `3600`                     | Tempo de cache das respostas, em segundos          |
| `TESSERACT_BINARY`             | `C:\Program Files\Tesseract-OCR\tesseract.exe` | Caminho do executável do Tesseract |
| `TESSERACT_TIMEOUT`            | `15`                       | Tempo limite do processamento OCR, em segundos     |
| `SCANNER_RETENTION_DAYS`       | `7`                        | Dias de retenção de imagens e leituras abandonadas |

## Fluxo de leitura

1. A imagem é armazenada no disco local e um registro `CardScan` é criado com status `processing`.
2. O Tesseract extrai o texto da imagem.
3. As primeiras linhas válidas são normalizadas e pesquisadas na Scryfall.
4. Os candidatos recebem uma pontuação de confiança e o melhor resultado é sugerido.
5. A leitura passa para `needs_confirmation` (ou `failed`, se nenhum nome for identificado).
6. Ao confirmar, os dados da carta são importados e a quantidade é registrada na coleção do usuário.

## Manutenção

O comando abaixo remove imagens antigas e leituras não confirmadas. Ele é agendado para execução diária:

```bash
php artisan scans:prune --days=7
```

## Qualidade e testes

```bash
composer test        # Pint, PHPStan e suíte de testes
composer ci:check    # verificações de frontend, tipos e testes
composer lint        # formatação do código PHP
npm run types:check  # verificação de tipos TypeScript
```

O workflow do GitHub Actions em `.github/workflows/tests.yml` executa `composer ci:check` em cada push para `main` e em pull requests.

## Deploy

O arquivo `Dockerfile.vercel` gera uma imagem com PHP 8.4, Tesseract, extensão `pdo_pgsql` e os assets já compilados. A porta é definida pela variável `PORT` (padrão `80`).

Em produção, configure ao menos `APP_KEY`, `APP_URL`, as credenciais do banco de dados e garanta que o agendador e o worker de filas estejam em execução.

## Licença

Distribuído sob a licença MIT.
