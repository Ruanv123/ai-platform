# Spec: Loja Shopify — catálogo e Link de compra pelo Agente

Status: ready-for-agent

Depends on: `.scratch/agenda-google/spec.md` (padrão de Integração por OAuth, Tools do Agente e estado de Integração)

ADRs: `docs/adr/0013-loja-espelhada-em-modelo-canonico-com-adapter-por-provedor.md`

## Problem Statement

A tela de Integrações lista Shopify, Nuvemshop, VTEX e outros como Provedores de e-commerce, mas nenhum conecta. Uma Empresa que vende online e usa um Agente com Objetivo de Vendas não consegue fazer o Agente vender:

- ele não sabe quais produtos a Loja tem, quanto custam nem se há estoque;
- ele não consegue mandar ao Contato um jeito de comprar.

Hoje a Empresa contorna isso subindo o catálogo como Documento numa Coleção. O Trecho envelhece na primeira mudança de preço ou estoque, e não dá para filtrar por "até R$ 200, tamanho M". O Agente recomenda produto esgotado com preço antigo, e ninguém fica sabendo se alguma venda saiu do atendimento.

## Solution

A Empresa instala o app da plataforma na sua loja Shopify, pelo admin da Shopify ou pela tela de Integrações, e liga a loja à Empresa. A partir daí:

- **Espelho:** a plataforma mantém uma cópia da Loja num modelo canônico (Produtos, Variantes, Categorias, Clientes da loja e Pedidos), sincronizada em segundo plano por importação inicial, webhooks e reconciliação diária. A Empresa acompanha o estado da Loja.
- **Buscar produtos:** o Agente ganha essa Ação. Ela faz uma busca híbrida no catálogo e, em seguida, uma triagem por um subagente, que devolve os poucos produtos com mais chance de compra para aquele Contato. A triagem usa a conversa, os Pedidos do Cliente da loja e a Memória do agente.
- **Mandar Link de compra:** outra Ação do Agente. Gera um link que abre o checkout da Shopify com as Variantes escolhidas. Quando o Contato compra por ele, o Pedido volta com Atribuição ao Contato e ao Agente.
- **Ficha do Contato:** o Cliente da loja ligado ao Contato aparece na ficha dele no atendimento.

Tudo o que o Agente enxerga é o canônico. A Shopify é o primeiro e, por enquanto, o único adapter, mas o motor é desenhado para receber outros Provedores de e-commerce sem mudar o que o Agente enxerga.

## User Stories

### Instalação e vínculo

1. As a lojista na Shopify, I want to instalar o app da plataforma pelo admin da Shopify, so that eu conecte minha loja sem sair de onde já trabalho.
2. As a Owner ou Admin da Empresa, I want to conectar a Loja a partir da tela de Integrações, so that eu não precise procurar o app na Shopify.
3. As a lojista que instalou pela Shopify sem ter conta na plataforma, I want to ser levado a criar a conta e depois voltar à ligação da Loja, so that a instalação não se perca no meio do cadastro.
4. As a pessoa com acesso a várias Empresas, I want to escolher a qual Empresa a Loja pertence, so that o catálogo não vá parar na Empresa errada.
5. As a Member da Empresa, I want to ser impedido de ligar uma Loja, so that só quem administra a Empresa decida sobre integrações.
6. As a Owner de uma Empresa que já tem uma Loja, I want to ser avisado de que ligar outra loja desconecta a atual e apaga o espelho dela, so that eu não troque de loja por engano.
7. As a lojista, I want to ver dentro do admin da Shopify se a loja está ligada e sincronizada, com um atalho para a plataforma, so that eu saiba que o app está funcionando.
8. As a lojista, I want to que o app peça só permissão de leitura, so that eu confie em instalar sem medo de alterarem minha loja.
9. As a Empresa, I want to que um link de instalação antigo ou reaproveitado seja recusado, so that ninguém ligue a minha loja a outra Empresa.

### Sincronização e estado da Loja

