# Spec: WhatsApp oficial completo — conexão, webhooks, mídia e Templates

Status: ready-for-agent

Related: `.scratch/atendimento-no-whatsapp/spec.md`, `.scratch/whatsapp-ativo/spec.md`, ADR 0001, ADR 0004, ADR 0012

## Problem Statement

Hoje a Empresa só consegue ligar o WhatsApp oficial colando à mão WABA ID, Phone Number ID, access token e app secret de um App da Meta que ela mesma precisa criar. Isso é impraticável para uma clínica ou loja brasileira comum: exige conta de desenvolvedor na Meta, criar app, gerar token de system user e configurar webhook.

Mesmo depois de conectada, a Empresa perde coisas sem perceber:

- A Meta agrupa vários eventos num mesmo webhook, e a plataforma só processa o primeiro. Mensagens de Contatos somem.
- Eventos que não são de mensagem (aprovação de Template, qualidade do número, bloqueio da conta, remoção do parceiro) são respondidos com erro. A Meta repete a entrega e pode desativar o webhook do app.
- Quando um envio falha, a Empresa vê só "falhou", sem saber por quê.
- A Equipe só responde com texto na conversa. Não consegue enviar uma foto, um PDF ou um áudio.
- Reações e cliques em botão de Template não chegam. O Contato toca em "Confirmar consulta" e nada acontece.
- Templates com imagem ou documento no cabeçalho não podem ser criados nem enviados pela plataforma.
- A estrutura atual prende a Conta do WhatsApp a um único número, o contrário do que a Meta faz: a Conta é dona dos números e dos Templates.

## Solution

A Empresa conecta o WhatsApp com um clique em **"Conectar com a Meta"**, pelo cadastro incorporado da Meta, com a plataforma atuando como **Tech Provider**. Quem já tem App da Meta próprio continua podendo informar as credenciais, pela Forma de conexão **App próprio**. As duas formas recebem webhooks em endereços próprios e validam a assinatura de forma determinística (ADR 0012).

Todo evento que chega é processado, não só o primeiro. A conversa mostra texto, imagem, áudio, documento, vídeo, Reações e cliques em botão. O resto aparece como "Tipo de mensagem não suportado". Falhas de envio aparecem na bolha, com explicação em português. A Equipe envia imagem, documento, vídeo e áudio gravado no navegador, que chega como mensagem de voz. Os Templates passam a ter estrutura própria (componentes, variáveis e botões), aceitam cabeçalho de imagem e documento e são atualizados sozinhos pelos webhooks da Meta. Mudanças na conta e no número (bloqueio, remoção do parceiro, qualidade, limite) ficam registradas e refletem no estado do Canal.

## User Stories

### Conexão pelo Tech Provider

1. As a Empresa, I want to conectar meu WhatsApp clicando em "Conectar com a Meta", so that eu não precise criar app nem copiar tokens
2. As a Empresa, I want to escolher, dentro da janela da Meta, qual Conta do WhatsApp e qual número conectar, so that eu use o número que já atende meus clientes
3. As a Empresa, I want to ver o andamento da conexão (autorização, inscrição, registro do número, sincronização), so that eu saiba o que está acontecendo
4. As a Empresa, I want to que, se algum passo falhar, a tela diga em português o que houve e ofereça "tentar de novo", so that eu não precise recomeçar do zero
5. As a Empresa, I want to que a nova tentativa continue do passo que falhou, so that nada seja feito duas vezes na minha conta da Meta
6. As a Empresa, I want to informar o PIN de confirmação em duas etapas quando meu número já tiver um, so that o registro não falhe por falta dele
7. As a Empresa, I want to ver o nome exibido, a qualidade e o limite de mensagens do meu número depois de conectar, so that eu saiba se o número está saudável
8. As a Empresa, I want to que meus Templates já aprovados apareçam logo após conectar, so that eu os use sem sincronizar à mão
9. As a Empresa, I want to escolher qual Agente atende no número conectado, so that o atendimento comece em seguida

### Conexão por App próprio

