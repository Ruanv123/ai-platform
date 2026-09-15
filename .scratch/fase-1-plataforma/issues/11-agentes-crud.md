# 11: Agentes (CRUD)

**What to build:** Owner e Admin criam, editam, ativam/desativam e excluem Agentes através de um formulário estruturado; Member visualiza. Campos: nome da persona, objetivo (Atendimento/Suporte, Vendas, Qualificação/SDR, Agendamento), tom de voz (formal, amigável, descontraído), uso de emojis, tamanho das respostas (curtas, médias), mensagem de boas-vindas, instruções, regras e gatilhos de transferência (listas ordenadas) e Coleções que o Agente consulta. Idioma fixo pt-BR. A lista mostra objetivo e status. Referência: spec, user stories 53–63, 66–68 e seção Agentes.

**Blocked by:** 06 (Coleções)

**Status:** ready-for-agent

- [ ] Model Agente pertencente à Empresa, com enums de objetivo, tom e tamanho (labels pt-BR via método de label, no padrão do `TeamRole`)
- [ ] Relação muitos-para-muitos Agente ↔ Coleção; só Coleções da mesma Empresa são aceitas
- [ ] `TeamPermission` ganha `agent:manage` (Owner e Admin)
- [ ] Item "Agentes" na sidebar; lista com nome, objetivo e status
- [ ] Formulário com editores de lista (adicionar, editar, reordenar, remover) para instruções, regras e gatilhos de transferência
- [ ] Ativar/desativar e excluir Agente com confirmação
- [ ] Feature tests: validação, Coleção de outra Empresa recusada, permissões por papel, isolamento (404)
