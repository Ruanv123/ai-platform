# 01: Canal, Conta e Número do WhatsApp

**What to build:** A Empresa continua configurando, testando e removendo o WhatsApp exatamente como hoje, na mesma tela e na mesma rota, mas por baixo o atendimento passa a ser modelado como Canal. O Canal guarda o Tipo de canal, o Agente que responde, o status e o último erro; a Conta do WhatsApp guarda as credenciais de app da Meta; o Número do WhatsApp guarda identidade e qualidade. O Canal se prende ao Número, porque é ele que recebe o webhook e atende. Nada muda para o usuário nesta entrega — o que muda é que existe um lugar único para "qual Agente responde aqui" e "este atendimento está funcionando". Referência: spec, user stories 38–54, e ADR 0001.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Tabela `channels` com Empresa, Tipo de canal, Agente nulável, status, último erro e índice único por Empresa e Tipo de canal
- [ ] Tabelas `whatsapp_accounts` (método de conexão, WABA ID, access token e app secret criptografados, verify token único, verificação do webhook, último teste) e `whatsapp_phone_numbers` (phone number ID único, número exibido, nome verificado, qualidade)
- [ ] `ChannelType` e `ChannelStatus` como enums com labels em pt-BR; `WhatsAppConnectionStatus` removido; `WhatsAppConnectionMethod` passa a viver na Conta
- [ ] `whats_app_numbers` dropada sem backfill; model, factory e policy antigos removidos
- [ ] `ChannelPolicy` no lugar da policy antiga, mantendo `channel:manage` para Owner e Admin
- [ ] Criar a configuração grava Canal, Conta e Número numa transação, com o Canal em "Não conectado"
- [ ] Editar sem informar segredos preserva os salvos; segredos nunca voltam nas props
- [ ] Teste de conexão bem-sucedido move o Canal para "Conectado" e grava número exibido, nome verificado e qualidade; falha move para "Erro" com a mensagem da Meta traduzida
- [ ] Excluir apaga Canal, Conta e Número em cascata
- [ ] Segunda configuração de WhatsApp na mesma Empresa continua recusada com mensagem em pt-BR
- [ ] Webhook: handshake casa pelo verify token da Conta e marca a verificação; evento é resolvido pelo phone number ID até o Canal e o Agente, com assinatura validada pelo app secret da Conta
- [ ] Factories de Canal, Conta e Número, com estados para Canal conectado, em erro e sem Agente
- [ ] Feature tests de canal e de webhook reescritos sobre o novo modelo, cobrindo permissões por Papel e isolamento entre Empresas