10. As a Empresa que já tem App da Meta, I want to informar minhas credenciais em vez de usar o cadastro incorporado, so that eu mantenha o controle do meu app
11. As a Empresa com App próprio, I want to ver a URL de webhook e o verify token para colar no meu app, so that a Meta me entregue os eventos
12. As a Empresa com App próprio, I want to ver quando o webhook foi verificado pela Meta, so that eu saiba que a configuração deu certo
13. As a Empresa com App próprio, I want to testar a conexão, so that eu descubra credenciais erradas antes de um Contato escrever
14. As a Empresa, I want to que a opção App próprio seja secundária na tela, so that o caminho simples seja o óbvio

### Desconexão

15. As a Empresa, I want to desconectar o WhatsApp da plataforma sem que meu número pare de funcionar na Meta, so that eu possa voltar a usá-lo em outro lugar
16. As a Empresa, I want to que meus Contatos e Mensagens continuem na plataforma depois de desconectar, so that eu não perca o histórico
17. As a Empresa, I want to que a plataforma pare de receber meus eventos ao desconectar, so that nada continue chegando sem Canal

### Recebimento

18. As a Empresa, I want to que todas as mensagens de um mesmo webhook sejam registradas, so that nenhum Contato fique sem resposta
19. As a Empresa, I want to que a mesma mensagem entregue duas vezes pela Meta seja registrada uma vez só, so that o Agente não responda em dobro
20. As a Empresa, I want to ver na conversa as imagens que o Contato envia, so that eu entenda o que ele mostrou
21. As a Empresa, I want to ouvir na conversa os áudios que o Contato envia, so that eu confira o que a transcrição entendeu
22. As a Empresa, I want to abrir na conversa os documentos que o Contato envia, so that eu veja o arquivo original
23. As a Empresa, I want to assistir na conversa aos vídeos que o Contato envia, so that eu não perca o conteúdo que o Agente não entende
24. As a Contato, I want to receber uma resposta dizendo que vídeos não são compreendidos, so that eu saiba que preciso explicar de outro jeito
25. As a Empresa, I want to ver o emoji com que o Contato reagiu sobre a mensagem reagida, so that eu perceba a reação sem poluir a conversa
26. As a Empresa, I want to que uma Reação não acione o Agente, so that ele não responda a um 👍
27. As a Empresa, I want to que trocar ou remover a Reação atualize o que aparece, so that a conversa mostre o estado atual
28. As a Contato, I want to que tocar num botão de resposta rápida de um Template seja entendido como se eu tivesse escrito o texto do botão, so that o atendimento continue
29. As a Empresa, I want to que o código do botão clicado fique registrado, so that um Fluxo possa decidir pelo código mesmo que eu mude o texto do botão
30. As a Empresa, I want to ver "Tipo de mensagem não suportado" quando o Contato mandar sticker, localização, cartão de contato ou algo desconhecido, so that eu saiba que ele mandou algo e possa responder manualmente
31. As a Empresa, I want to que mensagens não suportadas não acionem o Agente, so that ele não responda ao que não entende
32. As a Empresa, I want to que a mídia recebida continue disponível depois de 30 dias, so that o histórico não perca arquivos quando a Meta os apagar

### Status de entrega e falhas

33. As a Empresa, I want to ver se cada mensagem enviada foi enviada, entregue ou lida, so that eu acompanhe o atendimento
34. As a Empresa, I want to que o status não volte atrás quando a Meta entregar os eventos fora de ordem, so that "lida" nunca vire "entregue"
35. As a Empresa, I want to ver na bolha, em português, por que um envio falhou, so that eu saiba o que fazer (por exemplo, enviar um Template porque a janela fechou)
36. As a Empresa, I want to que um Contato que bloqueou marketing tenha o consentimento de marketing revogado automaticamente, so that eu não insista e prejudique meu número

### Envio de mídia pela Equipe

37. As a pessoa da Equipe, I want to enviar uma imagem na conversa, so that eu mostre algo ao Contato
38. As a pessoa da Equipe, I want to enviar um documento na conversa, so that eu mande um orçamento ou um pedido de exame
39. As a pessoa da Equipe, I want to enviar um vídeo na conversa, so that eu mostre como algo funciona
40. As a pessoa da Equipe, I want to gravar um áudio no navegador e enviá-lo, so that o Contato receba uma mensagem de voz como no próprio WhatsApp
41. As a pessoa da Equipe, I want to que a gravação funcione em qualquer navegador moderno, so that eu não dependa do Safari ou do Chrome
42. As a pessoa da Equipe, I want to ser avisada em português, antes do envio, quando o arquivo não tem formato aceito ou é grande demais, so that eu não espere uma falha
43. As a pessoa da Equipe, I want to ver a mídia que enviei na conversa, com o status de entrega, so that eu saiba que chegou
44. As a pessoa da Equipe, I want to ver na bolha quando a Meta recusar a mídia depois do envio (ex.: vídeo com codec inválido), so that eu entenda que o Contato não recebeu

