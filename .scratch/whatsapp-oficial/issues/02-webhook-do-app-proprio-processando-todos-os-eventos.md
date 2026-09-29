# 02: Webhook do App próprio em rota própria, processando todos os eventos

**What to build:** A Empresa com App próprio configura no seu App da Meta a URL `/webhooks/whatsapp/app-proprio` e o verify token da Conta. A plataforma valida cada POST com o `app_secret` da Conta encontrada pelo WABA ID do próprio payload e processa **todos** os eventos que a Meta agrupou no mesmo POST, não só o primeiro. Ver ADR 0012 e spec `../spec.md`, seção "Webhook".

**Blocked by:** 01

**Status:** resolved

- [x] GET na rota nova conclui o handshake buscando a Conta pelo `verify_token` e grava `webhook_verified_at`; token desconhecido recebe 403
- [x] POST acha a Conta por `entry[].id` e valida `X-Hub-Signature-256` com o `app_secret` dela; assinatura ausente ou inválida recebe 403
- [x] POST com assinatura válida de WABA desconhecida, ou com campo desconhecido, recebe 200 e é descartado
- [x] Nenhum identificador ou segredo da Conta vai na URL
- [x] O processamento percorre todos os `entry → changes`, despacha por `field` e, em `messages`, trata cada item de `messages[]` e `statuses[]` separadamente
- [x] Mesma mensagem entregue duas vezes é registrada uma vez; a unicidade é por item (`provider_message_id`), não pelo POST
- [x] Um POST com vários entries, changes e mensagens registra todas as Mensagens e aciona o Agente para cada Contato
- [x] A tela do WhatsApp em App próprio mostra a URL nova e pede o App ID junto das credenciais atuais
- [x] A rota antiga deixa de atender o App próprio (ela passa a ser do Tech Provider no ticket 03)
- [x] Testes pelas rotas HTTP com fixtures de payload real (`WhatsAppWebhookTest` e `WhatsAppWebhookConformanceTest` como referência)