10. As a Empresa, I want to ver o progresso da importação inicial, so that eu saiba quando o Agente já pode recomendar.
11. As a Empresa, I want to ver quando a Loja foi sincronizada pela última vez, so that eu confie que preço e estoque estão atuais.
12. As a Empresa, I want to que um produto alterado na Shopify reflita na plataforma em poucos minutos, so that o Agente não ofereça preço antigo.
13. As a Empresa, I want to que um produto apagado ou despublicado na Shopify deixe de ser oferecido, so that o Contato não receba link de algo que não está à venda.
14. As a Empresa, I want to que mudança de estoque em qualquer local da Shopify atualize o estoque da Variante, so that "tem no M?" tenha a resposta certa.
15. As a Empresa, I want to que alterações perdidas por instabilidade sejam recuperadas sozinhas, so that o espelho não fique errado para sempre por um webhook que não chegou.
16. As a Empresa, I want to que as inscrições de webhook removidas pela Shopify sejam recriadas sozinhas, so that a sincronização não pare em silêncio.
17. As a Empresa, I want to ver a Loja com erro, o motivo e o que fazer, so that eu resolva antes que o catálogo envelheça.
18. As a Owner ou Admin, I want to receber um aviso quando a Loja entrar em erro, so that eu não dependa de abrir a tela para descobrir.
19. As a Empresa, I want to que o Agente continue recomendando com a última cópia quando a Loja está com erro, so that uma instabilidade da Shopify não derrube as vendas.
20. As a Empresa, I want to reconectar a Loja quando o acesso é revogado ou expira, so that a sincronização volte sem reinstalar do zero.
21. As a Empresa, I want to que mudanças em massa na Shopify, como um reajuste de preços, não gerem uma avalanche de processamento repetido, so that a sincronização continue rápida.

### Clientes da loja e Pedidos

22. As a Empresa, I want to que os clientes da Shopify sejam ligados aos Contatos pelo telefone, so that o Agente saiba quem é quem.
23. As a Empresa, I want to que um Contato que chega depois da importação seja ligado ao Cliente da loja com o mesmo telefone, so that a ligação não dependa da ordem em que as coisas aconteceram.
24. As a Empresa, I want to que telefones em qualquer formato, como `(11) 99999-8888`, sejam normalizados usando o país da Loja, so that a ligação com o Contato funcione.
25. As a Empresa, I want to que clientes sem telefone sejam espelhados mesmo assim, so that o histórico de compras fique completo.
26. As a Equipe no atendimento, I want to ver na ficha do Contato o Cliente da loja ligado a ele, com nome, e-mail, total gasto e últimos Pedidos, so that eu atenda sabendo o que a pessoa já comprou.
27. As a Equipe, I want to que o nome do Contato não seja sobrescrito pelo nome da Shopify, so that a ficha não mude sozinha.
28. As a Empresa, I want to que os Pedidos dos últimos 60 dias e todos os seguintes sejam espelhados, so that a recomendação conheça o que o Cliente da loja já levou.

### Buscar produtos

29. As a Contato, I want to descrever o que procuro em linguagem natural, como "um tênis de corrida leve até 400 reais", so that eu receba sugestões sem navegar pela loja.
30. As a Contato, I want to receber poucas sugestões, com preço e disponibilidade, so that eu decida rápido pelo WhatsApp.
31. As a Contato, I want to que as sugestões levem em conta o que eu já disse na conversa, so that eu não precise repetir meu tamanho ou minha preferência.
32. As a Contato que já comprou na loja, I want to que as sugestões considerem o que eu já comprei, so that eu não receba de novo o mesmo produto que acabei de levar.
33. As a Contato, I want to que as sugestões considerem o que o Agente lembra de mim de conversas anteriores, so that eu seja tratado como alguém conhecido.
34. As a Contato, I want to saber quando uma Variante está esgotada, so that eu não peça algo indisponível.
35. As a Empresa, I want to que o Agente só ofereça Produtos ativos e publicados na loja online, so that rascunhos e produtos arquivados nunca apareçam.
36. As a Empresa, I want to que Variantes esgotadas só sejam oferecidas se a Shopify permitir vender sem estoque, so that o Agente respeite a política da loja.
37. As a Empresa, I want to que o Agente mostre o preço promocional com o preço comparativo, so that o desconto seja percebido.
38. As a Empresa, I want to que a busca entenda Categorias e opções como cor e tamanho, so that "vestido azul M" encontre a Variante certa.
39. As a Empresa, I want to ver no Traço o custo da triagem de produtos, so that eu saiba quanto a recomendação custa em IA.
40. As a Empresa, I want to que a triagem só rode quando há mais candidatos do que o Agente pode oferecer, so that eu não pague por uma triagem inútil.

