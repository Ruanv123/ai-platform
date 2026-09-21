# Spec: Traço e dashboard de execuções

Status: ready-for-agent

Depends on: `.scratch/fluxos-motor/spec.md`

## Problem Statement

Quando um fluxo quebra, a Empresa descobre pelo cliente reclamando. É a queixa mais cara do mercado: no ManyChat as automações falham silenciosamente, no BotConversa os fluxos não disparam e o suporte devolve a culpa para a Meta, e no Typebot a ausência de analytics de fluxo é lacuna reconhecida.

Sem traço, "por que o fluxo parou" é uma investigação. Sem funil por nó, a Empresa não sabe onde o atendimento vaza — e esse é exatamente o dado que justifica o preço do produto.

## Solution

Gravar tudo que uma Execução faz, e transformar isso em duas telas: um dashboard que avisa e um traço que explica.

- Quatro níveis cumulativos de gravação: passo a passo por nó, variáveis a cada passo, payload completo das requisições HTTP, e prompt e resposta crus do LLM com tokens e custo.
- Campos sensíveis são mascarados; os níveis 3 e 4 só aparecem para Owner e Admin.
- O dashboard do Fluxo mostra tiles, execuções por dia, erros mais frequentes e o **funil por nó**: quantos entraram, quantos saíram, onde desistem.
- A lista de execuções abre no traço com o nó que quebrou destacado.
- Traço detalhado por 30 dias; agregados para sempre.

## User Stories

1. As a Empresa, I want to ver por onde a Execução passou, nó a nó, so that eu entenda o que o bot fez
2. As a Empresa, I want to ver as variáveis no momento da falha, so that eu descubra qual dado estava errado
3. As a Empresa, I want to ver o que foi enviado e recebido numa requisição HTTP, so that eu corrija o campo que a API recusou
4. As a Owner, I want to ver o prompt e a resposta crus do LLM, so that eu entenda por que o Agente respondeu aquilo
5. As a Member, I want to não ver o prompt nem o payload, so that eu não leia dado pessoal de todo Contato para fazer meu trabalho
6. As a Empresa, I want to que CPF e dados de pagamento apareçam mascarados, so that o traço não vire um vazamento
7. As a Empresa, I want to ver quanto cada execução custou em IA, so that eu saiba se esse atendimento se paga
8. As a Empresa, I want to ver quantas execuções começaram, terminaram, foram abandonadas e falharam, so that eu saiba se o atendimento está saudável
9. As a Empresa, I want to ver onde as execuções param, nó a nó, so that eu conserte o passo que espanta o cliente
10. As a Empresa, I want to ver os erros mais frequentes agrupados, so that eu ataque a causa e não um caso
11. As a Empresa, I want to ver execuções presas, so that eu descubra o fluxo travado antes do cliente reclamar
12. As a Empresa, I want to sair de "algo está errado" para a linha exata em poucos cliques, so that depurar não vire projeto
13. As a Empresa, I want to filtrar execuções por falha, presas e todas, so that eu olhe primeiro o que importa
14. As a Empresa, I want to abrir a execução no canvas, so that eu veja o nó problemático no desenho

## Implementation Decisions

### Níveis de gravação

1. Traço por nó: entrou, saiu, qual aresta seguiu, duração, erro
2. Snapshot de Variáveis a cada passo
3. Payload completo das requisições HTTP, request e response
4. Prompt e resposta crus do LLM, com tokens e custo estimado

Os quatro entram na v1. **Replay fica para depois.**

### Dado sensível

- Mascaramento por lista de campos declarada no nó. O valor vira `***` no traço, no payload e na ficha.
- Níveis 3 e 4 visíveis só para Owner e Admin. Member vê 1 e 2.
- **Credenciais de conector nunca são gravadas**, em nenhum nível, sem exceção configurável.

### Retenção

Traço detalhado por 30 dias, agregados para sempre. Sem esse corte a tabela de traços vira a maior do banco em dois meses. Se for preciso mais, que seja configurável por plano, não um número maior para todos.

### Dashboard

- Aba **Execuções** dentro do Fluxo, com tiles, gráfico de execuções por dia, erros mais frequentes e funil por nó.
- O Dashboard geral da Empresa ganha um **cartão-resumo** que linka para lá. Um lugar para investigar, um sinal para avisar.
- Métricas: iniciadas, concluídas, abandonadas, com falha, presas, custo de IA, e o funil com a queda percentual entre nós.
- **Comparação entre Versões** é o próximo candidato: publicar e ver a taxa cair é o que o versionamento imutável permite medir. Fora da v1.

### Gráficos

- **Sem eixo duplo.** Execuções e falhas diferem por uma ordem de grandeza; plotar juntas deixa a linha de falhas colada no zero. Falha aparece em tile, tooltip e card próprio.
- A paleta `--chart-1..5` do projeto **reprova** na validação: `--chart-4` e `--chart-5` têm ΔE 7,4 a olho normal (piso é 15) e ambas ficam abaixo de 3:1 de contraste contra branco; `--chart-3` lê como cinza. Usar `--chart-2` com `--destructive`, que passa nos seis testes no modo claro. **Corrigir os tokens do projeto é trabalho à parte.**
- Série única dispensa legenda; o título nomeia. Duas ou mais exigem legenda mais rótulo direto.

### Tempo real

**Polling**, não WebSocket. Num dashboard operacional, atualizar a cada três segundos é indistinguível de tempo real, e o valor está no dado estar gravado e buscável. O Reverb entra só na caixa de entrada — ver `.scratch/atendimento-humano/spec.md`.
