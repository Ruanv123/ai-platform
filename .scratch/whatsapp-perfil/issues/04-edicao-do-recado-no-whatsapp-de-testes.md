# 04: Edição do recado no WhatsApp de testes

**What to build:** Na aba Perfil do WhatsApp de testes, Owner e Admin editam o recado do número conectado usando o mesmo formulário de textos, reduzido pelas capacidades do Evolution Go. Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** 02 (Leitura do Perfil no WhatsApp de testes), 03 (Edição dos textos do Perfil na Meta)

**Status:** ready-for-agent

- [ ] Implementação Evolution Go de "atualizar textos" via `POST /user/profileStatus`
- [ ] O formulário mostra só o recado, e o nome continua só para leitura
- [ ] Rota de gravação na tela do WhatsApp de testes com as mesmas permissões e validação do recado
- [ ] Sucesso e falha seguem o mesmo padrão de toast, sem alterar o `ChannelStatus`
- [ ] Testes Pest cobrem o payload enviado ao Evolution Go, a falha e as permissões