### Link de compra e Atribuição

41. As a Contato, I want to receber um link que já abre o checkout com o que escolhi, so that eu compre em poucos toques.
42. As a Contato que já é Cliente da loja, I want to que o checkout venha com meu e-mail preenchido, so that eu digite menos.
43. As a Contato, I want to comprar mais de uma Variante e em quantidades diferentes pelo mesmo link, so that eu feche o pedido inteiro de uma vez.
44. As a Empresa, I want to que o Link de compra não crie nada na minha loja até o Contato comprar, so that meu admin não se encha de carrinhos abandonados.
45. As a Empresa, I want to que o Agente só gere link de Variante disponível e de Produto visível, so that o Contato não caia numa página de erro.
46. As a Empresa, I want to ver quais Pedidos vieram de um Link de compra, com o Contato e o Agente que o geraram, so that eu saiba quanto o Agente vendeu.
47. As a Empresa, I want to ver essas vendas também nos relatórios da Shopify, por origem, so that eu confira o resultado sem depender da plataforma.
48. As a Empresa, I want to que só conte como venda do Agente o Pedido feito pelo Link de compra, so that o número seja incontestável.
49. As a Empresa, I want to que um identificador interno não fique legível nem editável no link, so that ninguém forje a Atribuição trocando um id.

### Agente e Playground

50. As a Empresa, I want to ligar ou desligar as Ações "Buscar produtos" e "Mandar Link de compra" por Agente, so that só o Agente de vendas ofereça produtos.
51. As a Empresa, I want to que essas Ações venham ligadas por padrão em Agentes com Objetivo Vendas quando há Loja conectada, so that eu não precise descobrir onde ligar.
52. As a Empresa, I want to que ligar ou desligar essas Ações passe pelo Rascunho do agente, so that a mudança só valha nos Canais depois de publicar.
53. As a Empresa sem Loja conectada, I want to que as Ações da Loja não apareçam, so that eu não ligue algo que não funciona.
54. As a Empresa, I want to testar a recomendação no Playground com o catálogo real, so that eu veja exatamente o que o Contato veria.
55. As a Empresa, I want to que um Link de compra gerado no Playground não tenha Atribuição, so that meus testes não inflem as vendas do Agente.

### Desconectar e privacidade

56. As a Owner ou Admin, I want to desconectar a Loja pela plataforma, so that eu encerre a integração quando quiser.
57. As a lojista, I want to que desinstalar o app na Shopify tenha o mesmo efeito de desconectar, so that não sobrem dados da minha loja na plataforma.
58. As a Empresa, I want to que desconectar apague Produtos, Variantes, Categorias, Clientes da loja e Pedidos, e desligue as Ações da Loja nos Agentes, so that nada fique pela metade.
59. As a Empresa, I want to que desconectar mantenha meus Contatos, Mensagens e Memórias do agente, so that o atendimento não perca histórico.
60. As a cliente da loja, I want to que um pedido de apagamento pela Shopify apague meus dados vindos da loja, so that meu direito pela LGPD seja cumprido.
61. As a Owner da Empresa, I want to receber os dados que a plataforma tem de um cliente quando a Shopify pede, so that eu atenda o pedido de acesso do titular.
62. As a lojista, I want to que a plataforma apague todos os dados da loja quando a Shopify pedir, depois da desinstalação, so that o app cumpra as regras da App Store.

## Implementation Decisions

### Modelo canônico (ADR 0013)

- **Loja**: 1:1 com uma Integração de tipo e-commerce, no máximo uma por Empresa. Guarda:
  - domínio da loja no provedor, nome, moeda, país e fuso;
  - estado de sincronização: `importing` (com progresso), `synced` (com `last_synced_at`) ou `error` (com motivo e ação sugerida);
  - marcador de reconciliação (até onde já reconciliou por `updated_at`).
