# Spec: Leitura do site — onboarding que já conhece a Empresa

Status: ready-for-agent

ADRs: `docs/adr/0016-leitura-do-site-com-firecrawl-so-lendo-e-extracao-propria.md`, `docs/adr/0017-voz-da-marca-na-empresa-e-estilo-como-ajuste-do-agente.md`

## Problem Statement

Para o Agente responder bem, a Empresa precisa digitar no onboarding tudo o que ela é: descrição do negócio, produtos e serviços, público-alvo, contatos, endereço e Horário de atendimento. Depois ainda precisa montar uma Coleção, subir Documentos e criar um Agente antes de ver qualquer resposta. Quase tudo isso já está no site da Empresa, e o onboarding já pede o endereço dele, mas não faz nada com isso.

O resultado é um onboarding longo, com campos respondidos às pressas ou deixados em branco, e um Agente que diz "Não informado." para perguntas que o site responde. Não há onde dizer como a marca fala: o Estilo só oferece três tons por Agente, e uma Empresa com vários Agentes teria que repetir e manter a mesma descrição em cada um. O Documento URL lê uma página por vez e volta vazio em sites que dependem de JavaScript (Wix, Framer, SPAs), comuns entre as Empresas do público-alvo.

## Solution

A Empresa informa o nome e o site, e a plataforma faz uma Leitura do site:

- mapeia as URLs;
- escolhe as páginas-chave (home, sobre, serviços, políticas, FAQ, contato), até um teto;
- lê cada uma.

Com o que leu, a plataforma:

- monta a **Coleção do site**, com um Documento por página e o FAQ do site como Documento FAQ;
- propõe os **Dados da Empresa**, cada campo mostrando de qual página veio;
- propõe a **Voz da marca**: um texto sobre como a marca soa, com frases reais do site.

O onboarding mostra o progresso da Leitura. Quando ela termina, abre o formulário dos Dados da Empresa e da Voz da marca já preenchido para conferir. Depois pergunta o Objetivo e cria o primeiro Agente (inativo, consultando a Coleção do site) e leva o cliente direto ao Playground. Quem não quer esperar continua para o dashboard, onde a proposta aparece como pendência quando ficar pronta. Quem não tem site preenche o formulário como hoje.

Depois do onboarding, Configurações > Empresa permite fazer uma nova Leitura do site. A nova proposta mostra só o que mudou, para aceitar campo a campo, e nunca sobrescreve em silêncio o que já foi confirmado. A Voz da marca passa a entrar nas instruções de todos os Agentes da Empresa, e o Estilo de cada Agente continua valendo por cima dela.

## User Stories

### Onboarding

