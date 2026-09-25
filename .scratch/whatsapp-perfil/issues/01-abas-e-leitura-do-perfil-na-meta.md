# 01: Abas Conexão | Perfil e leitura do Perfil na Meta

**What to build:** Na tela do WhatsApp oficial, o Owner, o Admin e o Member veem abas de linha "Conexão" | "Perfil". A aba ativa fica na URL (`?tab=perfil`). A aba Perfil mostra, só para leitura e sempre lida ao vivo da Meta, o Perfil do WhatsApp do número: foto, nome de exibição, recado, descrição, endereço, e-mail, sites e categoria. Este ticket cria o contrato de Perfil do WhatsApp por provedor (capacidades + leitura), com a primeira implementação (Meta), e introduz a suíte de testes de frontend com Vitest. Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Prefactor: a tradução de erros da Meta sai do controller de conexão para um lugar reaproveitável, sem mudar o comportamento do teste de conexão
- [ ] Contrato de Perfil do WhatsApp com capacidades (campos legíveis/editáveis) e leitura; implementação Meta lê o business profile e o estado do nome (`verified_name`, `name_status`, `new_display_name`, `new_name_status`)
- [ ] A tela aceita `?tab=conexao|perfil`; o conteúdo atual vira a aba Conexão, sem regressão
- [ ] O Perfil é uma deferred prop avaliada só com a aba Perfil ativa, com esqueleto pulsante enquanto carrega
- [ ] Canal não `Connected`: estado vazio com link para a aba Conexão, sem chamar a Meta
- [ ] Falha da Meta: toast de erro em português e estado vazio com "Tentar de novo", que recarrega só a prop do Perfil
- [ ] Nenhuma falha de leitura altera `ChannelStatus` ou `last_error`
- [ ] Outra Empresa recebe 404
- [ ] Vitest + React Testing Library + jsdom configurados como dependências de desenvolvimento, com script `npm test`
- [ ] Testes Pest (com `Http::fake`) cobrem Perfil preenchido, não conectado e falha sem mudar o status
- [ ] Testes Vitest cobrem: campos renderizados conforme as capacidades, estado vazio e "Tentar de novo"