- **Credencial**: fica criptografada na Integração, como hoje. Inclui o token de acesso **que expira** e o refresh token; a Shopify vai obrigar apps públicos a usá-los em 2027, e eles já entram assim. Refresh que falha deixa a Loja em `error` até reconectar, igual à Agenda.
- **Produto**: id externo, título, descrição em texto limpo, handle/URL, status no provedor, se está publicado na loja online, Categorias e embedding.
- **Variante**: id externo, Produto, SKU, título, opções (pares nome/valor, até 3), preço, preço comparativo, estoque somado de todos os locais, se vende sem estoque e se está disponível.
- **Categoria**: id externo e título, ligada a Produtos N:N. Corresponde às collections da Shopify; nunca se chama Coleção.
- **Cliente da loja**: id externo, nome, e-mail, telefone em E.164 (nulo quando não há) e ligação com no máximo um Contato.
- **Pedido**: id externo, número exibido, Cliente da loja (nulo para convidado sem cadastro), total, moeda, status financeiro e de entrega, data e Atribuição (nula quando não veio de Link de compra).
- **Item do pedido**: Variante (nula se ela já não existe mais), título, quantidade e preço.
- **Sem payload bruto** em nenhuma tabela: só o id externo e as colunas canônicas. Tudo tem `team_id` e unicidade por (Loja, id externo).
- **Ligação Cliente da loja ↔ Contato:**
  - acontece nos dois sentidos: ao espelhar um Cliente da loja e ao criar um Contato;
  - usa o telefone normalizado para E.164, com o país da Loja como padrão (a mesma normalização do commit de telefones em E.164);
  - quando o telefone muda na Shopify, a ligação é refeita.
- **Nada é copiado para o Contato.** A ficha do Contato no atendimento lê o Cliente da loja ligado.

### Motor de integração de e-commerce

- **Contrato de adapter por Provedor de e-commerce**, com as operações:
  - conectar (OAuth e refresh);
  - registrar e conferir inscrições de webhook;
  - iniciar e ler a importação completa;
  - buscar um recurso por id externo;
  - listar alterações desde um instante;
  - validar a assinatura do webhook;
  - identificar a Loja a partir do webhook;
  - traduzir para o canônico;
  - montar o Link de compra.
- **Só o adapter conhece o provedor.** O resto do motor (jobs de sincronização, upsert canônico, estado da Loja, ligação com o Contato) é único.
- **O webhook é um aviso** (ADR 0013). O job recebido carrega apenas (Loja, tipo de recurso, id externo, apagado ou não), busca o estado atual na API e faz upsert, ou apaga do espelho quando o recurso sumiu. Ordem de chegada, duplicatas e formato do payload não importam.
- **Debounce por recurso:** avisos repetidos do mesmo recurso numa janela curta viram uma única busca, com um job único por (Loja, tipo, id).
- **Importação inicial** pelas Bulk Operations da Shopify (GraphQL), consumindo o JSONL por páginas em jobs. A conclusão chega pelo webhook `bulk_operations/finish`. Pedidos ficam limitados aos 60 dias que o escopo padrão permite.
- **Reconciliação diária**, por comando agendado:
  - busca incremental por `updated_at` a partir do marcador;
  - recria inscrições de webhook ausentes;
  - atualiza `last_synced_at`.
- **Rate limit:** respeitar o custo de pontos da GraphQL Admin API. Um 429 ou throttle reenfileira com backoff; nunca vira erro da Loja.
- **Embeddings:** gerados de título, descrição, Categorias e opções. O Produto só é reprocessado quando esse texto muda, usando um hash do texto de origem guardado no Produto.

### Recebimento de webhooks

- **Um endpoint por provedor** para webhooks de Integração. Hoje só existe o da Shopify, fora do grupo autenticado e com throttle próprio, seguindo a linha do ADR 0012.
- **Tratamento no controller:**
  - valida `X-Shopify-Hmac-Sha256` sobre o corpo bruto, com o client secret do app da plataforma;
  - encontra a Loja pelo domínio no header `X-Shopify-Shop-Domain`;
  - responde 200 e enfileira;
  - assinatura inválida recebe 401;
  - loja desconhecida recebe 200 e é descartada.
