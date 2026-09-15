# 16: Dashboard

**What to build:** O Dashboard mostra um checklist de ativação calculado do estado real (dados da Empresa completos, ao menos um Agente, ao menos um documento `ready`, WhatsApp `connected`, Agente vinculado ao WhatsApp), com cada item pendente levando à tela correspondente; um resumo com Agentes ativos/total, documentos `ready` e `failed`, uso da cota e status do WhatsApp; e cards de métricas de atendimento (conversas, atendimentos resolvidos, transferências, oportunidades criadas) marcados como "em breve", sem dados. Referência: spec, user stories 100–104 e seção Dashboard e navegação.

**Blocked by:** 07 (Documentos de texto livre + ingestão + busca), 11 (Agentes CRUD), 13 (Canais: configuração do WhatsApp)

**Status:** ready-for-agent

- [ ] Checklist de ativação com os 5 itens refletindo o estado da Empresa, cada item com link para a tela correspondente
- [ ] Resumo com Agentes ativos/total, documentos `ready` e `failed`, uso da cota e status do WhatsApp
- [ ] Cards de métricas de atendimento "em breve", sem dados
- [ ] Sidebar final na ordem: Dashboard, Agentes, Base de conhecimento, Canais, Integrações, Equipe, Configurações
- [ ] Dados sempre restritos à Empresa do usuário
- [ ] Feature tests: checklist e resumo refletindo diferentes estados (conta vazia, parcialmente configurada, totalmente ativada) e isolamento entre Empresas
