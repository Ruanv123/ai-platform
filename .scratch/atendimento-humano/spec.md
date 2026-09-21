# Spec: Atendimento humano — caixa de entrada de exceção

Status: ready-for-agent

Depends on: `.scratch/fluxos-motor/spec.md`

## Problem Statement

O nó **Transferir para humano** existe no catálogo do motor, e a plataforma não tem para onde transferir. Um bot de atendimento sem saída para humano é inaceitável no Brasil: o cliente que pede para falar com alguém e continua falando com o robô vira reclamação.

Hoje, se um Fluxo desiste, não há tela onde a Equipe veja isso, nem forma de responder pela plataforma, nem registro de quem assumiu.

## Solution

Uma caixa de entrada **de exceção**: só as conversas que um Fluxo transferiu, não todas as conversas da Empresa.

- Lista de pendentes com filtros, thread da conversa, responder, devolver ao bot.
- Histórico completo em modo leitura, porque `Message` já existe e sai quase de graça.
- **Assumir explícito**: a conversa mostra quem assumiu, e quem não assumiu vê aviso antes de escrever.
- Enquanto está com humano, **o bot fica pausado** — o pior cenário é bot e pessoa falando junto.
- Painel lateral com a ficha do Contato e a Execução pausada, que abre e fecha.

## User Stories

1. As a Equipe, I want to ver as conversas que o bot transferiu, so that ninguém fique esperando
2. As a Equipe, I want to saber por que a conversa veio parar aqui, so that eu entre no assunto já sabendo o contexto
3. As a Equipe, I want to assumir a conversa explicitamente, so that duas pessoas não respondam ao mesmo cliente
4. As a Equipe, I want to ver quem assumiu, so that eu não atropele o colega
5. As a Equipe, I want to filtrar por conversas atribuídas a mim, so that eu trabalhe na minha fila
6. As a Equipe, I want to filtrar por não atribuídas, so that eu pegue o que está parado
7. As a Equipe, I want to filtrar por pessoa da Equipe, so that eu veja a carga de cada um
8. As a Equipe, I want to buscar por nome ou telefone, so that eu ache a conversa que o cliente citou no telefone
9. As a Equipe, I want to responder pelo mesmo número, so that o cliente não perceba a troca
10. As a Equipe, I want to que o bot pare de responder enquanto estou na conversa, so that não saiam duas respostas
11. As a Equipe, I want to devolver a conversa ao bot quando terminar, so that o atendimento automático continue
12. As a Equipe, I want to que a conversa seja liberada se eu sumir, so that ela não fique travada comigo
13. As a Equipe, I want to ver a ficha do Contato ao lado, so that eu não pergunte o que já está registrado
14. As a Equipe, I want to fechar o painel lateral, so that eu ganhe espaço para ler a conversa
15. As a Equipe, I want to ver a Execução pausada e abrir o traço, so that eu entenda onde o fluxo parou
16. As a Equipe, I want to saber quanto falta da janela de 24 horas, so that eu não descubra tarde demais que não posso mais responder
17. As a Equipe, I want to que a conversa atualize sozinha enquanto está aberta, so that eu não fique recarregando

## Implementation Decisions

### Escopo

- **Inbox de exceção para ação**, mais leitura do histórico completo em modo somente-visualização.
- **A inbox completa fica anotada para depois**, e cresce sobre os mesmos dados: `Message` já existe e já é escopada por Contato e Canal. Chegar à inbox completa pela metade é pior que não ter.

### Atribuição

- **Assumir explícito**, não fila única nem roteamento automático. Fila única garante colisão no primeiro dia com duas pessoas online; roteamento automático pressupõe equipe grande, presença e escala, nada disso existe aqui.
- **Liberação automática por inatividade**, senão quem assumiu e foi almoçar trava a conversa.
- Filtros: Aguardando, Minhas, Todas, mais seletor "Atribuída a" com as pessoas da Equipe, mais filtro de Canal e busca.

### Pausa do bot

- Transferir pausa a Execução e marca o Contato como aguardando humano. O motor não responde enquanto isso durar.
- Volta de duas formas: manual ("devolver ao bot") e automática por inatividade configurável. Sem a segunda, o Contato fica em limbo para sempre.
- A Equipe é notificada na transferência.

### Tempo real

- **Reverb entra aqui, e só aqui.** Dependências novas: `laravel/reverb` e `laravel-echo`, mais um processo em produção. **Aprovado pelo usuário, escopo restrito à inbox.**
- O dashboard de execuções continua em polling — ver `.scratch/fluxos-observabilidade/spec.md`.
- Motivo da diferença: na inbox há um humano conversando ao vivo com um cliente do outro lado; num dashboard de métricas, três segundos de atraso não mudam nada.

### Janela de 24 horas

A conversa mostra quanto falta da janela. Passado o prazo, a resposta livre é bloqueada e só Template passa — mesma regra do motor, exposta na UI para a pessoa não descobrir pelo erro.

### Navegação

"Atendimento" entra como item de primeiro nível na sidebar, com contador de pendentes.