- **Nada é armazenado.** A idempotência vem do upsert por id externo.
- **Tópicos tratados:**
  - produtos: criado, atualizado, apagado;
  - estoque: `inventory_levels/update`, que é traduzido para a Variante do item de estoque;
  - collections: criada, atualizada, apagada;
  - clientes: criado, atualizado, apagado;
  - pedidos: criado, atualizado, apagado;
  - `bulk_operations/finish`;
  - `app/uninstalled`;
  - compliance: `customers/data_request`, `customers/redact`, `shop/redact`.

### App Shopify

- **App público** construído desde já para passar na revisão da App Store. Unlisted e listado passam pela mesma revisão, então a visibilidade é escolhida só na hora de publicar.
- **Requisitos:**
  - embedded com App Bridge e session token, sem cookies de terceiros;
  - só GraphQL Admin API;
  - OAuth antes de qualquer tela;
  - webhooks de compliance desde o dia 1.
- **Escopos:** `read_products`, `read_inventory`, `read_customers` e `read_orders`, mais acesso a **dados protegidos de clientes nível 2** (nome, e-mail e telefone). Sem `read_all_orders` e sem nenhum escopo de escrita.
- **Instalação iniciada pela Shopify:**
  1. OAuth.
  2. O app embedded mostra "conectar a uma Empresa" e abre a plataforma em nova aba, com um **token de instalação** de uso único e curta duração, ligado à instalação pendente.
  3. Login ou cadastro.
  4. Escolha da Empresa, só entre as Empresas em que a pessoa é Owner ou Admin.
  5. Confirmação. Se a Empresa já tem Loja, a tela avisa que a atual será desconectada e seu espelho apagado.
- **Instalação iniciada pela plataforma:** o mesmo OAuth, partindo da tela de Integrações com a Empresa já definida.
- **Depois da ligação**, a tela embedded mostra só o estado da Loja e um atalho para a plataforma.
- **Desconexão:**
  - pela plataforma, desinstala o app na loja via API;
  - pela Shopify, chega como `app/uninstalled`;
  - nos dois casos, o efeito é o mesmo (ver "Desconectar").

### Ações do Agente

- **"Buscar produtos"**, uma Tool do Agente, recebe a descrição em linguagem natural e filtros opcionais (preço máximo e mínimo, Categoria, opção, só disponíveis). Em ordem:
  1. Busca híbrida: similaridade de embedding mais filtros SQL, só sobre Produtos ativos e publicados na loja online. Traz cerca de 30 candidatos no nível da Variante.
  2. Se houver mais candidatos que o limite de oferta (3 a 5), um **subagente de triagem** com modelo barato os reordena pela chance de compra. O contexto dele é a conversa recente, os Pedidos do Cliente da loja ligado e a Memória do agente sobre o Contato. Cada escolha vem com um motivo curto.
  3. Devolve ao Agente, para cada Variante: título, opções, preço, preço comparativo, disponibilidade e URL do Produto.
- **Variantes esgotadas** só passam se a Variante vende sem estoque.
- **O custo do subagente** entra no Traço.
- **"Mandar Link de compra"**, uma Tool, recebe uma lista de pares (Variante, quantidade):
  - recusa Variante indisponível ou de Produto não visível, com uma falha legível para o Agente;
  - monta o permalink de carrinho da Shopify;
  - pré-preenche o e-mail quando há Cliente da loja ligado;
  - acrescenta UTMs fixas (`utm_source` da plataforma, `utm_medium=whatsapp`, `utm_campaign=agente-ia`);
  - acrescenta um **token de Atribuição opaco** nos atributos do carrinho.
- **Token de Atribuição:** registro próprio que liga token → (Contato, Agente, Canal, momento). Um Pedido espelhado que traz o token ganha a Atribuição. Token desconhecido é ignorado.
- **Só conta a venda direta:** o Pedido traz o token.
- **Playground:** "Buscar produtos" é real. O Link de compra é gerado de verdade, mas sem token de Atribuição.
- **Ligar as Ações:** o Agente ganha dois interruptores que fazem parte do Rascunho do agente e da Versão do agente.
  - Nascem ligados em Agentes com Objetivo Vendas quando a Empresa tem Loja.
  - As Tools só são entregues ao modelo quando o interruptor está ligado **e** a Empresa tem Loja conectada, esteja ela sincronizada ou com erro.

### Desconectar

