# Strategies de evento por provedor, com suíte de conformidade

Cada Tipo de canal do WhatsApp tem a sua própria família de strategies de evento (texto, imagem, áudio, vídeo, documento, status), unidas apenas por uma interface por tipo de evento. Não existe um evento canônico intermediário: nenhum adapter traduz Meta e Evolution Go para uma forma comum antes das strategies.

## Considered Options

- **Evento canônico compartilhado** (adapter traduz, strategies só conhecem o canônico): rejeitada. Ela economiza uma família de strategies, mas empurra toda divergência entre provedores para a tradução, inclusive as que não têm correspondência limpa — o Evolution Go emite `READ_RECEIPT` e a Meta emite `statuses` com semântica diferente, e um canônico teria que escolher um perdedor.

## Consequences

- O risco real não é a duplicação, é a divergência silenciosa: como o WhatsApp de testes é o que roda na máquina do desenvolvedor, é possível depurar num código que não é o que atende em produção.
- A defesa é uma **suíte de conformidade** parametrizada: as mesmas asserções de saída rodam contra as duas famílias, a partir de fixtures de payload real de cada provedor. Uma strategy nova não está pronta enquanto as duas famílias não passarem nela.
- Eventos fora do núcleo caem numa strategy `Unsupported` que **registra** a Mensagem com o tipo bruto em vez de descartar, para que a superfície real que chega de cada provedor seja observável antes de virar código.
- A suíte cobre o que os dois provedores conseguem fazer: texto, áudio, imagem, documento, vídeo, Reação, status de entrega e envio de imagem, documento, áudio e vídeo. Ficam **só na família oficial**, por não existirem no Evolution Go: envio de Template, clique em botão de Template e os eventos de conta, número e Template da Meta.
