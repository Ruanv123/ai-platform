# Pausa do Agente por Contato e Canal, independente de Fluxos

Status: accepted

A Pausa da IA e a atribuição humana pertencem ao par Contato + Canal, para que uma pessoa possa assumir um atendimento sem interromper os demais Canais do mesmo Contato. Esse estado terá registro próprio, independente da Execução de Fluxo: o Agente atende sem Fluxo, e a remoção dos Fluxos será uma tarefa separada. A caixa de entrada reunirá ambos os tipos de atendimento enquanto coexistirem. Não usaremos `contacts.human_waiting` como fonte da pausa, pois ele abrange todos os Canais; tampouco `flow_executions`, pois uma conversa conduzida diretamente pelo Agente não tem Execução. O ADR 0003 continua válido para o Histórico: ele é uma leitura das Mensagens, sem entidade de Conversa com ciclo de vida. Sua escolha anterior de tratar o Contato como unidade do atendimento não se aplica ao novo estado de pausa, que exige o par Contato + Canal.
