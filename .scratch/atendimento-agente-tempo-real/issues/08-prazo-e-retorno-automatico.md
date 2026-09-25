# 08: Configurar prazo e retornar automaticamente ao Agente

**What to build:** Owner/Admin define quanto tempo dura uma Pausa da IA. A Conversa continua com a pessoa responsável enquanto há atividade e retorna automaticamente ao Agente após o prazo, usando a mesma regra da devolução manual.

**Blocked by:** 07 — Devolver manualmente a Conversa ao Agente.

**Status:** done

- [x] A configuração da Empresa aceita horas e minutos entre 1 e 1.440 minutos; o padrão é 30 minutos, com autorização apenas para Owner/Admin.
- [x] Cada pausa guarda o prazo que estava configurado ao começar. Uma mudança posterior vale apenas para novas pausas, inclusive quando a pausa antiga recebe outra Mensagem.
- [x] Cada Mensagem recebida ou enviada com sucesso durante a pausa renova o vencimento; envio falho não o renova.
- [x] No vencimento, o processamento usa a mesma regra da devolução manual e não duplica resposta se rodar mais de uma vez ou competir com nova Mensagem.
- [x] A atribuição permanece até o retorno bem-sucedido; falha mantém pausa e responsável, sem repetição automática da mesma resposta.
- [x] Testes de funcionalidade congelam o tempo e cobrem limites, papel, renovação, mudança da configuração, concorrência, última Mensagem de cada direção e falha.
