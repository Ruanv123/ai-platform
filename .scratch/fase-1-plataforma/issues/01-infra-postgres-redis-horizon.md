# 01: Infra: Postgres + pgvector, Redis, Mailpit e Horizon

**What to build:** O ambiente de desenvolvimento sobe com um comando: Docker rodando apenas os serviços (Postgres com pgvector, Redis e Mailpit) e a aplicação no host. A aplicação passa a usar Postgres (com a extensão `vector` habilitada), filas no Redis monitoradas pelo Laravel Horizon (filas `ingestion` e `default`) e e-mail pelo Mailpit. Os testes rodam num database de teste dedicado no Postgres com pgvector, não mais em SQLite em memória. Referência: spec `.scratch/fase-1-plataforma/spec.md`, seções Infraestrutura e Testing Decisions.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] Arquivo de compose com Postgres (imagem oficial pgvector), Redis e Mailpit sobe com um comando
- [x] `.env.example` configurado para Postgres, Redis (fila, e cache/sessão se fizer sentido) e Mailpit
- [x] Migration habilita a extensão `vector`
- [x] `laravel/horizon` instalado, com supervisores para as filas `ingestion` e `default`; painel acessível em ambiente local
- [x] Script de dev (`composer run dev`) sobe o Horizon no lugar do `queue:listen`
- [x] Configuração de testes aponta para um database de teste no Postgres com pgvector (criado pelo compose)
- [x] Toda a suíte de testes existente passa em Postgres
- [x] Um teste prova que a extensão `vector` está disponível no banco de testes
