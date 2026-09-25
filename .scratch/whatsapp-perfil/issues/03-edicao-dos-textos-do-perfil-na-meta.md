# 03: Edição dos textos do Perfil na Meta

**What to build:** Na aba Perfil do WhatsApp oficial, Owner e Admin editam recado, descrição, endereço, e-mail, até dois sites e categoria (lista com rótulos em português), com um "Salvar" próprio. A alteração é gravada direto na Meta. O Member vê tudo só para leitura. Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** 01 (Abas Conexão | Perfil e leitura do Perfil na Meta)

**Status:** ready-for-agent

- [ ] O contrato ganha "atualizar textos"; a implementação Meta faz POST no business profile com `messaging_product: whatsapp` e só os campos alterados
- [ ] Validação no servidor: recado de 1 a 139 caracteres, endereço até 256, descrição até 512, e-mail válido até 128, até 2 sites http(s) de até 256 caracteres, categoria dentro do enum da Meta
- [ ] Enum de categoria com rótulos em português
- [ ] O formulário mostra os limites enquanto a pessoa digita e os erros ao lado de cada campo
- [ ] Sucesso: toast e redirect para `?tab=perfil`. Recusa da Meta: toast de erro em português, sem alterar o `ChannelStatus`
- [ ] Autorização com `update` do Canal: Owner e Admin gravam, Member recebe 403, outra Empresa recebe 404
- [ ] O Member vê os campos desabilitados e sem botão de salvar
- [ ] Testes Pest cobrem o payload enviado (`Http::assertSent`), a validação, a recusa e as permissões
- [ ] Testes Vitest cobrem o modo só leitura para o Member