### Templates

45. As a Empresa, I want to criar um Template com cabeçalho de imagem ou documento, so that eu mande "seu exame ficou pronto" com o arquivo
46. As a Empresa, I want to enviar o arquivo de exemplo do cabeçalho ao criar o Template, so that a Meta consiga avaliá-lo
47. As a Empresa, I want to escolher o arquivo do cabeçalho na hora de enviar o Template pela conversa, so that cada Contato receba o seu
48. As a Empresa, I want to criar botões de resposta rápida, URL, telefone e copiar código, so that o Contato aja direto da mensagem
49. As a Empresa, I want to informar exemplos para cada variável, so that a Meta aprove o Template
50. As a Empresa, I want to que a aprovação, a rejeição, a pausa e a desativação de um Template apareçam sozinhas, so that eu não precise sincronizar à mão
51. As a Empresa, I want to saber quando a Meta muda a categoria ou a qualidade de um Template, so that eu não seja surpreendida pelo preço ou por uma pausa
52. As a Empresa, I want to continuar podendo sincronizar à mão, so that eu force a atualização se algo parecer errado

### Saúde da conta e do número

53. As a Empresa, I want to que o Canal apareça com erro quando minha Conta do WhatsApp for bloqueada ou restrita, so that eu saiba que o atendimento parou
54. As a Empresa, I want to que o Canal apareça com erro quando eu remover a plataforma como parceira na Meta, so that o estado da plataforma reflita o que fiz lá
55. As a Empresa, I want to que o Canal apareça com erro quando o token deixar de valer, so that eu saiba que preciso reconectar
56. As a Empresa, I want to ver a qualidade e o limite de mensagens atualizados quando a Meta os mudar, so that eu me antecipe a uma restrição
57. As a Empresa, I want to ver o nome exibido atualizado quando a Meta aprovar ou recusar uma mudança de nome, so that eu saiba como apareço para o Contato

### Operação da plataforma

58. As a pessoa que opera a plataforma, I want to configurar o App da Meta da plataforma por variáveis de ambiente, so that o mesmo código rode em cada ambiente
59. As a pessoa que opera a plataforma, I want to que webhooks com assinatura inválida sejam recusados, so that ninguém forje eventos
60. As a pessoa que opera a plataforma, I want to que eventos de uma WABA desconhecida recebam 200 e sejam descartados, so that a Meta não desative o webhook do app
61. As a pessoa que desenvolve, I want to que o WhatsApp de testes se comporte como o oficial em tudo o que o Evolution Go permite, so that eu depure localmente o mesmo comportamento de produção

## Implementation Decisions

### Vocabulário (CONTEXT.md)

- **Conta do WhatsApp** é a WABA: dona dos Números e dos Templates.
- **App da Meta** é o app que chama a API e assina os webhooks: o da plataforma no Tech Provider, o da Empresa no App próprio.
- **Forma de conexão**: Tech Provider ou App próprio.
- **Reação** pertence à Mensagem reagida: não é Mensagem nova e não aciona o Agente.

### Esquema

