# 01: Remover os Nós de agenda e a Agenda por HTTP

**What to build:** Abrir espaço para a Agenda do Google. Os Nós de agenda do Fluxo deixam de existir: somem do catálogo do editor, do validador de DSL, do publicador, do motor e do rótulo do Traço, incluindo os aliases (`calendar_availability`, `check_availability`, `calendar_create_event`, `create_calendar_event`). Um Fluxo que ainda tenha um desses Nós é rejeitado como Nó desconhecido ao publicar. A tela da Agenda deixa de mostrar o formulário de URL base, autenticação e caminhos, e o tipo `agenda` deixa de ser aceito na criação genérica de Integração, porque a Agenda só vai nascer pelo OAuth do Google. Enquanto o ticket 02 não chega, a tela da Agenda mostra que a conexão ainda não está disponível. Ver `.scratch/agenda-google/spec.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Nós de agenda (e aliases) não aparecem no catálogo do editor de Fluxo
- [ ] Publicar um Fluxo com Nó de agenda falha como Nó desconhecido
- [ ] O motor e o Traço não têm mais caminho para Nós de agenda
- [ ] Criar Integração do tipo `agenda` pela rota genérica é recusado
- [ ] A tela da Agenda não mostra mais o formulário HTTP
- [ ] Testes da Agenda por URL base e da página com formulário HTTP são removidos, não adaptados
- [ ] Integrações HTTP, CRM e e-commerce continuam funcionando como antes
