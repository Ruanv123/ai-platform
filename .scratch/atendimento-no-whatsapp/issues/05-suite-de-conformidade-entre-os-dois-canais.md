# 05: Suíte de conformidade entre os dois Tipos de canal

**What to build:** A defesa contra a divergência silenciosa entre as duas famílias de strategies, que é o risco central do ADR 0004 — como o Canal de testes é o que roda na máquina do desenvolvedor, é possível depurar num código que não é o que atende em produção. Não é estrutura nova: é o mesmo seam da rota, parametrizado por um dataset Pest com os dois Tipos de canal, rodando as **mesmas asserções** contra as duas rotas e as duas fixtures. Referência: spec, seção Testing Decisions; user story 42.

**Blocked by:** 03, 04

**Status:** done

- [x] Dataset Pest com os dois Tipos de canal, cada um apontando sua rota e sua pasta de fixtures
- [x] Mesmas asserções para cada tipo do núcleo: Contato criado, Mensagem registrada com o texto certo, tipo certo e direção certa
- [x] Mesma asserção de idempotência nos dois
- [x] Mesma asserção de evento não suportado nos dois
- [x] Mesma asserção de status de entrega atualizando a Mensagem de saída nos dois
- [x] A suíte falha de forma legível quando um lado tem strategy e o outro não

## Comments

- Implementado no commit `07f9764` (Add WhatsApp webhook conformance suite).
