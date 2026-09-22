# Laravel RAG Laboratory

Projeto educacional para construir, observar e medir um sistema de Retrieval-Augmented Generation (RAG). O objetivo não é apenas responder perguntas, mas descobrir qual combinação de chunking, embedding e retrieval funciona melhor para os documentos disponíveis.

Atualmente, a fonte de conhecimento são arquivos `.txt` colocados na pasta `documents/`.

## Objetivos do projeto

Este laboratório permite:

- importar documentos de texto;
- dividir os documentos usando diferentes estratégias de chunking;
- gerar embeddings localmente com Ollama;
- armazenar embeddings no PostgreSQL com pgvector;
- recuperar contexto por busca vetorial ou híbrida;
- enviar o contexto recuperado para um modelo de linguagem;
- medir retrieval com métricas determinísticas;
- avaliar respostas usando LLM-as-a-judge;
- experimentar combinações e encontrar a melhor relação entre qualidade e quantidade de contexto.

O fluxo principal é:

```text
documents/*.txt
        ↓
Chunking Strategy
        ↓
Embedding Model
        ↓
PostgreSQL + pgvector
        ↓
Vector ou Hybrid Retrieval
        ↓
Contexto selecionado
        ↓
Modelo de linguagem
        ↓
Resposta
```

## Stack

- Laravel e PHP 8.3;
- Laravel AI SDK;
- Docker Compose;
- PostgreSQL 16;
- pgvector;
- Ollama;
- `nomic-embed-text-v2-moe` para embeddings de 768 dimensões;
- `qwen3.5:4b` para respostas e avaliação local.

Todo o fluxo de IA funciona localmente. O projeto não utiliza OpenAI nesta etapa.

## Serviços do Docker Compose

O ambiente possui quatro serviços:

| Serviço | Responsabilidade |
|---|---|
| `app` | Aplicação Laravel e comandos Artisan |
| `postgres` | Banco PostgreSQL com pgvector |
| `ollama` | Servidor local dos modelos de IA |
| `ollama-pull` | Baixa os modelos configurados antes de liberar a aplicação |

Os volumes `postgres_data`, `ollama_data` e `app_vendor` preservam banco, modelos e dependências entre reinicializações.

## Requisitos

- Docker Desktop ou Docker Engine;
- Docker Compose v2;
- pelo menos alguns gigabytes livres para imagens e modelos;
- portas `8000`, `5432` e `11434` disponíveis.

Não é necessário instalar PHP, Composer, PostgreSQL ou Ollama diretamente na máquina.

## Setup

### 1. Criar o arquivo de ambiente

Linux ou macOS:

```bash
cp .env.example .env
```

PowerShell:

```powershell
Copy-Item .env.example .env
```

### 2. Subir os containers

```bash
docker compose up -d --build
```

Nas próximas execuções, basta usar:

```bash
docker compose up -d
```

Na primeira execução, o Docker irá:

1. construir a imagem PHP;
2. instalar as dependências do Composer;
3. iniciar o PostgreSQL;
4. iniciar o Ollama;
5. baixar os modelos de embedding e geração.

O primeiro startup pode demorar por causa do download dos modelos. Acompanhe com:

```bash
docker compose ps
docker compose logs -f ollama-pull
```

Quando o serviço estiver pronto, a aplicação estará disponível em `http://localhost:8000`.

### 3. Gerar a chave da aplicação

```bash
docker compose exec app php artisan key:generate
```

### 4. Executar as migrations

```bash
docker compose exec app php artisan migrate
```

As migrations habilitam a extensão `vector`, criam `documents` e `document_chunks`, adicionam a coluna `embedding vector(768)` e criam o índice Full Text Search em português.

### 5. Adicionar documentos

Coloque arquivos na pasta:

```text
documents/
```

Por enquanto, somente arquivos `.txt` são importados:

```text
documents/manual_da_empresa.txt
documents/politicas_internas.txt
```

### 6. Indexar os documentos

```bash
docker compose exec app php artisan embed:documents
```

Esse comando:

1. lê `documents/*.txt`;
2. cria ou atualiza cada `Document`;
3. aplica o chunking oficial;
4. gera os embeddings;
5. recria os `DocumentChunk`;
6. salva conteúdo, posição e vetor no PostgreSQL.

## Configuração do RAG

A configuração foi dividida por responsabilidade.

### Configuração oficial

`config/rag.php` representa o RAG usado por `embed:documents`, `rag:ask` e `rag:evaluate`. Os valores vêm do `.env`.

Configuração padrão:

