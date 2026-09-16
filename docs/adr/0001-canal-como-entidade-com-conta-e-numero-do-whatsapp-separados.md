# Canal como entidade própria, com Conta e Número do WhatsApp separados

Precisávamos que o mesmo Agente pudesse atender em vários meios (WhatsApp, Chat no site, Instagram Direct) sem que cada meio reinventasse "qual Agente responde aqui" e "este atendimento está funcionando". Criamos uma tabela `channels` fina (Empresa, Tipo de canal, Agente, `ChannelStatus`, `last_error`) e mantivemos os dados específicos de cada Tipo de canal em tabelas próprias: `whatsapp_accounts` para as credenciais de app da Meta e `whatsapp_phone_numbers` para o número, com o Canal se prendendo ao **número**, porque é o número que recebe o webhook e atende.

## Considered Options

- **Tabela única com `settings` JSON**: rejeitada porque o webhook já depende de dois lookups indexados (`verify_token` e `phone_number_id`) e os segredos usam cast `encrypted` por coluna — ambos morrem dentro de um blob JSON.
- **Sem tabela pai, "Canal" apenas como enum unindo tabelas por tipo**: rejeitada porque o catálogo, o dashboard e o vínculo com o Agente precisariam conhecer cada tipo individualmente.
- **Canal preso à Conta em vez do Número**: rejeitada porque a unidade real de atendimento é o número; prender à conta faria `channels.agent_id` descrever algo que não corresponde a quem responde.

## Consequences

- `ChannelStatus` (NotConfigured, Connected, Error) é genérico e escrito explicitamente nas transições, para que catálogo e dashboard filtrem sem carregar a relação específica do tipo. O vocabulário rico da Meta (webhook verificado, qualidade do número) continua nas tabelas específicas.
- `unique(team_id, type)` limita a Empresa a um Canal por Tipo de canal. Suportar dois números de WhatsApp é remover esse índice, sem remodelagem.
- Excluir a configuração de um Canal apaga a linha de `channels` em cascata; o catálogo é gerado do enum, então não existem linhas órfãs em `NotConfigured`.