- `whatsapp_accounts` (Conta do WhatsApp): pertence à Empresa (`team_id`). Tem `connection_method` (`tech_provider` | `own_app`, substitui `method` e remove o caso `manual`), `waba_id` único, `business_id`, `name`, `access_token` criptografado (business token no Tech Provider, token de system user no App próprio), `app_id` e `app_secret` criptografado (só App próprio), `verify_token` único e nulo fora do App próprio, `webhook_verified_at` (só App próprio), `subscribed_at` (Tech Provider), `ban_state`, `review_status` e `last_tested_at`. A coluna `whatsapp_phone_number_id` sai.
- `whatsapp_phone_numbers` (Número do WhatsApp): passa a apontar para a Conta (`whatsapp_account_id`, uma Conta tem vários Números). Mantém `channel_id` único e `phone_number_id` único. Ganha `name_status`, `messaging_limit_tier`, `registration_pin` criptografado e `registered_at`, além de `display_phone_number`, `verified_name` e `quality_rating`.
- `channels` não muda. `unique(team_id, type)` continua: um número oficial por Empresa. Se a WABA tiver vários números, a Empresa conecta um.
- Templates normalizados, sem colunas JSON:
  - `whatsapp_templates`: `whatsapp_account_id`, `meta_template_id`, `name`, `language`, `status`, `requested_category`, `assigned_category`, `quality_rating`, `rejection_reason`, `rejection_explanation`, `submitted_at`, `last_synced_at`. Saem `body`, `header_type`, `header_text`, `footer`, `buttons` e `variables`.
  - `whatsapp_template_components`: `whatsapp_template_id`, `type` (header | body | footer | buttons), `format` (text | image | video | document, só no header), `text` (com `{{n}}`), `media_path` e `media_handle` (exemplo do header de mídia) e `position`.
  - `whatsapp_template_variables`: `whatsapp_template_component_id`, `position` e `example`. As variáveis são as do `text` do componente, e os exemplos das variáveis de botão de URL também ficam aqui.
  - `whatsapp_template_buttons`: `whatsapp_template_component_id`, `type` (quick_reply | url | phone_number | copy_code), `text`, `url`, `phone_number`, `example` e `position`.
  - `whatsapp_template_histories` continua, sem a coluna `details`.
- `messages` ganha `button_payload`, `reaction_emoji`, `reacted_at` e `failure_code`. `failure_reason` e `media_*` já existem.
- A migração pode descartar dados: só existem contas de desenvolvimento. As contas `manual` viram `own_app` e o endereço antigo deixa de valer.

### Configuração da plataforma

- O App da Meta da plataforma vem do ambiente: `app_id`, `app_secret`, `config_id` do cadastro incorporado, verify token do webhook e versão da Graph API (esta já existe em `services.whatsapp`).

### Conexão pelo Tech Provider

- O popup do cadastro incorporado roda no navegador (SDK JS da Meta). O front repassa **só o `code`** ao backend. Toda chamada à Graph API acontece no servidor.
- O backend **não confia** em `waba_id`/`phone_number_id` vindos do front. Ele os descobre pelo token (`debug_token` → `granular_scopes`).
- Sequência: trocar o `code` pelo business token → `POST /{waba}/subscribed_apps` → `POST /{phone}/register` com PIN de 6 dígitos gerado e criptografado → buscar os dados do número → sincronizar Templates → Canal `Connected`.
- Cada passo concluído fica registrado na Conta ou no Número (`subscribed_at`, `registered_at`), para que "tentar de novo" retome do passo que falhou. Se houver falha, o Canal vai para `Error` com `last_error` em português.
- Se o registro falhar porque o número já tem PIN, a tela pede o PIN da Empresa e tenta de novo com ele.

### Conexão por App próprio

- O formulário atual continua (WABA ID, Phone Number ID, token, app secret), agora com o App ID. A Conta gera o `verify_token`. A tela mostra `/webhooks/whatsapp/app-proprio`.

### Desconexão

- Tech Provider: `DELETE /{waba}/subscribed_apps` e apagar token e PIN. **Nunca** desregistrar o número nem alterar outra coisa na conta da Meta da Empresa.
- App próprio: apagar as credenciais.
- Contatos e Mensagens permanecem.

### Webhook (ADR 0012)

- `GET/POST /webhooks/whatsapp`: Tech Provider. O handshake usa o verify token da plataforma e a assinatura `X-Hub-Signature-256` é validada com o secret da plataforma.
- `GET/POST /webhooks/whatsapp/app-proprio`: App próprio. O handshake busca a Conta pelo `verify_token` e grava `webhook_verified_at`. O POST acha a Conta por `entry[].id` (WABA ID) e valida com o `app_secret` dela.
- Nada que identifique ou autentique a Conta vai na URL.
- Com a assinatura válida, a resposta é **200 sempre**, mesmo com WABA ou campo desconhecido. O POST vai para a fila sem ser guardado em tabela (risco aceito no ADR 0012).
- O job percorre **todos** os `entry → changes`. Em cada `change`, despacha pelo `field`, e dentro de `messages` despacha cada item de `messages[]` e de `statuses[]` separadamente. A unicidade do job e a idempotência são feitas por item (`provider_message_id`), não pelo POST.

