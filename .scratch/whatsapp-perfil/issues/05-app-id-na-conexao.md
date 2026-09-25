# 05: App ID na Conexão

**What to build:** No formulário da aba Conexão do WhatsApp oficial, o Owner e o Admin informam, de forma opcional, o App ID do app da Meta. Esse dado é necessário para trocar a foto do Perfil. Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Coluna `app_id` opcional na Conta do WhatsApp
- [ ] O campo aparece no formulário de criar e de editar a conexão, validado e salvo
- [ ] Editar o App ID preserva o access token e o app secret
- [ ] O App ID é serializado para o frontend
- [ ] Testes Pest cobrem salvar, editar e preservar os segredos
