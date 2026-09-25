# Spec: Perfil do WhatsApp gerenciado pela plataforma

Status: ready-for-agent

## Problem Statement

Hoje, para trocar a foto, o recado, a descrição ou o nome que os Contatos veem ao abrir a conversa com a Empresa, alguém da Equipe precisa sair da plataforma: ir ao WhatsApp Manager da Meta (no WhatsApp oficial) ou ao celular conectado (no WhatsApp de testes). A tela do Canal só cuida da conexão. O Owner/Admin que configurou o Canal aqui não consegue ver nem ajustar a identidade pública do Número do WhatsApp no mesmo lugar, e não tem como saber pela plataforma se um pedido de troca de nome ainda está em revisão na Meta.

## Solution

A tela do Canal ganha abas de linha: **Conexão** (o conteúdo atual) e **Perfil**. A aba Perfil mostra e edita o **Perfil do WhatsApp** direto no provedor, sem cópia no banco. Ela existe nas duas telas: a do WhatsApp oficial e a do WhatsApp de testes, e as duas usam o mesmo componente. Cada provedor declara quais campos suporta, e a aba só mostra esses:

- **WhatsApp oficial (Meta):** foto, recado, descrição, endereço, e-mail, até dois sites e categoria. O nome de exibição pode ser alterado por pedido, que passa pela revisão da Meta.
- **WhatsApp de testes (Evolution Go):** foto e recado. O nome aparece só para leitura.

A foto é trocada numa ação própria, com recorte interativo (arrastar e dar zoom), prévia e confirmação. Os campos de texto têm um "Salvar" próprio.

## User Stories

### Navegação

1. As an Owner, I want to ver as abas "Conexão" e "Perfil" na tela do Canal do WhatsApp, so that eu gerencie tudo do número num lugar só
2. As an Owner, I want to que a aba ativa fique na URL, so that eu mande o link direto para o Perfil e continue nele ao recarregar a página
3. As a Member, I want to abrir a aba Perfil, so that eu saiba como a Empresa aparece para os Contatos
4. As an Owner, I want to que o Perfil só seja carregado quando eu abro a aba dele, so that a aba Conexão não fique mais lenta nem dependa da Meta responder
5. As an Owner, I want to ver um esqueleto enquanto o Perfil carrega, so that eu saiba que a informação está a caminho
6. As an Owner, I want to encontrar a mesma aba Perfil na tela do WhatsApp de testes, so that eu use a mesma experiência em desenvolvimento

### Leitura

7. As an Owner, I want to ver a foto atual do número, so that eu confirme o que os Contatos estão vendo
8. As an Owner, I want to ver o nome de exibição atual, so that eu saiba como a Empresa se apresenta
9. As an Owner, I want to ver o recado atual, so that eu decida se preciso atualizá-lo
10. As an Owner of a Canal oficial, I want to ver descrição, endereço, e-mail, sites e categoria atuais, so that eu revise os dados do negócio
11. As an Owner, I want to que o Perfil venha sempre direto do provedor, so that uma troca feita pelo celular ou pelo WhatsApp Manager apareça aqui sem desatualização
12. As an Owner, I want to ver um estado vazio com link para a aba Conexão quando o Canal não está conectado, so that eu saiba o que falta para gerenciar o Perfil
13. As an Owner, I want to ver um toast de erro e um botão "Tentar de novo" quando o provedor falha ao devolver o Perfil, so that eu tente de novo sem recarregar a página
14. As an Owner, I want to que uma falha na leitura do Perfil não mude o status do Canal, so that o status continue refletindo só a conexão

### Edição de textos

