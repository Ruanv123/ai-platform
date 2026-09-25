# 02: Leitura do Perfil no WhatsApp de testes

**What to build:** A tela do WhatsApp de testes ganha as mesmas abas "Conexão" | "Perfil", com o mesmo componente da aba Perfil. A aba mostra o Perfil do WhatsApp lido ao vivo do Evolution Go, só com o que ele suporta: nome, recado e foto. O WhatsApp de testes continua sendo um Tipo de canal próprio (ADR 0002). Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** 01 (Abas Conexão | Perfil e leitura do Perfil na Meta)

**Status:** ready-for-agent

- [ ] Implementação Evolution Go do contrato: nome via `GET /instance/status`, recado via `POST /user/info`, foto via `POST /user/avatar`, com o token da instância no header `apikey`
- [ ] As capacidades declaram só foto, nome e recado, e a aba não mostra campos de negócio
- [ ] `?tab=perfil` funciona na tela do WhatsApp de testes, com deferred prop e esqueleto
- [ ] Canal não conectado (QR ainda não lido) mostra o estado vazio com link para a Conexão
- [ ] Falha do Evolution Go mostra um toast de erro e "Tentar de novo", sem alterar o `ChannelStatus`
- [ ] O gate de visibilidade do WhatsApp de testes continua valendo
- [ ] Testes Pest com `Http::fake` do Evolution Go cobrem Perfil preenchido, não conectado e falha
