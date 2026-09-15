# 15: Catálogo de Integrações

**What to build:** Área Integrações com o catálogo estático de CRMs vindo do enum `CrmProvider`: RD Station CRM, HubSpot, Pipedrive, Kommo, Salesforce e Ploomes. Cada card abre um drawer com descrição e o que a integração fará, com o botão "Conectar" desabilitado e o selo "Em breve". Sem tabela nem persistência. Referência: spec, user stories 97–99 e seção Integrações.

**Blocked by:** 03 (Onboarding da Empresa)

**Status:** ready-for-agent

- [ ] Enum `CrmProvider` com os 6 casos, cada um com nome, descrição, logo e lista de capacidades futuras
- [ ] Item "Integrações" na sidebar; página com cards de todos os provedores
- [ ] Drawer com detalhes, botão "Conectar" desabilitado e selo "Em breve"
- [ ] Visível para todos os papéis
- [ ] Feature test verificando o catálogo nas props da página