1. Como dono de uma Empresa se cadastrando, quero informar só o nome e o site no primeiro passo, para não ter que digitar o que o meu site já diz.
2. Como dono de uma Empresa sem site, quero dizer "não tenho site" e preencher os Dados da Empresa à mão, para que o onboarding funcione para mim também.
3. Como dono de uma Empresa, quero ver o progresso da Leitura do site enquanto ela acontece (páginas encontradas, páginas lidas), para saber que algo está acontecendo e quanto falta.
4. Como dono de uma Empresa com site lento, quero "continuar sem esperar" e ir ao dashboard, para não ficar preso numa tela de carregamento.
5. Como dono de uma Empresa que continuou sem esperar, quero ver no dashboard um aviso de que a proposta ficou pronta, para conferir quando puder.
6. Como dono de uma Empresa, quero receber o formulário dos Dados da Empresa já preenchido com o que o site diz, para só conferir em vez de escrever.
7. Como dono de uma Empresa, quero ver ao lado de cada campo proposto a página de onde ele veio, para julgar se a informação está atual.
8. Como dono de uma Empresa, quero que os campos que o site não informa fiquem vazios em vez de inventados, para não ter informação errada nas respostas do Agente.
9. Como dono de uma Empresa, quero editar qualquer campo proposto antes de salvar, para corrigir o que estiver desatualizado no site.
10. Como dono de uma Empresa, quero conferir o Horário de atendimento proposto antes de ele valer, porque o horário do site pode estar desatualizado ou ser de outra unidade.
11. Como dono de uma Empresa, quero ver a Voz da marca proposta, com frases reais do meu site, para reconhecer o jeito da minha marca falar.
12. Como dono de uma Empresa, quero editar a Voz da marca proposta, para ajustar o que a leitura não captou.
13. Como dono de uma Empresa, quero escolher o Objetivo do meu primeiro Agente numa escolha única, para ter um Agente criado sem configurar nada além disso.
14. Como dono de uma Empresa, quero terminar o onboarding direto no Playground do meu primeiro Agente, para ver na hora ele respondendo sobre a minha empresa.
15. Como dono de uma Empresa, quero que o primeiro Agente nasça inativo, para que ele não responda em nenhum Canal antes de eu conferir.
16. Como dono de uma Empresa cujo site não pôde ser lido, quero uma mensagem clara e o formulário manual, para não perder o onboarding por causa do meu site.
17. Como dono de uma Empresa cujo site foi lido só em parte, quero seguir com o que foi lido, para não perder tudo por causa de uma página com erro.
18. Como dono de uma Empresa que informou um Instagram ou um link de WhatsApp, quero ser avisado logo que esse endereço não é lido, para informar o site ou preencher à mão.
19. Como dono de uma Empresa que usa Linktree, quero que a leitura siga até o meu site de verdade, para não precisar descobrir qual link informar.
20. Como dono de uma Empresa com site em outro idioma, quero os Dados da Empresa e a descrição da Voz da marca propostos em português, porque o Agente atende em português.

### Coleção do site

21. Como alguém da Equipe, quero ver a Coleção do site na Base de conhecimento com um Documento por página lida, para saber exatamente o que o Agente sabe e de onde veio.
22. Como alguém da Equipe, quero que o FAQ do site vire um Documento FAQ com os pares pergunta/resposta, para que o Agente encontre a resposta certa para cada pergunta.
23. Como alguém da Equipe, quero que a página de FAQ não vire também um Documento URL, para o Agente não receber o mesmo conteúdo duas vezes.
24. Como alguém da Equipe, quero apagar uma página da Coleção do site que não serve para o atendimento, para o Agente não usá-la.
25. Como alguém da Equipe, quero que uma página que eu apaguei não volte na próxima Leitura, para não precisar apagá-la de novo.
26. Como alguém da Equipe, quero adicionar Documentos meus à Coleção do site, sabendo que a Leitura nunca mexe neles.
27. Como alguém da Equipe, quero renomear a Coleção do site, porque ela é uma Coleção como outra qualquer.
28. Como alguém da Equipe, quero que as páginas que falharam na leitura apareçam com erro na Coleção, como os Documentos com falha já aparecem hoje.
29. Como alguém da Equipe, quero que as páginas lidas contem na cota de armazenamento da Base de conhecimento, como qualquer Documento URL.
30. Como alguém da Equipe, quero que todo Agente novo já nasça consultando a Coleção do site, para não ter que ligá-la toda vez.

### Nova Leitura em Configurações

