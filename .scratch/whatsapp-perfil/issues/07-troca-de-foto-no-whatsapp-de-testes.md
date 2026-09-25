# 07: Troca de foto no WhatsApp de testes

**What to build:** Na aba Perfil do WhatsApp de testes, Owner e Admin trocam a foto do número conectado com o mesmo diálogo de recorte. O Evolution Go só aceita foto por URL. Por isso, a plataforma guarda o JPEG por pouco tempo e o entrega por uma URL assinada e temporária. Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** 02 (Leitura do Perfil no WhatsApp de testes), 06 (Troca de foto na Meta)

**Status:** ready-for-agent

- [ ] O arquivo recortado é guardado num disco privado
- [ ] Uma rota assinada, que expira em cerca de 5 minutos e fica fora da autenticação de sessão, serve o arquivo
- [ ] A implementação Evolution Go chama `POST /user/profilePicture` com `image` apontando para a URL assinada
- [ ] O arquivo é apagado depois da resposta do Evolution Go, com sucesso ou falha
- [ ] A capacidade de foto é declarada pelo Evolution Go, e o aviso de App ID não aparece nesta tela
- [ ] Mesmas validações, toasts e permissões do ticket 06
- [ ] Testes Pest cobrem a URL enviada, o download pela URL assinada, a recusa depois de expirar ou sem assinatura, o arquivo apagado e as permissões
