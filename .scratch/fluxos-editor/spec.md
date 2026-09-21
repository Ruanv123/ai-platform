# Spec: Editor de fluxos

Status: ready-for-agent

Depends on: `.scratch/fluxos-motor/spec.md` (o DSL e os tipos de Nó precisam existir)

## Problem Statement

O motor executa DSL, e ninguém vai escrever DSL à mão. Sem editor visual, o produto é uma API sem produto: a Empresa que contrata a plataforma não sabe o que é um grafo dirigido, sabe que quer marcar consulta pelo WhatsApp.

Há um segundo problema, que é o motivo de o Botpress ser o campeão de reclamação de curva de aprendizado: editor de fluxo é intimidante. A pessoa abre o canvas em branco e não sabe o que fazer.

## Solution

Um editor de canvas no estilo n8n, com a criação por IA como porta de entrada.

- O canvas ocupa toda a tela menos a sidebar da aplicação. Barras, seletor de nós, propriedades e controles de zoom **flutuam** sobre ele.
- O chat de IA e o painel de teste são **sheets rentes**, sem sombra: eles fazem parte do layout e empurram o canvas.
- Nó novo pelo `+`, tanto solto quanto depois de cada nó existente. Fluxo vazio mostra um nó-fantasma "escolha o gatilho".
- O grafo corre na horizontal e fica centrado na vertical.
- A Empresa descreve o atendimento em texto e a IA monta o grafo; depois continua a conversa para ajustar.
- O Playground roda a Versão rascunho com o Traço acendendo nó a nó ao lado.

## User Stories

1. As a Empresa, I want to montar o atendimento arrastando e conectando passos, so that eu não precise de um desenvolvedor
2. As a Empresa, I want to adicionar um nó pelo `+` logo depois do nó que acabei de configurar, so that eu construa na ordem em que penso
3. As a Empresa com fluxo vazio, I want to ver claramente que preciso escolher um gatilho, so that eu não fique olhando para uma tela em branco
4. As a Empresa, I want to buscar o nó pelo nome, so that eu não precise decorar em qual categoria ele está
5. As a Empresa, I want to descrever meu atendimento em português e receber um fluxo montado, so that eu comece de algo em vez do zero
6. As a Empresa, I want to pedir ajustes por conversa depois do fluxo montado, so that eu refine sem arrastar tudo de novo
7. As a Empresa, I want to ver o que a IA vai mudar antes de aplicar, so that ela não desmonte o que eu fiz
8. As a Empresa, I want to clonar um Modelo de fluxo pronto, so that eu parta de algo que já funciona no meu segmento
9. As a Empresa, I want to conversar com o fluxo antes de publicar, so that eu descubra os erros antes do cliente
10. As a Empresa, I want to ver os nós acendendo enquanto testo, so that eu entenda por onde o fluxo passou
11. As a Empresa, I want to ver o custo em reais e em tokens do turno que acabei de testar, so that eu saiba o que esse atendimento vai custar
12. As a Empresa, I want to testar um fluxo de webhook sem disparar uma chamada de verdade, so that eu ajuste sem ferramenta externa
13. As a Empresa, I want to ser avisada na publicação quando o desenho tem problema, so that eu não publique fluxo com nó órfão ou sem saída
14. As a Empresa, I want to ver de relance se o fluxo está publicado, pausado ou é rascunho, so that eu saiba o que está no ar
15. As a Empresa, I want to paginar e filtrar a lista de fluxos, so that ela continue utilizável quando eu tiver dezenas

## Implementation Decisions

### Layout

- **Canvas em toda a área restante**, só a sidebar da aplicação permanece.
- **Flutuam com sombra**: barra de nome à esquerda, barra de ações à direita (abas Desenho/Execuções, Criar com IA, Testar, Publicar), seletor de nós, painel de propriedades e controles de zoom.
- **Sheets rentes, sem sombra**: chat de IA à direita, painel de teste embaixo.
- O seletor de nós e o painel de propriedades são o **mesmo painel flutuante em dois modos**, mutuamente exclusivos, com botão de fechar.
- O `+` abre busca central com a lista agrupada por categoria quando vazia.
- O gatilho é um **nó no canvas**, com aparência própria. Fluxo vazio mostra o nó-fantasma.
- Grafo horizontal, centrado na vertical.

### Biblioteca

React Flow para o canvas. **Dependência nova — precisa de aprovação antes de instalar.** O DSL é persistido pelo motor e o React Flow lê e escreve sobre ele; a posição é metadado.

### Geração por IA

- A IA produz **DSL**, validado por schema e por integridade do grafo (sem nó órfão, sem aresta para lugar nenhum, sem ciclo sem saída) antes de virar Fluxo. Nunca escrita direta no banco.
- Duas formas: gerar do zero a partir de descrição, e editar por conversa sobre o fluxo existente.
- As alterações propostas aparecem como lista para aplicar ou descartar, nunca aplicadas direto.

### Playground

- Roda a **Versão rascunho**. Nada é enviado ao Contato.
- Mostra conversa de um lado e Traço do turno do outro, com custo e tokens.
- Usa o gatilho manual invisível para rodar Fluxo de webhook com payload falso.
- Isso muda a definição de Playground no glossário: deixa de ser só do Agente.

### Listagem

- Tabela plana, sem agrupamento, com **paginação server-side**: intervalo, total, itens por página e navegação.
- Colunas: Status (interruptor + rótulo), Fluxo, Tipo (Conversa/Reação), Gatilho, Canal, execuções em 7 dias, conclusão, última execução.
- Status é **um estado só** com três valores: Rascunho, Publicado, Pausado. O interruptor fica desabilitado até a primeira publicação.
- "Alterações não publicadas" é selo derivado da comparação entre rascunho e Versão publicada, não um quarto estado.
- Filtros de Canal, Gatilho e Status.

### Validação na publicação

- Fluxo de conversa duplicado no mesmo Canal → recusa.
- Fluxo de reação que ganhou nó de espera → avisa que virou conversa.
- Fluxo de reação que envia mensagem num Canal que já tem Fluxo de conversa → avisa, não recusa.
- Fluxo com gatilho de webhook começando por nó de Mensagem em vez de Template → avisa (a janela de 24 h provavelmente estará fechada).
- Nó órfão, aresta sem destino, ciclo sem saída → recusa.

### Navegação

"Fluxos" entra como item de primeiro nível na sidebar, ao lado de Agentes. O Fluxo pertence à Empresa e se vincula a Canais pelo Gatilho, então não é filho de nenhum dos dois.