```env
RAG_CHUNKING_STRATEGY=section
RAG_CHUNK_MAX_CHARACTERS=1500
RAG_CHUNK_OVERLAP=100
RAG_CHUNK_MINIMUM_SIMILARITY=0.65

RAG_EMBEDDING_STRATEGY=nomic_768
RAG_EMBEDDING_INPUT_MODE=asymmetric

RAG_RETRIEVAL_STRATEGY=hybrid
RAG_MINIMUM_SIMILARITY=0.25
RAG_CONTEXT_LIMIT=3
RAG_CANDIDATE_LIMIT=20
RAG_RRF_K=60
```

Após alterar o `.env`, limpe o cache e recrie o índice:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan embed:documents
```

### Catálogo de estratégias

`config/rag-strategies.php` registra as implementações disponíveis:

- estratégias de chunking;
- modelos e dimensões de embedding;
- modos de entrada do embedding;
- estratégias de retrieval.

Esse arquivo informa o que existe. O `config/rag.php` informa o que está ativo.

### Configuração de avaliação

`config/rag-evaluation.php` contém:

- o golden dataset de perguntas e respostas esperadas;
- a matriz de experimentos;
- thresholds e limites testados;
- instruções e modelo do LLM-as-a-judge;
- meta mínima de qualidade do experimento.

## Conceitos implementados

### Chunking

Chunking divide documentos extensos em unidades menores. Isso evita enviar o documento inteiro para o modelo e melhora a precisão da recuperação.

Estratégias disponíveis:

| Estratégia | Funcionamento |
|---|---|
| `paragraph` | Agrupa parágrafos completos até o tamanho máximo |
| `fixed` | Divide por quantidade de caracteres e aceita overlap |
| `section` | Respeita títulos e mantém o título em cada chunk |
| `semantic` | Compara embeddings de unidades vizinhas e agrupa assuntos relacionados |
| `section_semantic` | Preserva seções e aplica agrupamento semântico dentro delas |

`max_characters` é um limite aproximado. `overlap` repete parte do chunk anterior no seguinte. `minimum_similarity` controla o quanto duas unidades precisam ser semanticamente relacionadas para permanecerem juntas.

### Embeddings

Embedding transforma texto em um vetor numérico. Textos semanticamente relacionados tendem a gerar vetores próximos.

O projeto usa vetores de 768 dimensões:

```text
Texto → Ollama → [0.018, -0.042, ..., 0.031]
```

No modo assimétrico do Nomic:

```text
Documento: search_document: conteúdo
Pergunta:  search_query: pergunta
```

Os prefixes informam ao modelo que documentos e consultas possuem papéis diferentes.

### Busca vetorial

A busca vetorial compara o embedding da pergunta com os embeddings dos chunks usando distância cosseno no pgvector.

O threshold `minimum_similarity` remove candidatos pouco relacionados. O `context_limit` define quantos chunks podem chegar ao modelo.

### Busca híbrida

A busca híbrida combina:

```text
Busca semântica com pgvector
             +
