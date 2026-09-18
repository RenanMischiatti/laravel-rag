# Project purpose

This is an educational Laravel project for learning Retrieval-Augmented Generation step by step. Prefer readable implementations that make each RAG stage observable. Avoid unnecessary abstractions, but keep retrieval, generation, and evaluation responsibilities separated.

# Current architecture

- `documents/*.txt` is the knowledge source.
- `embed:documents` imports files, creates paragraph-based chunks, and stores 768-dimensional embeddings.
- PostgreSQL stores documents and chunks; pgvector performs cosine vector search.
- Ollama provides the embedding and text-generation models through Laravel AI SDK.
- `rag:ask` retrieves relevant chunks and asks the model to answer only from that context.
- `rag:evaluate` runs a small golden dataset from `config/rag-evaluation.php`.

# Evaluation

Evaluation code belongs in `app/Services/RagEvaluation`; keep it separate from the production RAG flow.

- Retrieval quality is deterministic: Hit@K and MRR are calculated in PHP.
- Generation quality uses an LLM-as-a-judge with structured scores for correctness, faithfulness, relevance, and completeness.
- The judge receives one case at a time: question, reference answer, retrieved context, and generated answer.
- Do not ask an LLM to calculate deterministic metrics.
- Keep `--retrieval-only` working so retrieval can be studied without generation or judging.

# Project conventions

- Write code and inline comments in English.
- Keep comments short and focused on concepts.
- Log retrieval candidates, final prompts, answers, evaluation results, and summaries with `Log::info`.
- Add evaluation cases for meaningful behavior changes.
- Prefer measuring a change against the golden dataset before declaring it an improvement.
- Do not add OpenAI calls, chat interfaces, hybrid search, reranking, or agentic RAG unless the current task explicitly requests them.

# Useful commands

```bash
docker compose up -d
docker compose exec app php artisan embed:documents
docker compose exec app php artisan rag:ask "Question"
docker compose exec app php artisan rag:evaluate --retrieval-only
docker compose exec app php artisan rag:evaluate
docker compose exec app php artisan test
```

# Likely next study steps

Expand the golden dataset, calculate Hit@1/3/5/10, inspect failed candidates, then compare chunking strategies. Hybrid search and reranking should come only after the baseline can be measured.
