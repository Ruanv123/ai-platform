# 04: Atualizar todas as Conversas em tempo real

**What to build:** Pessoas da Equipe com a caixa aberta veem novas Mensagens de Conversas ativas ou pausadas sem recarregar a página; após perder a conexão, recuperam o estado atual sem lacunas nem duplicações.

**Blocked by:** 02 — Mostrar todas as Conversas na caixa de entrada.

**Status:** partial (falta validação manual de duas telas e reconexão com Reverb)

- [x] Mensagens recebidas e enviadas atualizam lista, prévia, ordenação e Conversa selecionada pelo Reverb existente, independentemente de transferência para humano.
- [x] Mudanças de estado já disponíveis na caixa continuam chegando às pessoas conectadas; o cliente consulta novamente o estado canônico ao reconectar.
- [x] O canal privado só autoriza pessoas da Empresa correspondente e os avisos não expõem mídia privada ou credenciais do provedor.
- [x] Testes de funcionalidade cobrem publicação dos avisos e autorização.
- [ ] Uma verificação manual confirma duas telas abertas e reconexão.