- **O que acontece:**
  - hard delete de Produtos, Variantes, Categorias, Clientes da loja, Pedidos, Itens do pedido e tokens de Atribuição;
  - soft delete da Integração e da Loja;
  - revogação das credenciais;
  - interruptores da Loja desligados nos Agentes.
- **O que fica:** Contatos, Mensagens e Memórias do agente.
- **Ligar outra loja** executa esse mesmo desconectar antes.

### LGPD (webhooks de compliance)

- **`customers/redact`:** apaga o Cliente da loja, os Pedidos dele e a ligação com o Contato. O Contato, as Mensagens e a Memória do agente permanecem, porque vieram do WhatsApp.
- **`customers/data_request`:** gera um arquivo com tudo o que a plataforma tem daquele Cliente da loja (cadastro, Pedidos e Atribuições) e o envia por e-mail ao Owner da Empresa.
- **`shop/redact`:** garante que nada da Loja sobrou. É idempotente, porque normalmente o desconectar já apagou tudo.
- **Prazo:** todos os pedidos são executados em fila, bem dentro dos 30 dias que a Shopify exige.

### Telas

- **Integrações:**
  - o card da Shopify passa a conectar de verdade;
  - a página da Loja mostra estado, progresso da importação, última sincronização, erro com ação, contagem de Produtos, Clientes da loja e Pedidos, botão de reconectar e botão de desconectar;
  - os demais Provedores de e-commerce continuam no catálogo sem prometer capacidade.
- **Agente:** mostra os dois interruptores da Loja só quando há Loja.
- **Atendimento:** a ficha do Contato mostra o Cliente da loja ligado, com nome, e-mail, total gasto e últimos Pedidos, e os Pedidos atribuídos a ele.
- **Aviso à Equipe:** quando a Loja entra em `error`, os Owners e Admins são notificados.

## Testing Decisions

- **Um bom teste** exercita a feature por fora e verifica efeitos observáveis. "Por fora" quer dizer uma rota HTTP, um webhook assinado, uma conversa do Agente em que o modelo chama a Tool ou o comando agendado. Os efeitos são: resposta, linhas canônicas gravadas ou apagadas, requisições que chegaram à Shopify, estado da Loja e texto devolvido pela Tool. O teste não verifica classes internas, o adapter isolado nem o formato das chamadas entre módulos.
- **Tudo externo é fake:**
  - Shopify via `Http::fake`: GraphQL Admin API, endpoints de OAuth e token, e o download do JSONL de Bulk Operation;
  - modelo via `WhatsAppAgent::fake` e `PlaygroundAgent::fake` com `ToolCall`, e o subagente de triagem também faked;
  - embeddings via `Embeddings::fake`.
- **Fixtures reais da Shopify** (respostas GraphQL, JSONL de bulk e corpos de webhook) ficam num diretório de fixtures por provedor, como já acontece com o WhatsApp. É o início da **suíte de conformidade** do ADR 0013: as asserções sobre o canônico são escritas uma vez, parametrizadas por provedor, e hoje só a Shopify roda nelas.
- **Seam 1: webhook assinado da Shopify.** Cobre:
  - HMAC inválido responde 401;
  - loja desconhecida é descartada;
  - produto criado ou atualizado faz upsert do canônico a partir da busca na API, não do payload;
  - produto apagado ou despublicado sai da busca;
  - estoque de um local atualiza o total da Variante;
  - webhooks duplicados e fora de ordem terminam no mesmo estado;
  - Cliente da loja ligado ao Contato pelo telefone normalizado, e religado quando o telefone muda;
  - Cliente da loja sem telefone é espelhado sem ligação;
  - Pedido com token de Atribuição ganha a Atribuição, e com token desconhecido não;
  - `bulk_operations/finish` conclui a importação e deixa a Loja `synced`;
  - `app/uninstalled` apaga o espelho e mantém os Contatos;
  - `customers/redact`, `customers/data_request` (e-mail ao Owner) e `shop/redact`.
  - Prior art: `tests/Feature/Channels/WhatsAppWebhookTest.php` e `tests/Feature/Channels/WhatsAppWebhookConformanceTest.php`.
