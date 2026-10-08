# Spec: Templates do Agente — biblioteca de modelos e envio pela IA

Status: done

Depends on: `.scratch/whatsapp-ativo/spec.md` (Templates, consentimento por finalidade)

## Problem Statement

O Agente de IA só consegue falar com o Contato enquanto a janela de 24 horas da Meta está aberta. Fora dela, a resposta é descartada e registrada como falha (`customer_care_window_closed`). Ele também nunca toma a iniciativa: não lembra o Contato de um compromisso que ele mesmo marcou e não retoma uma conversa que parou no meio.

A Empresa já consegue criar Templates, mas criar do zero é difícil (categoria, variáveis, exemplos, aprovação) e nada liga um Template ao Agente. O Agente não sabe que Templates existem nem como preenchê-los.

## Solution

1. **Biblioteca de modelos.** A plataforma oferece modelos prontos de Template pensados para o Agente (retomar conversa, lembrete de compromisso, confirmação de agendamento, follow-up). A Empresa escolhe um modelo, revisa no editor de Templates e envia para a Meta. Cada variável do modelo vem com uma descrição do que ela representa.
2. **Templates do Agente.** Na tela do Agente, a Empresa escolhe quais Templates aprovados ele pode usar e para quê: retomar conversa com a janela fechada, lembrete de compromisso, follow-up sem resposta ou uso livre.
3. **Envio pela IA.** O Agente envia o Template na situação configurada e preenche as variáveis sozinho, a partir da descrição de cada variável, da conversa e dos dados do Contato e da agenda.

## User Stories

### Biblioteca

1. As a Empresa, I want to começar um Template a partir de um modelo pronto, so that eu não precise escrever do zero nem adivinhar a categoria certa
2. As a Empresa, I want to ver para que serve cada modelo e como ele chega ao Contato, so that eu escolha o certo
3. As a Empresa, I want to ajustar o texto do modelo antes de enviar à Meta, so that a mensagem tenha a cara da minha empresa
4. As a Empresa, I want to descrever o que cada variável significa, so that o Agente saiba como preenchê-la

### Templates do Agente

5. As a Empresa, I want to escolher quais Templates aprovados o Agente pode usar, so that ele não envie nada que eu não autorizei
6. As a Empresa, I want to dizer para que o Agente usa cada Template, so that ele envie o Template certo em cada situação
7. As a Empresa, I want to definir com quanta antecedência o lembrete sai e depois de quanto tempo sem resposta o follow-up sai, so that o ritmo combine com o meu negócio
8. As a Empresa, I want to ser avisada quando um Template escolhido deixa de estar aprovado, so that o Agente não fique sem ação sem eu saber

### Envio

9. As a Empresa, I want to que o Agente retome a conversa com um Template quando a janela de 24 horas fechou, so that o Contato receba a resposta em vez de ela sumir
10. As a Empresa, I want to que o Agente lembre o Contato dos compromissos marcados, so that menos gente falte
11. As a Empresa, I want to que o Agente retome conversas paradas, so that eu não perca oportunidades
12. As a Empresa, I want to que o Agente possa enviar um Template quando fizer sentido na conversa, so that ele use mensagens com botões e mídia que eu já aprovei
13. As a Empresa, I want to que as variáveis sejam preenchidas pela IA com base na conversa, so that a mensagem chegue personalizada sem eu mapear campos
14. As a Contato, I want to não receber marketing se eu não autorizei, so that eu não seja incomodado
15. As a Empresa, I want to ver no Atendimento cada Template que o Agente enviou ou deixou de enviar, e por quê, so that eu acompanhe o que a IA faz em meu nome

## Implementation Decisions

### Biblioteca de modelos

- Os modelos são **definidos no código** (catálogo versionado com a aplicação), não em tabela editável. A plataforma é dona do texto base; a Empresa edita a cópia dela.
- Cada modelo declara: chave estável, nome, para que serve, uso sugerido para o Agente, categoria, idioma, cabeçalho, corpo, rodapé, botões, exemplo e **descrição de cada variável**.
- "Usar modelo" abre o editor de Templates já preenchido. Nada vai para a Meta sem a Empresa revisar e enviar.
- O Template criado guarda a chave do modelo de origem, só para referência e métricas. Editar o Template não altera o modelo.
- Primeira versão da biblioteca: retomar conversa, lembrete de compromisso, confirmação de agendamento, follow-up de atendimento e follow-up de vendas. Todos no formato **Personalizado**, porque é o único que o envio pela conversa já suporta.

### Descrição das variáveis

