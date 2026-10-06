# Execução do fluxo como entidade estreita, sem reabrir Conversa

Status: superseded by ADR-0018

Um Fluxo em andamento precisa guardar em que Nó está, quais Variáveis coletou, quando acorda e quantas vezes tentou. Isso é estado persistido com ciclo de vida, e não dá para derivar das Mensagens. Criamos a **Execução**: uma entidade própria, deliberadamente estreita, que guarda posição e Variáveis e **não guarda Mensagens**.

O ADR 0003 continua valendo: o Histórico segue sendo uma consulta às Mensagens, não uma coluna nem uma tabela.

## Considered Options

- **Reabrir o ADR 0003 e introduzir Conversa como entidade**, pendurando o Histórico nela: rejeitada porque o motivo do 0003 continua verdadeiro — o corte de 24 horas é regra de leitura. A Execução não é a conversa: um Contato pode ter tido dez Execuções e uma conversa só, ou uma conversa longa e nenhuma Execução.
- **Estado em cache com TTL, sem entidade**: rejeitada porque investigar por que um Fluxo travou três dias atrás é requisito, não conveniência. Estado que some não se depura, e o sintoma mais citado dos concorrentes é exatamente automação que morre sem avisar.

## Consequences

- Execução e Histórico respondem perguntas diferentes e não se substituem. "O que o Contato falou" é Mensagem; "onde o Fluxo está" é Execução.
- Uma Execução pertence a uma **Versão do fluxo**, não ao Fluxo — ver ADR 0008. Isso é o que permite ao Nó onde ela parou continuar existindo depois de uma publicação.
- Execuções paradas são visíveis: existe uma varredura de reconciliação que as encontra, e "Presas" é métrica de primeira classe no dashboard.
- Toda Execução tem tempo de vida máximo. Ao estourar, encerra com motivo explícito registrado no Traço — nunca parada silenciosa.