15. As an Owner, I want to alterar o recado, so that eu comunique horário, promoção ou aviso rápido
16. As an Admin, I want to alterar o recado, so that eu mantenha o Perfil sem depender do Owner
17. As an Owner of a Canal oficial, I want to alterar descrição, endereço e e-mail, so that os Contatos encontrem a Empresa
18. As an Owner of a Canal oficial, I want to informar até dois sites, so that os Contatos cheguem ao site e à loja
19. As an Owner of a Canal oficial, I want to escolher a categoria do negócio numa lista em português, so that eu não precise conhecer os códigos da Meta
20. As an Owner, I want to ver os limites de tamanho de cada campo enquanto digito, so that a Meta não recuse o que eu salvar
21. As an Owner, I want to ver erros de validação ao lado do campo, so that eu corrija antes de enviar
22. As an Owner, I want to ver um toast de sucesso ao salvar, so that eu saiba que o provedor aceitou
23. As an Owner, I want to ver um toast de erro em português quando o provedor recusa, so that eu entenda o que corrigir
24. As a Member, I want to ver o Perfil só para leitura, sem botões de salvar, so that eu não tente uma ação que não posso fazer
25. As an Owner of a Canal de testes, I want to ver só os campos que o Evolution Go suporta, so that eu não preencha algo que seria ignorado

### Foto

26. As an Owner, I want to escolher uma imagem do meu computador, so that eu troque a foto do número
27. As an Owner, I want to recortar a imagem arrastando e dando zoom num quadrado, so that o logo não fique cortado no círculo do WhatsApp
28. As an Owner, I want to ver a prévia antes de confirmar, so that eu não publique uma foto errada
29. As an Owner, I want to que a foto seja enviada logo ao confirmar, sem depender do "Salvar" dos textos, so that uma falha na foto não derrube a edição dos textos
30. As an Owner, I want to ser avisado quando a imagem não é JPEG/PNG ou passa de 5 MB, so that eu escolha outra
31. As an Owner of a Canal oficial without App ID, I want to ver um aviso com link para a aba Conexão, so that eu saiba que preciso do App ID para trocar a foto
32. As an Owner, I want to ver a foto nova na aba depois de confirmar, so that eu saiba que ela foi aplicada
33. As an Owner of a Canal de testes, I want to trocar a foto do número conectado pelo QR code, so that eu teste o Perfil em desenvolvimento

### Nome de exibição (WhatsApp oficial)

34. As an Owner of a Canal oficial, I want to pedir um novo nome de exibição, so that a Empresa passe a aparecer com o nome certo
35. As an Owner, I want to saber que a troca de nome passa por revisão da Meta e tem limite de pedidos, so that eu não espere que ela seja imediata
36. As an Owner, I want to ver "Em revisão" enquanto a Meta avalia o nome pedido, so that eu acompanhe sem abrir o WhatsApp Manager
37. As an Owner, I want to ver se o nome pedido foi aprovado ou recusado, so that eu saiba se preciso pedir de novo
38. As an Owner, I want to ser avisado de que, depois da aprovação, o número precisa ser re-registrado no WhatsApp Manager, so that o nome novo passe a valer
39. As an Owner of a Canal de testes, I want to ver o nome só para leitura, so that eu não tente uma troca que o Evolution Go não faz de forma confiável

### Conexão

40. As an Owner, I want to informar o App ID do app da Meta no formulário de Conexão, so that a plataforma consiga enviar a foto do Perfil
41. As an Owner, I want to que o App ID seja opcional, so that a conexão continue funcionando para quem não vai trocar a foto

### Segurança e isolamento

42. As an Owner, I want to que só Owner e Admin consigam alterar o Perfil, so that um Member não mude a identidade pública da Empresa
43. As an Owner, I want to que outra Empresa não consiga ler nem alterar o Perfil do meu Canal, so that meus dados fiquem isolados
44. As a platform operator, I want to que a imagem enviada ao Evolution Go fique acessível só por uma URL assinada e temporária e seja apagada depois de aplicada, so that as fotos não fiquem expostas nem acumulem no disco

## Implementation Decisions

### Domínio

- O termo é **Perfil do WhatsApp**, já registrado no `CONTEXT.md`. "Contato" continua sendo o cliente final e não deve aparecer na interface nem no código desta feature com o sentido de Perfil.
- O provedor é a fonte da verdade. Nada do Perfil é persistido. Se um dia for preciso guardar uma cópia, o lugar natural é o Número do WhatsApp (`whatsapp_phone_numbers`), que já guarda identidade e saúde do número.
- O WhatsApp de testes continua sendo um Tipo de canal próprio (ADR 0002 intacto). A aba existe em cada tela, não numa tela unificada.

### Contrato por provedor

