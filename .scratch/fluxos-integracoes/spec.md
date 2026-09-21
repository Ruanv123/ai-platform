# Spec: Integrações e barramento de Eventos

Status: ready-for-agent

Depends on: `.scratch/fluxos-motor/spec.md`

## Problem Statement

A tela de Integrações é um catálogo de seis logos de CRM e alguns de e-commerce, gerado de enums, e **nenhum deles conecta**. A Empresa vê "Criar e atualizar contatos" listado como capacidade e não consegue criar nada.

Ao mesmo tempo, o atendimento que realmente resolve algo precisa falar com o mundo: consultar agenda, conferir pedido, criar oportunidade. E, na direção contrária, o sistema do cliente precisa saber o que aconteceu no atendimento.

## Solution

Duas pontes e um barramento.

- **Nó de Requisição HTTP** genérico: método, URL, headers, corpo com Variáveis, e mapeamento da resposta de volta para Variáveis. Destrava tudo sem negociar com nenhuma API.
- **Um conector curado** como prova do padrão, compilando para o mesmo motor de execução, retry e tratamento de erro do nó genérico. O primeiro é **Agenda**, não CRM.
- **Barramento de Eventos**: um nó emite um Evento nomeado, que alimenta o funil do dashboard e pode sair como webhook assinado para o sistema do cliente.

## User Stories

1. As a Empresa, I want to chamar qualquer API a partir de um nó, so that eu conecte o sistema que já uso sem esperar vocês construírem o conector
2. As a Empresa, I want to montar o corpo da requisição com o que o Contato me disse, so that a chamada leve os dados do atendimento
3. As a Empresa, I want to mapear a resposta da API para variáveis do fluxo, so that o que voltou continue a conversa
4. As a Empresa, I want to que uma falha temporária da API seja tentada de novo, so that instabilidade não vire atendimento perdido
5. As a Empresa, I want to decidir o caminho do fluxo quando a chamada falha de vez, so that o cliente receba uma resposta em vez de silêncio
6. As a Empresa, I want to guardar a credencial uma vez e usá-la em vários nós, so that eu não repita segredo no desenho
7. As a Empresa, I want to marcar campos sensíveis da requisição, so that eles não fiquem legíveis no traço
8. As a Empresa, I want to marcar horário na minha agenda direto pelo fluxo, so that o Contato saia da conversa com o compromisso criado
9. As a Empresa, I want to emitir um evento nomeado num ponto do fluxo, so that eu meça quantos chegaram ali
10. As a Empresa, I want to receber um webhook quando o evento acontece, so that meu sistema reaja ao atendimento
11. As a Empresa, I want to que o webhook de saída seja assinado, so that meu sistema confirme que veio de vocês
12. As a Empresa, I want to ver os webhooks que falharam, so that eu descubra a entrega perdida sem auditoria manual
13. As a Empresa, I want to declarar os nomes de evento que uso, so that um erro de digitação não crie um funil paralelo

## Implementation Decisions

### Nó de Requisição HTTP

- Método, URL, headers, corpo, tudo com interpolação de Variáveis, Campos do Contato e Variáveis globais.
- Mapeamento declarado da resposta para Variáveis (caminho no JSON → nome da Variável).
- Retry com backoff exponencial para falha temporária; saída de erro própria no nó para falha definitiva, de forma que o desenho decida o caminho.
- Lista de campos sensíveis declarada no nó, que alimenta o mascaramento do Traço.
- Configuração longa abre em janela cheia, não no painel flutuante.

### Conectores curados

- **Compilam para o nó genérico**: mesmo motor de execução, mesmo retry, mesmo tratamento de erro. O conector é açúcar tipado por cima, não uma segunda implementação.
- **O primeiro é Agenda**, não CRM. Agendamento é o caso de uso número um de WhatsApp no Brasil — consultório, salão, oficina, clínica — e é o único que fecha o ciclo sozinho: o Contato marca e a coisa acontece. Um conector de CRM sem time comercial do outro lado vira registro que ninguém lê.
- A **Integração** guarda a credencial e a conexão; o Fluxo guarda o que fazer com ela. Isso amplia a definição de Integração no glossário, que era só CRM.
- O catálogo de enums existente (`CrmProvider`, `EcommerceProvider`) continua como catálogo, mas a tela precisa parar de prometer capacidade que não existe.

### Barramento de Eventos

- O Evento é **declarado** pela Empresa: nome, descrição e formato do dado. String livre vira erro de digitação silencioso — `lead_qualificado` e `lead-qualificado` viram dois funis e ninguém descobre.
- Um Evento emitido **sempre** alimenta o funil e as métricas do Fluxo. Isso dá analytics de graça em vez de instrumentar o motor duas vezes.
- Pode ter assinantes externos: webhook de saída e, mais tarde, conector.
- **A assinatura interna — um Fluxo disparado por Evento de outro — fica fora da v1.** O barramento é construído inteiro; só essa perna adormece.

### Webhook de saída

- Entrega **at-least-once**, assinatura HMAC em header, retry com backoff exponencial.
- **Fila de falhas visível na UI.** Este item não é opcional: é o antídoto direto para "automação que falha silenciosamente", a queixa mais citada do mercado.

### Conflito de nome

`Message.event_type` hoje significa tipo de payload do provedor, e `CONTEXT.md` já mandava evitar "Evento" para Mensagem. **Renomear a coluna para `payload_type`** e liberar "Evento" para o barramento. É uma migração de uma coluna, feita agora custa quase nada, e evita explicar a ambiguidade para todo desenvolvedor futuro.
