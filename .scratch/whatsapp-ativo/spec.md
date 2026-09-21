# Spec: WhatsApp ativo — Templates, webhook de entrada e consentimento

Status: ready-for-agent

Depends on: `.scratch/fluxos-motor/spec.md`

## Problem Statement

Hoje a plataforma só reage. `WhatsAppCustomerCareWindow` existe, detecta que a janela de 24 horas fechou e simplesmente **descarta** a resposta: o Contato não recebe nada e ninguém é avisado.

Isso impede o caso mais pedido de automação de WhatsApp no Brasil: o sistema do cliente — agenda, CRM, ERP — precisa iniciar uma conversa. "Sua consulta é amanhã", "seu exame ficou pronto", "seu pedido saiu para entrega". Com a janela fechada, a única forma legal é um Template aprovado pela Meta, e a plataforma não tem nenhum.

## Solution

Dar à plataforma a capacidade de iniciar conversa, com as travas que isso exige.

- **Templates** sincronizados da Conta do WhatsApp, e criados e submetidos à Meta pela própria plataforma, com acompanhamento de aprovação e motivo de rejeição legível.
- **Gatilho de webhook** por Canal: o sistema do cliente chama um endereço e um Fluxo começa para um Contato.
- **Consentimento por finalidade**, com registro auditável e detecção automática de palavras de saída.
- **Rate limit em três camadas**, porque o endereço é público e cada chamada gasta dinheiro em IA.

## User Stories

### Templates

1. As a Empresa, I want to ver meus templates já aprovados na Meta dentro da plataforma, so that eu os use nos fluxos sem copiar texto à mão
2. As a Empresa, I want to criar um template e submetê-lo à Meta daqui, so that eu não precise abrir o Business Manager
3. As a Empresa, I want to acompanhar o status da aprovação, so that eu saiba quando posso usar
4. As a Empresa, I want to entender por que a Meta recusou, em português claro, so that eu corrija em vez de tentar de novo no escuro
5. As a Empresa, I want to saber quando a Meta recategorizou meu template, so that eu não seja surpreendida pela mudança de preço
6. As a Empresa, I want to preencher as variáveis do template com dados do Contato, so that a mensagem chegue personalizada

### Iniciar conversa

7. As a Empresa, I want to que meu sistema dispare um fluxo por webhook, so that o atendimento comece a partir do que acontece fora da plataforma
8. As a Empresa, I want to que o payload da chamada vire variáveis do fluxo, so that a mensagem use os dados que meu sistema mandou
9. As a Empresa, I want to proteger o endereço com um token de cabeçalho, so that nem todo mundo consiga disparar atendimento
10. As a Empresa, I want to que o endereço tenha limite de chamadas, so that um ataque não derrube meu atendimento nem gaste meu orçamento
11. As a Empresa, I want to escolher o que acontece quando o telefone não é um Contato conhecido, so that o comportamento não seja surpresa
12. As a Empresa, I want to que o fluxo use Template quando a janela está fechada, so that a mensagem chegue de verdade
13. As a Empresa, I want to ser avisada no desenho quando um fluxo de webhook começa sem Template, so that eu descubra o problema antes de publicar

### Consentimento

14. As a Contato, I want to que "SAIR" pare os disparos imediatamente, so that eu não precise repetir
15. As a Contato, I want to recusar marketing sem perder aviso de consulta, so that eu continue sendo atendido no que me interessa
16. As a Empresa, I want to ter registro de quando e como o consentimento foi dado e revogado, so that eu consiga comprovar
17. As a Empresa, I want to que a plataforma recuse o disparo sem consentimento, so that meu número não seja penalizado por erro de desenho

## Implementation Decisions

### Templates

- Model próprio ligado a `WhatsAppAccount`, **não a `Team`**: a Meta aprova por WABA, e todos os `WhatsAppPhoneNumber` daquela conta compartilham os templates. A UI mostra "seus templates" sem expor WABA, e só revela o agrupamento se a Empresa tiver mais de uma conta.
- Guarda status de aprovação, motivo de rejeição, categoria pedida, **categoria atribuída pela Meta** (que pode diferir) e o histórico dessas mudanças.
- **Leitura e submissão.** A submissão coloca a plataforma no meio de um processo que ela não controla: rejeição com motivo opaco, recategorização unilateral, e a Empresa cobrando uma aprovação que é da Meta. A tela que mais importa é a que **explica a recusa em português** — é ali que o BotConversa perde cliente ao dizer "problema da Meta".
- Navegação: **seção de primeiro nível "Templates"**, visível apenas quando a Empresa tem Canal de WhatsApp oficial.

### Gatilho de webhook

- **O endereço é do Canal, não do Fluxo.** Um Canal, um endereço; os Fluxos com gatilho de webhook naquele Canal é que respondem.
- Qual Fluxo dispara: cada um declara um **nome de evento** que o payload precisa bater por igualdade. Sem sobreposição possível, sem regra de desempate — ver ADR 0006.
- **Autenticação opcional** por token de cabeçalho, do tipo `X-...-Token`. Autentica a rota, não libera capacidade.
- **Risco aceito e registrado**: sem token obrigatório, o rate limit é a única proteção do endereço público. Mesma natureza do risco aceito no ADR 0002 para a rota do WhatsApp de testes.
- **Rate limit em três camadas**: por Canal, por Empresa e teto global da plataforma. O de Empresa é o que protege a conta de LLM — cada chamada pode acionar IA, transcrição e envio, então é amplificação de custo, não só de tráfego.
- **Telefone desconhecido: recusar**, declarado no gatilho (a alternativa "criar Contato" existe, mas não é o padrão).
- Este webhook **não tem relação** com o webhook da Meta, que já existe em rota própria com validação de `X-Hub-Signature-256` e não muda.

### Janela de 24 horas

- O nó **Enviar Template** é o caminho para falar com a janela fechada. Nó separado de Mensagem, porque HSM tem variáveis posicionais, botões e categoria, e nenhum texto livre.
- Validação na publicação: Fluxo com gatilho de webhook começando por nó de Mensagem recebe aviso.
- O comportamento atual de descartar a resposta fora da janela passa a ser do motor, e registra falha visível no Traço em vez de sumir.

### Consentimento

- **Por finalidade**, espelhando as categorias da Meta: marketing, utilidade, autenticação.
- **Registro auditável** append-only de quando e como foi dado e revogado.
- Detecção automática de palavras de saída — "sair", "parar", "cancelar", "descadastrar" — interceptada **antes** do motor de fluxo. Nunca depende de a Empresa ter desenhado um nó para isso.
- O envio ativo sem consentimento da finalidade é recusado pelo motor, não pelo desenho.

### Fora do escopo

**Campanha e disparo em massa não entram.** Exigem aprovação de template em lote, controle de rate limit da Meta, opt-out obrigatório em escala, monitoramento de qualidade do número e pausa automática por queda de qualidade. É outro produto, e o risco é o cliente perder a WABA dele por culpa da plataforma.