31. Como Owner ou Admin, quero fazer uma nova Leitura do site em Configurações > Empresa, para atualizar o que o Agente sabe quando o site mudar.
32. Como Owner ou Admin de uma Empresa criada antes deste recurso, quero fazer a primeira Leitura do site em Configurações, para aproveitar o recurso sem refazer o onboarding.
33. Como Owner ou Admin, quero ver quando foi a última Leitura e quantas páginas ela leu, para saber se o conhecimento está atual.
34. Como Owner ou Admin, quero que a nova proposta mostre só os campos em que o site diverge do valor atual, para revisar só o que mudou.
35. Como Owner ou Admin, quero aceitar cada campo divergente separadamente, com um "aceitar tudo" de atalho, para ficar com o meu valor onde eu sei que o site está errado.
36. Como Owner ou Admin, quero descartar uma proposta inteira, para que ela pare de aparecer como pendente.
37. Como Owner ou Admin, quero que nada que eu já confirmei seja sobrescrito sem eu aceitar, para confiar nos Dados da Empresa.
38. Como Owner ou Admin, quero que, numa nova Leitura, as páginas que continuam no site sejam atualizadas e as que sumiram sejam removidas da Coleção do site, para o Agente não usar informação antiga.
39. Como Owner ou Admin, quero que a plataforma me impeça de rodar mais de uma Leitura a cada 24 horas e me diga quando posso rodar de novo, para entender o limite.
40. Como Owner ou Admin que trocou o endereço do site, quero ver a oferta "ler o novo site" em vez de uma Leitura disparada sozinha, para decidir quando isso acontece.
41. Como Owner ou Admin de uma Empresa que já tinha Agentes, quero ao fim da Leitura escolher a quais deles ligar a Coleção do site, para que a mudança nos Agentes existentes seja uma decisão minha.
42. Como Member, quero ver a Coleção do site e os Dados da Empresa sem poder rodar nem confirmar uma Leitura, porque isso muda como todos os Agentes falam.
43. Como alguém com permissão só de Base de conhecimento, quero poder apagar Documentos da Coleção do site, como em qualquer Coleção.

### Voz da marca e o Agente

44. Como Owner ou Admin, quero editar a Voz da marca em Configurações > Empresa a qualquer momento, para ajustá-la sem precisar de uma nova Leitura.
45. Como Owner ou Admin, quero que a Voz da marca valha para todos os Agentes da Empresa, para não repetir a mesma descrição em cada um.
46. Como alguém que configura um Agente, quero que o Estilo do Agente prevaleça sobre a Voz da marca quando os dois divergirem, para ter um Agente de suporte mais formal que a marca.
47. Como Contato, quero que o Agente fale do jeito que a marca fala no site, para sentir que estou falando com a própria empresa.
48. Como Contato, quero que o Agente saiba o telefone, o endereço e o horário que a Empresa confirmou, para receber a informação certa sem que ele precise consultar nada.
49. Como alguém da Equipe testando no Playground, quero que o Agente já use a Voz da marca e a Coleção do site, para avaliar o resultado real.
50. Como Owner ou Admin, quero que, sem Voz da marca definida, o Agente continue funcionando só com o Estilo, como hoje.

## Implementation Decisions

### Modelo

- **Leitura do site** é uma entidade da Empresa:
  - situação: em andamento, concluída ou com falha;
  - endereço lido e endereço resolvido (quando veio de Linktree);
  - quem pediu, início e fim;
  - contagem de páginas encontradas, lidas e com falha;
  - motivo da falha, em texto amigável;
  - a **proposta**.
- A Empresa guarda o histórico de Leituras, e vale a última.
- A proposta é um conjunto de campos. Cada campo guarda o valor proposto e a URL de origem, e ela tem uma situação própria: pendente, confirmada, descartada ou substituída. Uma nova Leitura concluída marca como substituída a proposta pendente anterior.
- Campos que a proposta pode conter:
  - segmento (um dos valores de `TeamSegment`, ou vazio);
  - descrição do negócio;
  - produtos e serviços;
  - público-alvo;
  - telefone;
  - e-mail público;
  - endereço;
  - Horário de atendimento (no mesmo formato por dia da semana que a Empresa já usa);
  - Voz da marca: texto e frases de exemplo.
- A proposta não inclui nome nem fuso. Campo não encontrado fica ausente da proposta, nunca vira um valor inventado.
- A **Voz da marca** é guardada na Empresa, em dois campos:
  - um texto descritivo em pt-BR, com limite de tamanho da ordem de 1.000 caracteres;
  - uma lista de 2 a 4 frases de exemplo, no idioma original do site.
- A **Coleção do site** é uma Coleção marcada como mantida pela Leitura do site. Uma Empresa tem no máximo uma. Se a Equipe apagou a Coleção do site, a próxima Leitura cria outra.
- Documentos criados pela Leitura são marcados como vindos dela. Só esses a Leitura atualiza ou remove.
- URLs de Documentos vindos da Leitura que a Equipe apagou ficam registradas como ignoradas na Coleção do site, e a Leitura não as traz de volta.
- O onboarding ganha um estado na Empresa: em qual passo ela está e quando terminou. Isso substitui a regra atual de "usuário com Empresa não entra no onboarding".

