# 02: Importação inicial do catálogo

**What to build:** Logo depois de conectar, a Loja entra em "importando" e a página mostra o progresso. A plataforma dispara uma Bulk Operation da Shopify para Produtos, Variantes e Categorias (collections), lê o JSONL em jobs e grava o modelo canônico: Produto (título, descrição em texto limpo, handle/URL, status, se está publicado na loja online, embedding), Variante (SKU, opções, preço, preço comparativo, estoque somado de todos os locais, se vende sem estoque, disponível) e Categoria ligada N:N aos Produtos. Nasce o endpoint de webhooks da Shopify: valida `X-Shopify-Hmac-Sha256` com o client secret do app, acha a Loja pelo `X-Shopify-Shop-Domain`, responde e enfileira sem guardar nada. O primeiro tópico tratado é `bulk_operations/finish`, que conclui a importação e deixa a Loja "sincronizada" com a hora da última sincronização. Nada de payload bruto é guardado. Ver `.scratch/loja-shopify/spec.md` e ADR 0013.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Conectar dispara a importação e a Loja fica em "importando" com progresso visível
- [ ] Produtos, Variantes e Categorias são gravados a partir do JSONL, com unicidade por Loja e id externo
- [ ] Estoque da Variante é a soma dos locais; preço e preço comparativo em colunas próprias
- [ ] Cada Produto recebe embedding de título, descrição, Categorias e opções, com hash do texto de origem guardado
- [ ] Webhook com HMAC inválido recebe 401; loja desconhecida recebe 200 e é descartada
- [ ] `bulk_operations/finish` conclui a importação e deixa a Loja "sincronizada"
- [ ] Rodar a importação duas vezes termina no mesmo estado
- [ ] Fixtures reais da Shopify (GraphQL, JSONL, webhook) num diretório por provedor, com as asserções sobre o canônico escritas de forma parametrizável por provedor (início da suíte de conformidade)
