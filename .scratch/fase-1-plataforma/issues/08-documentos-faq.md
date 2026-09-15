# 08: Documentos de FAQ

**What to build:** Owner e Admin criam documentos de FAQ numa Coleção, com um editor de lista de pares pergunta/resposta (adicionar, editar, reordenar, remover). Cada par vira exatamente um chunk na ingestão. Editar o FAQ reprocessa o documento. O conteúdo conta na cota. Referência: spec, user stories 36–38.

**Blocked by:** 07 (Documentos de texto livre + ingestão + busca)

**Status:** done

- [x] Tipo de documento `faq` com pares pergunta/resposta guardados estruturados no documento
- [x] Editor de pares com adicionar, editar, reordenar e remover; validação de pergunta e resposta obrigatórias e ao menos um par
- [x] Ingestão gera um chunk por par (pergunta + resposta juntos)
- [x] Edição volta o status para `pending` e reprocessa; tamanho do conteúdo contabilizado na cota
- [x] Feature tests: criação, edição reprocessando, um chunk por par, cota e permissões
