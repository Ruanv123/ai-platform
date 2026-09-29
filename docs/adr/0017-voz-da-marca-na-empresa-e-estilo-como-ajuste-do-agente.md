# Voz da marca na Empresa, Estilo como ajuste do Agente

O jeito de falar da marca é guardado uma vez na Empresa, como Voz da marca: um texto descritivo e frases de exemplo tiradas da própria marca. Ela entra nas instruções de todos os seus Agentes. O Estilo continua no Agente (tom, emojis, tamanho, diretrizes de forma) e vale por cima da Voz da marca quando as duas divergem. A voz de uma marca é da Empresa por definição, e uma Empresa com vários Agentes não deveria repetir nem deixar divergir essa descrição em cada um.

## Considered Options

- **Mais um campo no Estilo do Agente, sugerido pela Leitura do site**: rejeitada. O enum de três tons é raso demais para carregar a voz de uma marca, cada Agente guardaria uma cópia, e uma nova Leitura do site teria que decidir em quais Agentes escrever.
- **Voz da marca estruturada (tom, palavras usadas, palavras evitadas)**: rejeitada. "Palavras evitadas" se confunde com Limite, e frases reais ancoram o modelo melhor que listas de adjetivos.

## Consequences

- A Voz da marca trata de como se diz, e o Limite trata do que não se diz nem se faz. A Leitura do site nunca propõe Limites.
- Mudar a Voz da marca muda como todos os Agentes falam sem passar pelo Rascunho de cada um. É por isso que ela só muda por confirmação explícita de alguém da Equipe.