- **Seam 2: rotas HTTP da plataforma.** Cobre:
  - instalação pela plataforma (redirect com escopos e callback gravando a Integração e a Loja, que inicia a importação);
  - instalação pela Shopify com token de instalação (válido, expirado, reutilizado);
  - escolha de Empresa restrita a Owner e Admin;
  - troca de Loja com o aviso e o apagamento do espelho anterior;
  - reconectar reaproveitando a Integração;
  - desconectar;
  - permissão `ManageIntegrations`;
  - interruptores da Loja no Rascunho do agente;
  - ficha do Contato com o Cliente da loja.
  - Prior art: `tests/Feature/IntegrationManagementTest.php`, `tests/Feature/Integrations/IntegrationTest.php` e `tests/Feature/Agents/AgentTest.php`.
- **Seam 3: Tools pela conversa do Agente.** Cobre:
  - busca respeitando os filtros, só ativos e publicados, e esgotados só com venda sem estoque;
  - triagem chamada só acima do limite e recebendo os Pedidos e a Memória do agente;
  - Link de compra com Variantes e quantidades, e-mail pré-preenchido, UTMs e token;
  - Link recusado para Variante indisponível;
  - Playground sem token de Atribuição;
  - Agente sem Loja ou com interruptor desligado não recebe as Tools;
  - Loja com erro continua respondendo a partir do espelho.
  - Prior art: `tests/Feature/Scheduling/AgentSchedulingTest.php` e `tests/Feature/Knowledge/PlaygroundTest.php`.
- **Seam 4: comando de reconciliação.** Cobre:
  - busca incremental por `updated_at` que recupera alteração sem webhook;
  - inscrição de webhook ausente recriada;
  - refresh de token que falha deixa a Loja em `error` e avisa a Equipe;
  - throttle da Shopify reenfileira sem erro.
- **Não há teste unitário isolado** do adapter nem da tradução para o canônico: eles são cobertos pelas fixtures nos seams 1 e 4.

## Out of Scope

- **Outros Provedores de e-commerce** (Nuvemshop, VTEX, Loja Integrada, WooCommerce). O modelo foi pensado para eles, mas nenhum adapter entra agora.
- **Mercado Livre** fica fora do modelo de Loja, porque é marketplace e não loja.
- **Consultar andamento de Pedido pelo Agente** ("cadê meu pedido?") e a verificação de identidade que isso exigiria.
- **Mensagens proativas disparadas pela Loja** (pedido pago, enviado, carrinho abandonado) e **Gatilho de Fluxo por evento da Loja**.
- **Nós de Loja no Fluxo.** Um Fluxo que precisa vender entrega ao Agente.
- **Qualquer escrita na Shopify:** pedido rascunho, cupom, cancelamento, alteração de endereço.
- **Atribuição assistida** (compra sem o link depois de uma recomendação).
- **Painel de vendas do Agente.** A Atribuição é gravada, mas o painel vem depois.
- **Histórico de Pedidos com mais de 60 dias** (`read_all_orders`).
- **Estoque por local**, tabelas de preço por cliente e múltiplas moedas ou Markets da Shopify.
- **Várias Lojas por Empresa.**
- **Copiar dados do Cliente da loja para o Contato ou para Campos do Contato.**
- **Ligar o Cliente da loja ao Contato por e-mail** informado na conversa. Fica para depois.
- **Guardar webhooks ou payloads brutos.**

## Further Notes

- **Revisão da Shopify:**
  - o acesso a dados protegidos de clientes e a revisão da App Store levam tempo, então vale iniciar em paralelo com a implementação;
  - até a aprovação, o app funciona em lojas de desenvolvimento;
  - a revisão exige política de privacidade e o fluxo embedded funcionando.
- **Glossário e ADR:** o glossário já tem Loja, Provedor de e-commerce, Produto, Variante, Categoria, Cliente da loja, Pedido, Link de compra e Atribuição, além das mudanças em Ação e Playground. O ADR 0013 registra o modelo canônico com adapter por provedor e o webhook como aviso.
- **Conflito de nome:** a Shopify chama de "collections" o que aqui é Categoria. Isso deve aparecer só dentro do adapter.
- **Tokens que expiram:** a Shopify obriga apps públicos a usá-los a partir de 01/01/2027. Implementar desde já evita uma migração forçada.