### Leitura (pipeline)

- Roda em segundo plano, na fila de ingestão, em etapas:
  1. validar o endereço;
  2. resolver o Linktree;
  3. mapear as URLs;
  4. escolher as páginas-chave;
  5. ler as páginas;
  6. extrair a proposta e os pares de FAQ;
  7. atualizar a Coleção do site.
- Cada Documento criado ou atualizado segue a ingestão já existente, com Trechos e embeddings.
- **Validação do endereço**, feita na requisição, antes de gastar crédito:
  - só HTTP/HTTPS;
  - recusa Instagram e endereços de WhatsApp (`wa.me`, `api.whatsapp.com`, `whatsapp.com`) com uma mensagem que orienta a informar o site ou preencher à mão.
- **Linktree**: a página é lida, e a Leitura segue o primeiro link que seja um site próprio, ignorando redes sociais e WhatsApp. Sem link aproveitável, a Leitura falha com um motivo claro.
- **Mapeamento e leitura** (ADR 0016):
  - um módulo leitor de páginas devolve markdown a partir de uma URL e oferece o mapa de URLs de um site;
  - a implementação usa a API do Firecrawl pelo cliente HTTP do Laravel, sem SDK novo;
  - a chave e a URL base ficam na configuração de serviços;
  - quando o Firecrawl falha (erro, timeout, sem chave configurada), a leitura de uma página cai no Readability atual;
  - sem mapa do Firecrawl, a Leitura usa os links da home lida pelo fallback.
- O Documento URL passa a usar o mesmo leitor de páginas, com o mesmo fallback.
- **Escolha das páginas-chave**: um agente do `laravel/ai` com structured output recebe o mapa de URLs e devolve até N URLs (configurável, padrão 25).
  - Prioriza: institucional, serviços, políticas (trocas, entregas, privacidade), FAQ e contato.
  - Exclui: produtos individuais, posts de blog, paginação, login e carrinho.
  - A home entra sempre.
  - O agente também indica qual URL, se houver, é a página de FAQ.
- **Extração**: agentes do `laravel/ai` com structured output, sobre o markdown lido.
  - Um agente produz a proposta de Dados da Empresa e a Voz da marca, com a URL de origem de cada campo.
  - Outro extrai os pares pergunta/resposta da página de FAQ.
  - Dados da Empresa e a descrição da Voz da marca saem sempre em pt-BR. Frases de exemplo e Documentos ficam no idioma original.
  - O conteúdo do site é tratado como dado não confiável: as instruções dos agentes mandam ignorar instruções contidas nas páginas, como já faz o agente de triagem de produtos.
- **Coleção do site**:
  - cada página lida vira um Documento URL, exceto a página de FAQ, que vira um único Documento FAQ com os pares extraídos;
  - numa nova Leitura: URL já presente é reprocessada, URL vinda da Leitura que não foi mais escolhida ou não existe mais é removida, URL ignorada é pulada, Documentos manuais nunca são tocados;
  - trocar o site é tratado igual: as páginas do domínio antigo contam como sumidas.
- **Falhas**:
  - falha total (nenhuma página lida, endereço recusado, Linktree sem site): a Leitura fica com falha e motivo amigável;
  - falha parcial: a Leitura conclui, e as páginas com erro ficam como Documentos com falha na Coleção do site;
  - estouro da cota de armazenamento faz as páginas que não couberem falharem como qualquer Documento URL.
- **Limite**: no máximo uma Leitura iniciada a cada 24 horas por Empresa. A leitura no onboarding conta.
  - Pedir antes do prazo é recusado com o momento em que será possível de novo.
  - Uma Leitura em andamento impede outra.

### Onboarding

- **Passo 1: nome e site, ou "não tenho site".**
  - Com site: cria a Empresa com o padrão de fuso e inicia a Leitura.
  - Sem site: cria a Empresa e segue para o formulário manual dos Dados da Empresa, que é o atual sem nome e site.
