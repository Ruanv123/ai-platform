# 07: Documentos de texto livre + ingestão + busca

**What to build:** Primeiro fluxo ponta a ponta da Base de conhecimento. Owner e Admin criam e editam documentos de texto livre numa Coleção; o documento é processado em segundo plano (status `pending → processing → ready | failed`, com mensagem de erro em pt-BR) e a lista atualiza o status sem recarregar. O pipeline de ingestão (fila `ingestion`) faz chunking orientado a Markdown (~800 tokens, overlap ~100), gera embeddings com `text-embedding-3-small` (1536 dim) via `laravel/ai` e grava os chunks em pgvector com índice HNSW por cosseno, substituindo os antigos atomicamente. Documentos podem ser excluídos, movidos de Coleção e reprocessados. A cota de 500 MB por Empresa é contabilizada e exibida. Excluir Coleção apaga documentos e chunks em cascata. Este ticket entrega também o **módulo de busca na base de conhecimento** (seam novo). Referência: spec, user stories 35, 38, 42–48, 50–51 e seções Base de conhecimento e Busca na base de conhecimento.

**Blocked by:** 01 (Infra), 06 (Coleções)

**Status:** done

- [x] Model Documento (tipo, status, mensagem de erro, tamanho em bytes, Coleção, Empresa) e Chunk (conteúdo, embedding `vector(1536)`, `team_id` e `collection_id` desnormalizados) com índice HNSW
- [x] Owner e Admin criam e editam documento de texto livre (título + conteúdo); edição volta o status para `pending` e reprocessa
- [x] Job de ingestão idempotente na fila `ingestion`; falha após tentativas marca `failed` com mensagem e preserva chunks anteriores
- [x] Lista de documentos mostra status, tamanho e data, com polling do Inertia apenas enquanto houver `pending`/`processing`
- [x] Excluir documento, mover para outra Coleção e reprocessar documento com erro
- [x] Uso de armazenamento da Empresa exibido contra a cota de 500 MB; criação/edição que ultrapassa a cota é bloqueada com mensagem clara; excluir libera espaço
- [x] Exclusão de Coleção apaga documentos e chunks em cascata
- [x] Módulo de busca: recebe Empresa, Coleções permitidas e pergunta; devolve até 5 trechos com similaridade ≥ 0.35 ordenados, com conteúdo, documento (id, título), Coleção (id, nome) e similaridade; ignora documentos não `ready`; conjunto vazio de Coleções devolve vazio sem consultar o banco; top-K e threshold em configuração
- [x] Feature tests via HTTP com `Embeddings::fake()` e fila `sync` (status `ready`, chunks gerados, cota, permissões, isolamento)
- [x] Testes diretos do módulo de busca contra pgvector com vetores controlados: limite 5, corte 0.35, ordenação, só `ready`, isolamento por Empresa, restrição a Coleções, conjunto vazio