Full Text Search do PostgreSQL
```

A busca semântica é boa para significado e paráfrases. A busca textual é boa para nomes, códigos, valores e expressões exatas.

Os dois rankings são combinados com Reciprocal Rank Fusion (RRF):

```text
score = 1 / (rrf_k + rank)
```

Um chunk bem colocado nas duas buscas recebe contribuições dos dois rankings. O RRF utiliza posições em vez de somar scores vetoriais e textuais que possuem escalas diferentes.

### Geração aumentada por recuperação

Depois do retrieval, os chunks selecionados são enviados ao modelo junto com a pergunta. O agente é instruído a responder somente com base no contexto e informar quando a resposta não estiver presente.

## Comandos

### Importar e gerar embeddings

```bash
docker compose exec app php artisan embed:documents
```

Reconstrói o índice usando a configuração oficial.

### Fazer uma pergunta

```bash
docker compose exec app php artisan rag:ask "Qual é a política de férias?"
```

O comando recupera os chunks, monta o prompt e solicita a resposta ao modelo do Ollama.

### Avaliar somente retrieval

```bash
docker compose exec app php artisan rag:evaluate --retrieval-only
```

O comando reconstrói o índice oficial e calcula métricas determinísticas sem executar geração ou judge. É a opção mais rápida para estudar busca.

### Avaliar retrieval e geração

```bash
docker compose exec app php artisan rag:evaluate
```

Além das métricas de retrieval, o comando:

1. gera uma resposta para cada pergunta;
2. envia pergunta, resposta esperada, contexto e resposta gerada ao judge;
3. calcula correctness, faithfulness, relevance e completeness.

O judge é probabilístico e pode errar. Hit@K e MRR continuam sendo calculados diretamente em PHP.

### Executar experimentos

```bash
docker compose exec app php artisan rag:experiment
```

O experimento gera o produto cartesiano das configurações registradas:

```text
chunking × embedding × input mode × retrieval × threshold × context limit
```

Para cada configuração de indexação, ele recria `document_chunks`, executa o golden dataset e calcula as métricas. Em um bloco `finally`, o índice oficial é sempre restaurado.

Mostrar mais resultados:

```bash
docker compose exec app php artisan rag:experiment --top=25
```

Mostrar todos:

```bash
docker compose exec app php artisan rag:experiment --all
```

O experimento pode demorar porque algumas estratégias precisam gerar embeddings adicionais.

### Executar testes

```bash
docker compose exec app php artisan test
```

## Métricas

### Context Hit@K

Indica a proporção de perguntas em que os primeiros `K` chunks contêm toda a evidência esperada.

O contexto é avaliado de forma acumulada. Se um fato estiver no primeiro chunk e outro no segundo, o caso passa no rank 2.

```text
21 acertos em 22 perguntas = 95,5%
```

### MRR

Mean Reciprocal Rank mede quão cedo a evidência completa aparece:

```text
rank 1 → 1.00
rank 2 → 0.50
rank 3 → 0.33
```

Quanto mais próximo de `1`, melhor.

### Average context

Quantidade média de chunks que passa pelo threshold e é enviada como contexto. Uma configuração pode ter boa recuperação e ainda ser cara por enviar conteúdo demais.

O experimento primeiro exige o Hit@K mínimo definido por `RAG_EXPERIMENT_MINIMUM_HIT_RATE`. Entre as configurações aprovadas, prioriza a menor média de contexto e utiliza Hit@K e MRR como desempate.

### Métricas do judge

| Métrica | Pergunta avaliada |
|---|---|
| Correctness | A resposta concorda com a resposta de referência? |
| Faithfulness | Todas as afirmações estão sustentadas pelo contexto? |
| Relevance | A resposta responde diretamente à pergunta? |
| Completeness | Os fatos necessários foram cobertos? |

O LLM-as-a-judge complementa as métricas matemáticas; ele não substitui Hit@K ou MRR.

## Banco de dados

### Tabelas principais

`documents`:

```text
id
filename
content
timestamps
```

`document_chunks`:

```text
id
document_id
position
content
embedding vector(768)
timestamps
```

### Verificar documentos e chunks

Abrir o PostgreSQL:

```bash
docker compose exec postgres psql -U laravel -d laravel_rag
```

Consultas úteis:

```sql
SELECT id, filename FROM documents;

SELECT document_id, position, LEFT(content, 120)
FROM document_chunks
ORDER BY document_id, position;

SELECT COUNT(*) AS chunks
FROM document_chunks;
```

Sair do `psql`:

```text
\q
```

## Logs e observabilidade

O projeto registra com `Log::info`:

- perguntas recebidas;
- candidatos recuperados;
- similaridade e ranking;
- posições vetoriais e lexicais;
- score do RRF;
- prompt final;
- resposta gerada;
- resultados e resumos de avaliação.

Os logs ficam em:

```text
storage/logs/laravel.log
```

Acompanhar em tempo real:

```bash
docker compose exec app php artisan pail
```

## Estrutura principal

```text
app/
├── Ai/Agents/                         Agentes de resposta e avaliação
├── Console/Commands/                  Comandos Artisan
├── Contracts/Rag/                     Contratos das estratégias
├── Factories/Rag/                     Resolução das estratégias
├── Models/                            Document e DocumentChunk
├── Services/Rag/                      Fluxo de produção do RAG
│   ├── Chunking/                      Estratégias de chunking
│   └── Retrieval/                     Estratégias de retrieval
└── Services/RagEvaluation/            Métricas, judge e experimentos

config/
├── rag.php                            Configuração oficial
├── rag-strategies.php                 Catálogo de estratégias
└── rag-evaluation.php                 Dataset e matriz experimental

documents/                             Fontes de conhecimento .txt
```

## Estado atual e próximos estudos

O projeto já implementa um baseline mensurável com chunking estrutural e semântico, embeddings assimétricos, busca vetorial, busca híbrida e avaliação.

Próximos passos possíveis:

- separar datasets de desenvolvimento e teste;
- ampliar as perguntas e os documentos;
- calcular Hit@1, Hit@3, Hit@5 e Hit@10 separadamente;
- estudar os casos que falham;
- implementar reranking;
- comparar novos modelos de embedding;
- suportar PDF, Markdown e outros formatos;
- testar estratégias semânticas orientadas ao domínio.

Antes de declarar uma mudança como melhoria, ela deve ser medida contra o golden dataset e, idealmente, contra um conjunto de teste que não participou da escolha da configuração.