- Um contrato de Perfil do WhatsApp com duas implementações: Meta e Evolution Go. As telas e controllers dependem só do contrato. O contrato expõe:
  - **capacidades:** quais campos são legíveis e quais são editáveis (foto, nome, recado, descrição, endereço, e-mail, sites, categoria);
  - **ler o Perfil** de um Canal, devolvendo um valor com os campos suportados, a URL da foto e o estado do nome de exibição (atual, pedido e status da revisão, quando houver);
  - **atualizar textos** com os campos suportados;
  - **trocar a foto** a partir de um arquivo JPEG já recortado;
  - **pedir nome de exibição** (só Meta; o Evolution declara que não pode).
- As falhas do provedor viram uma exceção de domínio com mensagem em português, reaproveitando a tradução de erros da Meta que hoje fica no controller de conexão (códigos 190, 10, 100, `OAuthException`) e a de erros do cliente Evolution Go existente.

### Meta

- **Leitura:** GET do business profile do número com os campos `about, address, description, email, profile_picture_url, websites, vertical`, mais um GET do número com `verified_name, name_status, new_display_name, new_name_status`.
- **Textos:** POST no business profile com `messaging_product: whatsapp` e só os campos alterados.
- **Foto:** Resumable Upload. Abrir sessão em `/{app_id}/uploads` com nome, tamanho e tipo do arquivo; enviar os bytes com `Authorization: OAuth <token>` e `file_offset: 0`; mandar o handle devolvido como `profile_picture_handle` no POST do business profile.
- **Nome:** POST no número com `new_display_name`. O status da revisão vem sempre da leitura ao vivo. O re-registro com PIN após a aprovação fica manual, e a aba avisa disso.
- **Limites validados no servidor:** recado de 1 a 139 caracteres, endereço até 256, descrição até 512, e-mail válido até 128, até 2 sites com http(s) e até 256 caracteres cada, categoria dentro do enum da Meta (OTHER, AUTO, BEAUTY, APPAREL, EDU, ENTERTAIN, EVENT_PLAN, FINANCE, GROCERY, GOVT, HOTEL, HEALTH, NONPROFIT, PROF_SERVICES, RETAIL, TRAVEL, RESTAURANT, ALCOHOL, ONLINE_GAMBLING, PHYSICAL_GAMBLING, OTC_DRUGS), com rótulos em português num enum PHP.

### Evolution Go

- Autenticação pelo header `apikey` com o token da instância, como o cliente atual já faz.
- **Leitura:** nome via `GET /instance/status`, recado via `POST /user/info`, foto via `POST /user/avatar` (número próprio, sem preview).
- **Recado:** `POST /user/profileStatus`.
- **Foto:** `POST /user/profilePicture` com `image` apontando para uma URL assinada da própria plataforma. O arquivo fica num disco privado; a rota assinada expira em cerca de 5 minutos, fica fora da autenticação de sessão e serve o arquivo; ele é apagado depois que o Evolution responde (sucesso ou falha).
- **Nome:** só leitura. O endpoint `/user/profileName` do Evolution Go v0.7.2 chama o rename de grupo em vez do push name e não é confiável.

### Conta do WhatsApp

- Nova coluna `app_id`, opcional, na Conta do WhatsApp, editável no formulário da aba Conexão junto das outras credenciais e serializada para o frontend. Sem App ID, a capacidade de trocar foto na Meta fica indisponível e a aba mostra o aviso.

### HTTP e Inertia

- As telas do WhatsApp oficial e do WhatsApp de testes aceitam `?tab=conexao|perfil`. O Perfil é uma deferred prop, avaliada só com a aba Perfil ativa. Ela volta vazia, com a mensagem de erro, quando o provedor falha, e retorna um estado "não conectado" quando o Canal não está `Connected`.
- Rotas novas por tela para: atualizar textos, trocar foto (multipart com o JPEG recortado) e, só no oficial, pedir nome. Todas autorizam com `update` do Canal (Owner/Admin) e redirecionam de volta com `?tab=perfil` e toast.
- Falhas do provedor na leitura ou na gravação **nunca** alteram o `ChannelStatus` nem o `last_error`.
- Validação no servidor da foto: JPEG/PNG, até 5 MB, quadrada, de 192 a 640 px (via dimensões da imagem, sem GD).

### Frontend