- Toda variável de Template (do corpo e do cabeçalho) passa a ter uma **descrição opcional** além do exemplo, editável no painel de variáveis.
- A descrição sobrevive à edição do Template e à sincronização com a Meta. A Meta não conhece descrição; ela é só da plataforma.
- Template sem descrição em alguma variável ainda pode ser usado pelo Agente, mas a tela do Agente avisa que o preenchimento fica menos confiável.

### Templates do Agente

- Ligação **Agente × Template** com um **uso** por ligação: `resume_conversation` (janela fechada), `appointment_reminder`, `follow_up` ou `on_demand` (o Agente decide).
- Um Agente tem **no máximo um Template por uso automático** (`resume_conversation`, `appointment_reminder`, `follow_up`) **por Conta do WhatsApp**, porque o Template é aprovado por WABA e o Agente pode atender Canais de Contas diferentes. Para `on_demand` não há limite.
- Só entram Templates **aprovados**, de formato que o envio já suporta (hoje, Personalizado), de Contas dos Canais da Empresa.
- Configuração do Agente: **antecedência do lembrete** (padrão 24 horas) e **tempo sem resposta para o follow-up** (padrão 24 horas). O follow-up acontece **uma vez** por conversa parada.
- A ligação faz parte da configuração do Agente e segue o mesmo fluxo de rascunho e publicação que as outras configurações.
- Template que deixa de estar aprovado continua ligado, mas é ignorado no envio e aparece com aviso na tela do Agente.

### Preenchimento das variáveis pela IA

- Um serviço próprio gera os valores com **saída estruturada**: recebe o texto do Template, a descrição e o exemplo de cada variável, o motivo do envio, os dados do Contato, o histórico recente e, no lembrete, os dados do compromisso.
- Cada valor precisa vir preenchido, sem quebra de linha e dentro do limite da Meta. Se a IA não conseguir, **o Template não é enviado** e a falha fica registrada. Nunca se envia variável vazia ou com o exemplo.
- Na ferramenta `on_demand`, o próprio Agente passa os valores na chamada da ferramenta, com as descrições no esquema; a mesma validação vale.

### Envio

- Um único ponto de envio para o Agente: valida o Template (aprovado, da Conta do Canal, formato suportado), checa consentimento, preenche as variáveis e envia pelo adaptador de saída que o Atendimento já usa. A Mensagem de saída fica registrada como as demais, com o Agente como autor.
- **Consentimento**: Template de **marketing** só sai se o Contato concedeu marketing. Qualquer categoria é bloqueada se a finalidade foi **revogada**. Utilidade sem registro de consentimento é permitida, porque responde a algo que o Contato pediu.
- **Conversa pausada** (assumida por humano) nunca recebe envio automático do Agente.
- **Janela fechada**: quando o Agente gera uma resposta e a janela fechou, ele envia o Template de `resume_conversation` (se houver) em vez de descartar. Sem Template configurado, o comportamento atual continua, com falha registrada.
- **Lembrete**: uma rotina agendada procura compromissos marcados pelo Agente que entram na antecedência configurada e envia o lembrete **uma única vez** por compromisso. Compromisso cancelado ou já passado não recebe lembrete.
- **Follow-up**: uma rotina agendada procura conversas cuja última Mensagem foi do Agente, sem resposta do Contato há mais que o tempo configurado, e envia o follow-up **uma única vez** por Mensagem sem resposta. Só sai dentro do horário de atendimento da Empresa, quando configurado.
- **Uso livre**: ferramenta `enviar_template` disponível na conversa quando o Agente tem Templates `on_demand`. A descrição da ferramenta lista cada Template e quando usá-lo.
- Todo envio automático que **não** acontece por regra (consentimento, Template não aprovado, IA sem valores) fica registrado no Atendimento com o motivo, como já acontece com `customer_care_window_closed`.

### Fora do escopo

- Formatos Carrossel, Cupom, Oferta, Pedido de ligação e Autenticação no envio pelo Agente: eles exigem parâmetros extras (mídia por card, data de término, código). Entram quando o envio pela conversa suportar.
- Campanhas e disparo em massa, pelos mesmos motivos do spec `whatsapp-ativo`.
- Mais de um follow-up em sequência (cadência). A primeira versão manda um só.
- Biblioteca editável pela própria Empresa ou por segmento.

## Testing Decisions

- Testes de feature para cada gatilho, com a Meta e a IA falsificadas (`Http::fake`, fakes do SDK de IA): envia quando deve, não envia quando a regra bloqueia, e não envia duas vezes.
- Testar a ligação Agente × Template pela requisição de salvar o Agente: Template não aprovado, de outra Empresa ou de formato não suportado é recusado; um único Template por uso automático e por Conta.
- Testar que a descrição das variáveis sobrevive à edição e à sincronização.
- Testar que "Usar modelo" abre o editor preenchido e que o Template salvo guarda a chave do modelo.
