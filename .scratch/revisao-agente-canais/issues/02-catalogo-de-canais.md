# 02: Catálogo de Canais

**What to build:** A tela de Canais deixa de ser a configuração do WhatsApp e vira a listagem dos meios de atendimento da Empresa. Cada Tipo de canal aparece numa linha larga: o WhatsApp mostra status da conexão, número conectado e Agente que responde, e leva para a configuração; Chat no site e Instagram Direct aparecem como "Em breve", desabilitados. Quando o Canal está em erro, a linha mostra o motivo no lugar da descrição — é onde a Empresa descobre que o atendimento parou sem abrir nada. A configuração do WhatsApp ganha rota própria e perde o card de Chat no site que estava enfiado nela. A densidade é diferente da tela de Integrações de propósito: lista é o que você opera, grid é o que você ainda vai escolher. Referência: spec, user stories 31–37 e 50.

**Blocked by:** 01 (Canal, Conta e Número do WhatsApp)

**Status:** ready-for-agent

- [ ] Catálogo gerado a partir do enum de Tipo de canal, com label, descrição, ícone, disponibilidade e destino
- [ ] Controller próprio serve o catálogo na rota raiz de Canais; a configuração do WhatsApp passa a ter rota e nome próprios
- [ ] Binding da configuração feito pelo Canal, recusando Canal de outro Tipo de canal
- [ ] Linha do WhatsApp mostra status, número exibido e Agente vinculado quando configurado; ação "Conectar" quando não
- [ ] Canal em erro mostra o último erro truncado na linha
- [ ] Canal conectado sem Agente vinculado é destacado
- [ ] Chat no site e Instagram Direct não navegam e não abrem painel
- [ ] Card de Chat no site removido da configuração do WhatsApp
- [ ] Member vê o catálogo sem ações de gerenciamento
- [ ] Feature tests: catálogo com os três tipos, linha conectada, linha em erro, linha sem Agente, isolamento entre Empresas, permissões por Papel
