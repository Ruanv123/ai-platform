# 12: Playground

**What to build:** Qualquer membro conversa com um Agente num chat de teste. O Agente roda com `gpt-5.6-luna` (OpenAI via `laravel/ai`, modelo em configuração), com system prompt montado a partir de um template controlado pela plataforma (identidade e objetivo, dados da Empresa, estilo, instruções e regras do cliente, guardrails, orientação de uso da base, formatação para mensageria) e com uma ferramenta de busca na base de conhecimento (usando o módulo de busca, restrita às Coleções do Agente) que o modelo chama quando precisa. A resposta vem em streaming, com painel das fontes consultadas por resposta. A conversa é descartável (estado no cliente, botão reiniciar) e usa sempre a configuração salva do Agente. Referência: spec, user stories 69–80 e seções Agentes e Playground.

**Blocked by:** 07 (Documentos de texto livre + ingestão + busca), 11 (Agentes CRUD)

**Status:** done

- [x] API do `laravel/ai` 0.11 para agentes com ferramentas e streaming com eventos de ferramenta verificada na documentação instalada
- [x] Template de instruções versionado no código, preenchido com dados da Empresa e do Agente; gatilhos de transferência não entram no prompt
- [x] Ferramenta de busca limitada às Coleções do Agente; Agente sem Coleções conversa só com dados da Empresa
- [x] Endpoint de streaming por Agente, disponível a todos os papéis, recebendo histórico + nova mensagem; nada é persistido
- [x] UI de chat com mensagem de boas-vindas, streaming, botão reiniciar e painel de fontes (documento, Coleção, trecho, similaridade) ou indicação de que a base não foi consultada
- [x] Aviso no Playground quando o formulário do Agente tem alterações não salvas
- [x] Rate limit de 30 mensagens/hora por usuário com mensagem em pt-BR
- [x] Erro ou timeout do provedor vira mensagem amigável no chat
- [x] Feature tests com o fake de agente do `laravel/ai`: streaming responde, Member pode usar, isolamento (404), rate limit a partir da 31ª mensagem, erro do provedor tratado
