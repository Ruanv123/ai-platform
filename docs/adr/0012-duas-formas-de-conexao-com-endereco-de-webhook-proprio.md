# Duas Formas de conexão, cada uma com seu endereço de webhook

A Conta do WhatsApp pode ser ligada à plataforma pelo **Tech Provider** (a Empresa autoriza a plataforma pelo cadastro incorporado da Meta e passa a usar o App da Meta da plataforma) ou por **App próprio** (a Empresa informa as credenciais do seu App da Meta). As duas convivem, e cada uma recebe webhooks num endereço próprio: `/webhooks/whatsapp` para o Tech Provider, validado com o secret do App da plataforma, e `/webhooks/whatsapp/app-proprio` para App próprio, onde a Conta é encontrada pelo WABA ID do próprio payload (`entry[].id`) e a assinatura é validada com o `app_secret` dessa Conta.

## Considered Options

- **Só Tech Provider**: rejeitada porque deixa a plataforma dependente da aprovação da Meta como Tech Provider para ter qualquer cliente, e tira a saída de quem já tem um App da Meta próprio.
- **Um endereço só, tentando o secret da plataforma e depois o da Conta**: rejeitada porque a validação da assinatura deixaria de ser determinística, e uma Conta de App próprio poderia ser tratada como Tech Provider (ou o contrário) por acidente.
- **Identificador ou token da Conta no caminho da URL**: rejeitada porque nada que identifique ou autentique a Conta deve trafegar na URL; o WABA ID já vem no corpo assinado.

## Consequences

- No Tech Provider, URL, verify token e secret de webhook são da plataforma e configurados uma vez no App da Meta dela; nada disso é por Conta. No App próprio, verify token e `app_secret` são por Conta.
- Quem conecta por App próprio cola a URL no seu App da Meta, então mudar esse endereço quebra a integração dessas Empresas.
- O webhook bruto não é armazenado: o controller valida a assinatura, responde 200 e enfileira. A idempotência vem do `provider_message_id`; um job que falha só deixa cópia em `failed_jobs`, e eventos de WABA desconhecida são descartados sem rastro. Risco aceito.
- Desconectar nunca pode deixar a Conta do WhatsApp da Empresa inutilizável: no Tech Provider a plataforma só remove a inscrição do seu App na WABA e apaga token e PIN; o número não é desregistrado.
