# WhatsApp de testes como Tipo de canal próprio

Para desenvolver o atendimento sem depender de um número aprovado na Meta, passamos a conectar o WhatsApp também por um serviço não oficial (Evolution Go) em desenvolvimento. Registramos esse meio como um caso próprio de `ChannelType` — **WhatsApp de testes** — e não como um provedor alternativo dentro do Tipo WhatsApp.

## Considered Options

- **Provedor dentro do Tipo WhatsApp** (uma coluna `provider` no Canal, catálogo mostrando "WhatsApp" uma vez): rejeitada porque o Canal passaria a ter duas dimensões de variação (tipo e provedor) para atender um único caso, e porque o `unique(team_id, type)` do ADR 0001 impediria ter o oficial e o de testes lado a lado — que é exatamente o que desenvolver exige.
- **Nenhum registro no domínio**, apontando o Evolution para o webhook oficial: rejeitada porque os payloads não se parecem (o Evolution Go usa `{event, instance, data}` com `MESSAGE`/`READ_RECEIPT`, a Meta usa `entry.changes.value` com `messages`/`statuses`) e porque não há assinatura para validar.

## Consequences

- O catálogo é gerado do enum, então o novo Tipo precisa de um gate para não aparecer ao cliente: `app()->isLocal() || $user->is_platform_admin`, o mesmo par já usado pelo `HorizonServiceProvider`.
- `unique(team_id, type)` continua valendo por tipo, então uma Empresa pode ter o WhatsApp oficial e o de testes ao mesmo tempo. Como o Contato é por Empresa e a Mensagem é presa ao Canal, o Histórico que o Agente lê é filtrado por Canal — sem isso o canal de testes contaminaria o oficial.
- **Risco aceito**: o webhook do WhatsApp de testes não tem autenticação (o Evolution Go não assina o payload) e a rota é registrada em todos os ambientes. Qualquer requisição a ela cria Contato e dispara chamada paga de IA. A garantia é operacional — não configurar um Canal de testes em produção — não técnica.
