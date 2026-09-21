# Motor único: atendimento sem Fluxo é um Fluxo trivial

O atendimento atual do WhatsApp vive inteiro em `RespondToWhatsAppMessage`: buffer de cinco segundos, leitura do Histórico, janela de 24 horas, cinco tentativas e erro registrado no Canal. Com a chegada dos Fluxos, essa lógica passa a existir em um só lugar: **o motor de execução é sempre o mesmo**, e um Canal "sem Fluxo" roda um Fluxo trivial de um Nó — entregue o turno ao Agente e não retome.

## Considered Options

- **Dois caminhos, um para Canal com Fluxo e outro para Canal sem**: rejeitada porque obriga a implementar buffer, janela de 24 horas, retry e tratamento de falha duas vezes, e as duas implementações divergem com o tempo. É o mesmo risco que o ADR 0004 reconheceu nas strategies por provedor — só que lá havia uma suíte de conformidade como seguro, e aqui dá para simplesmente não criar a divergência.

## Consequences

- Todo Canal passa a ter Execução, inclusive os que nunca viram um Fluxo desenhado. O custo é uma linha por atendimento; o ganho é que Traço, métricas e depuração valem para todos desde o primeiro dia.
- Canais existentes precisam de migração que cria o Fluxo trivial a partir do Agente já vinculado.
- O comportamento hoje implícito no Job — esperar cinco segundos, agrupar mensagens não respondidas, respeitar a janela de 24 horas — passa a ser comportamento do motor, exercitado por todos os Fluxos.