### Strategies da família Meta (ADR 0004)

- Por tipo de `messages[]`:
  - `text`: Mensagem, aciona o Agente.
  - `image`: mídia guardada, descrita pela IA para o Agente, aciona o Agente.
  - `audio` (incluindo voz): mídia guardada, transcrita para o Agente, aciona o Agente.
  - `document`: mídia guardada, texto extraído para o Agente, aciona o Agente.
  - `video`: mídia guardada e exibida. O Agente recebe só que o Contato enviou um vídeo e responde que não entende vídeos.
  - `button` (clique em resposta rápida de Template) e `interactive` do tipo `button_reply`: o texto do botão vai para `content`, o código para `button_payload`, e aciona o Agente.
  - `reaction`: grava `reaction_emoji`/`reacted_at` na Mensagem reagida (achada por `provider_message_id` no mesmo Canal). Emoji vazio limpa. Não cria Mensagem e não aciona o Agente. Reação a uma Mensagem desconhecida é descartada.
  - sticker, location, contacts e qualquer outro: `Unsupported`. A Mensagem é registrada com o tipo bruto e exibida como "Tipo de mensagem não suportado", sem acionar o Agente e sem processamento específico.
- `statuses[]`: o status só avança (sent < delivered < read). `failed` sobrescreve qualquer status, menos `read`. Em `failed`, grava `failure_code` e `failure_reason` com a tradução do código da Meta (131047 → janela de 24h fechada; 131026 → número não pode receber; 131053 → mídia recusada; 131050 → Contato bloqueou marketing; e um texto genérico para os demais). O 131050 também revoga o consentimento de marketing do Contato. `pricing` é ignorado.
- `account_update`: ban, restrição ou `PARTNER_REMOVED` gravam `ban_state`/`review_status` e levam o Canal a `Error` com `last_error` em português. `account_review_update` grava `review_status`.
- `phone_number_quality_update`: atualiza `quality_rating` e `messaging_limit_tier`.
- `phone_number_name_update`: atualiza `verified_name` e `name_status`.
- `message_template_status_update`, `template_category_update` e `message_template_quality_update`: atualizam o Template e registram o histórico, reaproveitando o serviço de Templates atual.
- A resposta 190 (token inválido) em qualquer chamada à Graph API leva o Canal a `Error`.
- `history`, `smb_message_echoes` e `smb_app_state_sync` não são assinados (coexistência fora do escopo).

### Família Evolution Go (WhatsApp de testes)

- Implementa o que o provedor oferece: texto, imagem, áudio, documento, vídeo, Reação, status de entrega e envio de imagem, documento, áudio e vídeo.
- Não tem Templates, clique em botão de Template nem eventos de conta, número e Template. Essa exceção está registrada no ADR 0004.

### Envio de mídia

- Os adapters de saída (oficial e testes) ganham envio de imagem, documento, áudio e vídeo, ao lado do envio de texto e de Template.
- Na oficial, o arquivo é salvo primeiro no disco privado `whatsapp_media`, depois enviado para `POST /{phone}/media` e em seguida para `/messages` com o id da mídia. A Mensagem de saída guarda os `media_*` locais.
- Limites e formatos aceitos:

  | tipo | formatos | máximo |
  |---|---|---|
  | imagem | jpeg, png | 5 MB |
  | documento | pdf, doc(x), xls(x), ppt(x), txt | 100 MB |
  | áudio | ogg/opus, mp3, aac, mp4 | 16 MB |
  | vídeo | mp4, 3gp | 16 MB |

  A validação acontece no front e no Form Request, com mensagens em português bem escritas.
- O áudio é gravado no navegador com `opus-recorder` (dependência npm aprovada), que gera `ogg/opus`, e chega ao Contato como mensagem de voz. Não há conversão no servidor nem `ffmpeg`.
- Nesta fase, só a Equipe usa o envio de mídia. O Agente continua enviando só texto, e a capacidade dos adapters fica pronta para uma futura Ação de envio de arquivo.
- As regras atuais da janela de 24h e da Pausa da IA continuam valendo para o envio de mídia.

### Templates