- Abas de linha "Conexão" | "Perfil" no topo das duas telas, sincronizadas com a query.
- Um componente de aba Perfil reaproveitado nas duas telas, dirigido pelas capacidades do provedor. Seguindo a regra do projeto, cada peça fica no seu próprio arquivo: formulário de textos, bloco de foto, diálogo de recorte e bloco de nome de exibição.
- Recorte com `react-easy-crop`, dependência nova aprovada pelo usuário. O canvas gera um JPEG quadrado de 640 px enviado por multipart.
- Esqueleto pulsante enquanto a deferred prop carrega. O estado vazio com "Tentar de novo" recarrega só a prop do Perfil.
- Member vê tudo desabilitado e sem ações.

## Testing Decisions

- Bons testes exercitam o comportamento externo pela borda mais alta: requisição HTTP → resposta Inertia/redirect → requisições ao provedor. Não testam classes internas nem o formato das chamadas do cliente isoladamente.
- **Backend (Pest, feature):** seam único no HTTP da aplicação, com `Http::fake` no limite com a Meta e com o Evolution Go. Prior art: os testes de feature do Canal do WhatsApp e do WhatsApp de testes (renderização sem segredos, teste de conexão com Meta fake, QR code lido ao vivo do Evolution). Cobrir:
  - a deferred prop do Perfil com dados do provedor, só com os campos suportados, vazia quando não conectado e com erro quando o provedor falha, sem mudar o `ChannelStatus`;
  - a gravação dos textos: payload enviado (`Http::assertSent`), validação dos limites e da categoria, toast de erro em caso de recusa;
  - a foto na Meta: sequência sessão → bytes → handle e bloqueio sem App ID;
  - a foto no Evolution: URL assinada enviada, servindo o arquivo, expirando e com o arquivo apagado depois;
  - o pedido de nome na Meta e a leitura de "Em revisão", mais a recusa do pedido de nome no WhatsApp de testes;
  - as permissões: Owner/Admin gravam, Member recebe 403, outra Empresa recebe 404;
  - o App ID salvo pelo formulário de Conexão, preservando os segredos.
- **Frontend (Vitest):** nova suíte com Vitest, React Testing Library e jsdom, que são dependências de desenvolvimento novas, aprovadas pelo usuário. Testar pelo que a pessoa vê e faz:
  - a aba Perfil mostra só os campos das capacidades recebidas;
  - estado vazio e "Tentar de novo";
  - modo leitura para Member;
  - aviso de App ID ausente;
  - o fluxo do diálogo de recorte: escolher arquivo → recortar → prévia → confirmar envia um JPEG, com a geração do blob no canvas mockada, já que o jsdom não tem canvas;
  - rejeição de arquivo fora de tipo/tamanho.
- Não há prior art de testes de frontend no repositório. Esta feature introduz a configuração do Vitest e o script `npm test`.

## Out of Scope

- Re-registro automático do número com PIN depois da aprovação do nome, gestão do PIN de verificação em duas etapas e escuta do webhook `phone_number_name_update`.
- Troca de nome no WhatsApp de testes, até confirmar que o endpoint do Evolution Go funciona.
- Descrição, endereço, e-mail, sites e categoria no WhatsApp de testes, porque o Evolution Go não tem esses endpoints.
- Cópia local do Perfil, histórico de alterações ou auditoria de quem trocou o quê.
- Promover o Evolution Go a provedor oferecido aos clientes (reverteria o ADR 0002).
- Qualidade do número e limites de mensagens na aba Perfil. Isso continua na Conexão.

## Further Notes

- Os limites de tamanho da Meta vêm de fontes secundárias (a referência oficial lista os campos sem limites). A tradução dos erros da Meta deve mostrar a mensagem do provedor quando o limite real for diferente.
- A troca de nome na Meta aceita no máximo 10 pedidos a cada 30 dias. Vale mostrar isso na interface antes de enviar.
- O Evolution Go precisa conseguir acessar a URL da plataforma para baixar a foto, a mesma exigência que o webhook do WhatsApp de testes já tem.
- Fontes: referência da Business Profile API, guia de Display Names, guia de Resumable Upload da Meta e o código de `pkg/routes/routes.go` e `pkg/user/service/user_service.go` do repositório `EvolutionAPI/evolution-go`.