- **Passo 2: progresso da Leitura.** A página acompanha a situação da Leitura (polling do Inertia) e mostra páginas encontradas e lidas.
  - Leitura concluída: segue para o passo 3.
  - Leitura com falha: segue para o formulário manual com o motivo.
  - "Continuar sem esperar": vai ao dashboard, e o onboarding fica marcado como concluído.
- **Passo 3: Dados da Empresa e Voz da marca pré-preenchidos**, com o selo de origem por campo. Salvar confirma a proposta com os valores editados. Os Documentos da Coleção do site já existem desde o fim da Leitura e não dependem desta confirmação: o conhecimento entra direto na Base de conhecimento, e só os Dados da Empresa e a Voz da marca pedem confirmação.
- **Passo 4: Objetivo do primeiro Agente.**
  - Cria um Agente inativo, sem Versão do agente, com os pontos de partida do Objetivo escolhido e a Coleção do site ligada, se existir.
  - Leva ao Playground desse Agente e marca o onboarding como concluído.
  - O passo existe também no caminho sem site.
- **Dashboard**: com proposta pendente, mostra um aviso que leva à conferência em Configurações > Empresa.
  - Se a proposta pendente veio do onboarding, a conferência é a do passo 3 (formulário pré-preenchido).
  - Se veio de uma nova Leitura, é a do diff por campo.

### Configurações > Empresa

- **Bloco "Leitura do site"**:
  - mostra a última Leitura (situação, data, páginas lidas, motivo da falha);
  - tem o botão para ler o site, desabilitado com o horário liberado quando o limite de 24 horas está ativo;
  - quando o endereço salvo difere do endereço da última Leitura, mostra a oferta "ler o novo site".
- **Conferência de nova Leitura**:
  - lista só os campos da proposta cujo valor difere do atual, com o valor atual, o proposto e a página de origem;
  - aceitar por campo e "aceitar tudo" gravam só os campos aceitos;
  - "descartar" encerra a proposta.
- **Ao concluir uma Leitura numa Empresa que já tem Agentes**: oferece ligar a Coleção do site aos Agentes escolhidos. A Leitura nunca liga a Coleção a um Agente existente sozinha.
- **Voz da marca** passa a ser um campo editável da página, independente de Leitura.
- **Permissão**: rodar, confirmar e descartar exigem `TeamPermission::UpdateTeam`. Os Documentos que a Leitura cria não exigem `ManageKnowledge` à parte. Apagar Documentos da Coleção do site segue a regra de qualquer Coleção.

### Agente

- Agentes criados depois que a Coleção do site existe nascem com ela ligada, seja pelo onboarding ou pela tela de Agentes.
- O template de instruções do Agente ganha a seção "Voz da marca", entre "Dados da Empresa" e "Estilo".
  - A seção traz o texto e as frases de exemplo, e diz explicitamente que o Estilo abaixo prevalece quando divergir.
  - Sem Voz da marca, a seção é omitida.
  - A versão do template é incrementada.
- Como os Dados da Empresa já são lidos na hora, a Voz da marca também é (ADR 0017): confirmar ou editar muda a fala de todos os Agentes, inclusive os ativos, sem republicar. A Versão do agente não guarda a Voz da marca.

## Testing Decisions