- Sincronizados ao conectar, pelos webhooks e pelo botão manual.
- A sincronização e a submissão leem e escrevem a estrutura normalizada (componentes, variáveis e botões).
- Criar um Template com cabeçalho de imagem ou documento envia o arquivo de exemplo pela Resumable Upload API (usando o `app_id` da Forma de conexão) e guarda o `media_handle` e a cópia local no componente de header.
- Na conversa, enviar um Template com header de mídia exige o upload do arquivo naquele envio. O arquivo segue o mesmo fluxo de `/media` do envio de mídia.

### Retenção

- A mídia recebida e a enviada ficam guardadas indefinidamente. Em produção, o disco `whatsapp_media` deve apontar para um storage privado compatível com S3; isso é só configuração.

## Testing Decisions

- Bons testes exercitam o comportamento externo por um único seam: **as rotas HTTP da plataforma**, com a Meta simulada por `Http::fake`. O teste confere o que ficou no banco e quais chamadas foram feitas à Graph API, nunca detalhes internos de strategy ou de job.
- **Webhook**: amplia `WhatsAppWebhookConformanceTest` com fixtures de payload real em `tests/Fixtures/whatsapp/oficial` e `tests/Fixtures/whatsapp/testes`:
  - Parametrizados nas duas famílias: texto, imagem, áudio, documento, vídeo, Reação (criar, trocar, remover), status fora de ordem, `failed` com código traduzido e tipo não suportado.
  - Só na oficial: POST com vários entries, changes e mensagens (todos registrados), mesma mensagem entregue duas vezes (registrada uma vez), clique em botão (`content` + `button_payload`), eventos de conta, número e Template, e 131050 revogando o consentimento.
- **Rotas do webhook** (`WhatsAppWebhookTest` como referência): handshake nas duas rotas, assinatura válida e inválida em cada uma, secret da plataforma na rota do Tech Provider e secret da Conta na rota de App próprio, 200 para WABA desconhecida com assinatura válida, e nenhum vazamento entre as duas rotas.
- **Conexão** (`WhatsAppTest` como referência): o `code` gera a sequência correta de chamadas à Graph API; `waba_id`/`phone_number_id` vêm do `debug_token`; uma falha em cada passo deixa o Canal em `Error` com mensagem traduzida; "tentar de novo" retoma do passo certo sem repetir os concluídos; PIN informado pela Empresa; App próprio salvo e testado; e a desconexão chama só `DELETE subscribed_apps`, nunca o desregistro.
- **Envio de mídia** (`InboxTest` e `WhatsAppOutputAdapterTest` como referência): upload válido de cada tipo gera `/media` + `/messages` e a Mensagem com `media_*`; arquivos fora de formato ou tamanho são recusados com a mensagem em português; a janela fechada continua bloqueando o envio livre.
- **Templates** (`WhatsAppTemplateTest` como referência): a sincronização popula componentes, variáveis e botões; a submissão monta o payload a partir deles; o header de mídia usa a Resumable Upload API; o envio na conversa com header de mídia exige o arquivo.
- O popup da Meta e a gravação com `opus-recorder` são validados manualmente.

## Out of Scope

- Coexistência com o app WhatsApp Business (`history`, `smb_message_echoes`, `smb_app_state_sync`).
- Preço por mensagem e painel de custos (`pricing` dos status).
- Notificações in-app ou por e-mail sobre eventos da conta, do número e dos Templates. Nesta entrega só o estado é gravado.
- Ação do Agente para enviar imagem ou documento.
- Mídia no header do nó Enviar Template do Fluxo (volta para discussão).
- Vários números oficiais por Empresa.
- Processamento específico de sticker, localização e cartão de contato.
- Envio de Reação ou de resposta citando uma mensagem pela Equipe.
- Armazenamento do webhook bruto e reprocessamento.

## Further Notes

- A plataforma ainda não está cadastrada na Meta como Tech Provider (verificação do Business, App Review de `whatsapp_business_management` e `whatsapp_business_messaging`, configuração do cadastro incorporado). Até a aprovação, tudo pode ser desenvolvido e testado pela Forma de conexão App próprio, e o Tech Provider pode ser testado pelo modo de desenvolvimento do App da plataforma com contas de teste.
- Os specs `atendimento-no-whatsapp` e `whatsapp-ativo` descrevem partes já entregues. Este spec as estende e, onde houver conflito (estrutura dos Templates, endereço do webhook, relação Conta–Número), prevalece.
