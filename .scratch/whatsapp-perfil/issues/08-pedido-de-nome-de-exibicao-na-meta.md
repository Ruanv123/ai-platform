# 08: Pedido de nome de exibição na Meta

**What to build:** Na aba Perfil do WhatsApp oficial, Owner e Admin pedem um novo nome de exibição. Antes de enviar, a aba avisa que a Meta revisa o nome e aceita no máximo 10 pedidos a cada 30 dias. Depois do envio, a aba mostra o status da revisão lido ao vivo e avisa que, após a aprovação, o número precisa ser re-registrado no WhatsApp Manager. Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** 01 (Abas Conexão | Perfil e leitura do Perfil na Meta)

**Status:** ready-for-agent

- [ ] O contrato ganha "pedir nome de exibição". A implementação Meta faz POST no número com `new_display_name`, e o Evolution Go declara que não suporta
- [ ] O bloco de nome, no próprio arquivo, mostra o nome atual, o nome pedido e o status ("Em revisão", "Aprovado", "Recusado", "Expirado") a partir de `name_status`/`new_name_status`
- [ ] Aviso de revisão e de limite antes de enviar, e aviso de re-registro manual depois da aprovação
- [ ] Sucesso e falha seguem o padrão de toast, sem alterar o `ChannelStatus`
- [ ] Permissões: Owner e Admin pedem, Member recebe 403
- [ ] No WhatsApp de testes, o nome aparece só para leitura e não existe rota de pedido
- [ ] Testes Pest cobrem o payload enviado, a leitura de "Em revisão", a falha e as permissões
- [ ] Testes Vitest cobrem o status mostrado e o bloqueio do pedido quando o provedor não suporta
