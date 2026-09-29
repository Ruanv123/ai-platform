# 09: Etapa comercial pausa a IA

**What to build:** Na tela do CRM, a Empresa marca Etapas do funil como Etapas comerciais. A cada mudança de Negócio vinculado — vinda de webhook ou de Operação da plataforma — o Motor reavalia o Contato: ele deve estar pausado pelo CRM se, e só se, algum Negócio vinculado aberto dele estiver numa Etapa comercial. Passar de nenhum para algum pausa a IA em todas as Conversas do Contato, em todos os Canais, sem prazo, marca o Contato como aguardando atendimento e avisa a Equipe, como numa Transferência para humano; é a Pausa da IA existente (ADR 0010) com origem CRM. Passar de algum para nenhum — saiu da Etapa comercial, ganho, perdido ou apagado — encerra a pausa de origem CRM e o Agente volta. Devolução manual com o Negócio ainda numa Etapa comercial encerra a pausa até a próxima passagem de nenhum para algum. Pausa manual já existente numa Conversa não é substituída nem encerrada. Mudar as Etapas comerciais reavalia os Contatos com Negócios vinculados. Um Agente que move o Negócio para uma Etapa liberada que também é comercial pausa a si mesmo, e a configuração do Agente sugere esse caminho. A Equipe recebe avisos in-app em tempo real, pelo broadcasting já usado no Inbox, de Negócio vinculado ganho, perdido (com Motivo de perda) e IA pausada pelo CRM; mudança de Etapa comum não avisa. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 08

**Status:** ready-for-agent

- [ ] A tela do CRM marca e desmarca Etapas comerciais; só Owner e Admin alteram
- [ ] Negócio vinculado entrando numa Etapa comercial pausa todas as Conversas do Contato, sem prazo, e marca aguardando atendimento
- [ ] O Agente não responde nas Conversas pausadas pelo CRM
- [ ] Saída da Etapa comercial, ganho, perda ou Negócio apagado encerra a pausa de origem CRM quando nenhum outro Negócio vinculado está numa Etapa comercial
- [ ] Com dois Negócios vinculados, a pausa continua enquanto qualquer um estiver numa Etapa comercial
- [ ] Devolução manual encerra a pausa e ela só volta na próxima entrada numa Etapa comercial
- [ ] Pausa manual preexistente não é alterada pela regra do CRM
- [ ] Alterar as Etapas comerciais reavalia os Contatos afetados
- [ ] Agente movendo o Negócio para Etapa liberada comercial resulta em pausa; a configuração do Agente sugere uma Etapa comercial como passagem
- [ ] Avisos em tempo real de ganho, perda com motivo e pausa pelo CRM; mudança de Etapa comum não avisa
- [ ] Testes pelo endpoint de webhook e pelo adapter falso, verificando estado da pausa, resposta do Agente e broadcasts