- Bons testes aqui exercitam comportamento observável pela borda HTTP da plataforma e pelas instruções que o Agente recebe, nunca a ordem interna das etapas nem o formato das chamadas entre módulos.
- **Seam 1: HTTP da plataforma, com fronteiras externas falsificadas.**
  - Os testes de feature chamam as rotas do onboarding e de Configurações > Empresa, com a fila síncrona.
  - O que é falsificado:
    - a API do Firecrawl e o site do cliente (para o fallback), via `Http::fake` com `preventStrayRequests`;
    - os agentes de escolha de páginas e de extração, com o fake do `laravel/ai` e `preventStrayPrompts`;
    - os embeddings, com `Embeddings::fake()`.
  - Casos a cobrir:
    - onboarding com site: Empresa criada, Leitura concluída, Coleção do site com um Documento por página e o FAQ como Documento FAQ (sem Documento URL duplicado), formulário do passo 3 com os valores propostos e a origem nas props, confirmação gravando os Dados da Empresa e a Voz da marca, primeiro Agente inativo com a Coleção do site ligada e redirecionamento ao Playground;
    - onboarding sem site: formulário manual e primeiro Agente sem Coleção do site;
    - "continuar sem esperar": onboarding concluído, e o dashboard mostra a pendência quando a Leitura conclui;
    - falha total: Leitura com falha e motivo, e o onboarding cai no formulário manual;
    - falha parcial: Leitura concluída, e as páginas com erro aparecem como Documentos com falha;
    - fallback: a API do Firecrawl falha e a página é lida pelo Readability;
    - Instagram e WhatsApp recusados sem nenhuma requisição externa;
    - Linktree seguido até o site próprio;
    - limite de 24 horas e Leitura em andamento recusando nova Leitura;
    - nova Leitura: páginas mantidas reprocessadas, páginas sumidas removidas, páginas apagadas pela Equipe não voltam, Documentos manuais intactos;
    - nova Leitura: a proposta lista só os campos divergentes, aceitar por campo grava só os aceitos, descartar encerra, e uma nova Leitura substitui a proposta pendente;
    - Agentes existentes não recebem a Coleção do site sem a escolha explícita, e Agentes novos nascem com ela;
    - permissão: Member não roda, não confirma e não descarta Leitura;
    - isolamento: uma Empresa não vê nem confirma a Leitura de outra.
- **Seam 2: instruções do Agente.** Pelo mesmo caminho dos testes de Playground (agente falsificado, verificação das instruções recebidas):
  - a seção de Voz da marca aparece com o texto e as frases;
  - ela é omitida sem Voz da marca;
  - ela afirma a precedência do Estilo.
- Os testes existentes do Documento URL continuam passando com o novo leitor de páginas. Os que falsificam o site direto passam a exercitar o fallback, ou recebem um fake do Firecrawl.
- **Prior art**:
  - `tests/Feature/Knowledge/UrlDocumentTest.php` (Http fake de páginas, falhas amigáveis, cota);
  - `tests/Feature/Integrations/ShopifyCatalogImportTest.php` (importação em segundo plano de fonte externa);
  - `tests/Feature/CompanyOnboardingTest.php` (fluxo de onboarding);
  - `tests/Feature/Agents/ProductSearchTest.php` (fakes de agentes do `laravel/ai` com structured output);
  - `tests/Feature/Knowledge/PlaygroundTest.php` e `tests/Feature/Settings/CompanyTest.php`.

## Out of Scope

- Branding visual (logo, cores, fontes), que volta quando o Chat no site for construído.
- Leitura do site automática ou periódica.
- Verificação de que o site pertence à Empresa.
- Proposta de Limites, Cenários ou Estilo do Agente a partir do site.
- Crawl completo do site e leitura de páginas de produto (os Produtos chegam pela Loja).
- Classificação das páginas em Coleções por assunto.
- Leitura de Instagram, Facebook ou outras redes sociais.
- Uso do `/extract` ou do formato JSON do Firecrawl (ADR 0016).
- Chave do Firecrawl informada pela Empresa.

## Further Notes

- As decisões vieram de uma sessão de grilling registrada no `CONTEXT.md`: Dados da Empresa, Voz da marca, Leitura do site e Coleção do site, além do ajuste em Estilo.
- O passo de Objetivo no caminho sem site foi estendido por consistência: a sessão decidiu o primeiro Agente para o caminho com site, e o fechamento no Playground vale igualmente para quem preenche à mão.
- O custo por Leitura é limitado pelo teto de páginas e pela janela de 24 horas. Vale medir créditos do Firecrawl e tokens de extração por Leitura desde o primeiro ticket, para calibrar o N.
- Sem chave do Firecrawl configurada (desenvolvimento, testes), tudo funciona pelo fallback do Readability, com qualidade menor em sites que dependem de JavaScript.
