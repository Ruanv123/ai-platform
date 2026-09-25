# Spec: Agente na tela de atendimento em tempo real

Status: ready-for-agent

## Problem Statement

A Empresa não consegue acompanhar nem assumir pela tela de atendimento uma Conversa conduzida diretamente pelo Agente. A caixa de entrada atual mostra apenas transferências feitas por Fluxos. Enquanto uma pessoa da Equipe tenta responder por outro caminho, o Agente pode continuar gerando e enviando uma resposta para o mesmo Contato. O estado de espera por humano usado pelos Fluxos pertence ao Contato inteiro e não representa uma Pausa da IA limitada ao Canal.

As Mensagens de imagem, áudio e documento já chegam pelos dois Tipos de canal WhatsApp, mas seus arquivos originais são descartados depois da descrição, transcrição ou extração de texto. Vídeos são registrados como conteúdo não suportado. A tela recebe apenas o texto derivado, portanto a Equipe não consegue ver nem reproduzir o conteúdo que o Contato enviou. O tempo real existente cobre a caixa de transferências, não todas as Conversas.

## Solution

Transformar a tela de atendimento na lista de todas as Conversas com Mensagens nos Canais WhatsApp oficial e WhatsApp de testes, identificando cada Conversa pelo par Contato + Canal. A lista abre ordenada pela Mensagem mais recente, permite filtrar por estado e responsável e mantém as transferências de Fluxos, com seus controles atuais, enquanto esse produto coexistir com o Agente.

Nas Conversas conduzidas diretamente pelo Agente, uma pessoa da Equipe pode pausá-lo e assumir a responsabilidade. O primeiro envio humano também pausa e assume na mesma ação. Durante a Pausa da IA, o Contato continua enviando Mensagens que aparecem em tempo real, sem receber resposta automática. A Empresa configura o prazo da pausa; a Equipe pode devolver antes dele. Ao retomar, o Agente responde uma única vez se a última Mensagem do Contato ainda não tiver resposta enviada pela Empresa.

Mensagens novas de texto, imagem, áudio, vídeo e documento tornam-se visíveis na tela com acesso protegido à mídia original e ao texto derivado disponível. Figurinhas aparecem apenas como marcador. O envio humano continua limitado a texto e Template. A atualização entre atendentes usa o WebSocket existente; o comando de envio continua por HTTP.

## User Stories

### Localizar e acompanhar Conversas

1. As a pessoa da Equipe, I want to ver todas as Conversas com Mensagens dos Canais WhatsApp, so that eu acompanhe o atendimento do Agente antes de qualquer transferência.
2. As a pessoa da Equipe, I want to ver a Mensagem mais recente no topo da lista, so that eu encontre rapidamente o atendimento ativo.
3. As a pessoa da Equipe, I want to distinguir duas Conversas do mesmo Contato em Canais diferentes, so that eu responda pelo Canal correto.
4. As a pessoa da Equipe, I want to ver se a Conversa está com o Agente, com atendimento humano ou com um Fluxo transferido, so that eu saiba qual ação está disponível.
5. As a pessoa da Equipe, I want to filtrar Conversas por estado, so that eu encontre as que precisam de intervenção.
6. As a pessoa da Equipe, I want to filtrar Conversas por responsável, so that eu encontre as minhas e veja a carga da Equipe.
7. As a pessoa da Equipe, I want to buscar por nome ou telefone do Contato, so that eu encontre uma Conversa específica.
8. As a pessoa da Equipe, I want to continuar vendo Conversas de um Canal desconectado ou com erro, so that o histórico não desapareça durante uma falha da conexão.
9. As a pessoa da Equipe, I want to ver o estado do Canal e o envio bloqueado quando ele estiver desconectado, so that eu não tente responder por um número indisponível.
10. As a pessoa da Equipe, I want to ver as transferências antigas de Fluxos na mesma lista e usar os controles existentes, so that nenhum atendimento humano em andamento se perca na transição do produto.
11. As a Empresa, I want to que outra Empresa não consiga consultar minhas Conversas ou mídias, so that os dados dos meus Contatos permaneçam isolados.

### Pausar e assumir o Agente

12. As a pessoa da Equipe, I want to pausar o Agente em uma Conversa e assumi-la em uma ação, so that eu possa responder sem concorrência da IA.
13. As a pessoa da Equipe, I want to enviar uma resposta em uma Conversa ainda conduzida pelo Agente e assumir automaticamente nessa operação, so that eu não precise executar duas ações antes de ajudar o Contato.
14. As a Contato que fala em dois Canais, I want to que uma pausa em um deles não interrompa o outro, so that o atendimento automático continue onde não houve intervenção.
15. As a pessoa da Equipe, I want to ver quem assumiu a Conversa, so that eu não responda por cima de outra pessoa.
16. As a pessoa da Equipe, I want to assumir explicitamente uma Conversa atribuída a outra pessoa quando necessário, so that uma ausência não deixe o Contato sem atendimento.
17. As a Empresa, I want to registrar quem assumiu e quem substituiu o responsável, so that a troca de atendimento seja rastreável.
18. As a pessoa da Equipe, I want to que o Agente não responda a novas Mensagens durante a Pausa da IA, so that o Contato receba apenas a resposta humana.
19. As a pessoa da Equipe, I want to que uma resposta ainda em geração seja impedida de sair após a pausa, so that a IA não concorra com minha resposta.
20. As a pessoa da Equipe, I want to que uma falha no meu envio mantenha a Conversa pausada e atribuída a mim, so that eu possa corrigir e tentar novamente sem a IA assumir inesperadamente.
21. As a pessoa da Equipe, I want to enviar texto livre ou Template pelo mesmo Canal, so that o Contato continue a conversa no número de origem.
22. As a pessoa da Equipe, I want to que uma tomada concorrente seja resolvida de forma inequívoca, so that só uma pessoa seja responsável por vez.

### Prazo e retorno

23. As a Owner ou Admin, I want to configurar o prazo da Pausa da IA em horas e minutos, so that a Empresa ajuste o tempo de intervenção à sua operação.
24. As a Owner ou Admin, I want to receber validação para um prazo entre 1 minuto e 24 horas, so that uma configuração inválida não deixe Conversas presas ou com vencimento excessivo.
25. As a Empresa nova, I want to começar com prazo de 30 minutos, so that haja retorno automático sem configuração prévia.
26. As a pessoa da Equipe, I want to que cada nova Mensagem recebida ou enviada com sucesso durante a pausa reinicie o prazo, so that o Agente não volte no meio de uma conversa humana ativa.
27. As a pessoa da Equipe, I want to que uma mudança de configuração não altere o vencimento da pausa que já assumi, so that o prazo exibido permaneça previsível.
28. As a pessoa da Equipe, I want to permanecer responsável durante a pausa, so that uma simples ausência de atividade não libere a Conversa para outra pessoa sem ação explícita.
29. As a pessoa da Equipe, I want to devolver a Conversa ao Agente antes do prazo, so that ele retome assim que minha intervenção terminar.
30. As a Empresa, I want to que o Agente volte quando o prazo terminar, so that a pausa não dure indefinidamente.
31. As a Contato, I want to receber uma resposta do Agente ao retorno se minha última Mensagem ficou sem resposta da Empresa, so that minha pergunta não fique abandonada.
32. As a Contato, I want to não receber uma resposta duplicada do Agente quando a Empresa já respondeu por último, so that a conversa continue coerente.
33. As a pessoa da Equipe, I want to ver uma resposta automática bloqueada pela janela do WhatsApp no chat, so that eu saiba que preciso usar um Template ou esperar nova Mensagem do Contato.
34. As a Empresa, I want to que o sistema não faça uma chamada de texto livre à Meta quando já sabe que a janela terminou, so that uma rejeição previsível não gere tráfego inútil.
35. As a pessoa da Equipe, I want to que uma falha na resposta de retorno mantenha a pausa e minha atribuição, so that eu possa resolver o atendimento.
36. As a pessoa da Equipe, I want to que a mesma falha não seja tentada repetidamente sem nova Mensagem do Contato, so that o chat não se encha de erros idênticos.

### Mensagens, mídia e tempo real

37. As a pessoa da Equipe, I want to ler as Mensagens de texto recebidas e enviadas em ordem, so that eu acompanhe o contexto do atendimento.
38. As a pessoa da Equipe, I want to ver a imagem original e sua legenda ou descrição, so that eu compreenda o que o Contato mostrou.
39. As a pessoa da Equipe, I want to reproduzir o áudio original e ler sua transcrição, so that eu consiga atender mesmo quando a transcrição for insuficiente.
40. As a pessoa da Equipe, I want to reproduzir um vídeo recebido e ver sua legenda, so that o conteúdo seja acessível mesmo que o Agente não interprete vídeo.
41. As a pessoa da Equipe, I want to abrir ou baixar um documento recebido e ler o texto extraído, so that eu examine o material enviado pelo Contato.
42. As a pessoa da Equipe, I want to ver o marcador “Figurinha não exibida”, so that a cronologia mantenha a Mensagem sem renderizar a figurinha.
43. As a pessoa da Equipe, I want to continuar lendo o texto derivado das mídias antigas, so that o histórico anterior permaneça útil apesar de não haver arquivo recuperável.
44. As a Empresa, I want to que os arquivos de mídia existam enquanto suas Mensagens existirem, so that o histórico novo continue reproduzível.
45. As a pessoa da Equipe, I want to receber novas Mensagens na tela sem recarregar manualmente, so that eu responda ao Contato em tempo hábil.
46. As a pessoa da Equipe, I want to ver imediatamente mensagens enviadas por colegas e mudanças de responsável ou estado, so that minha tela não indique uma posse desatualizada.
47. As a pessoa da Equipe, I want to recuperar o estado atual ao reconectar depois de uma queda do WebSocket, so that nenhuma Mensagem ou troca de responsável desapareça da minha visão.
48. As a pessoa de outra Empresa, I want to ser impedida de acessar o canal de tempo real e os arquivos desta Empresa, so that o isolamento entre Empresas também valha fora da página inicial.

## Implementation Decisions

### Escopo e domínio

- Entregar para WhatsApp oficial e WhatsApp de testes. O Canal de testes mantém sua visibilidade restrita atual. Canais desconectados ou com erro permanecem na listagem histórica em modo de leitura.
- A identidade da Conversa na navegação, nas ações e na listagem passa a ser o par Contato + Canal. O Histórico continua uma consulta às Mensagens, conforme o ADR 0003; não se cria uma entidade de Conversa com ciclo de vida.
- Persistir um estado explícito de Pausa da IA e atribuição humana por Contato + Canal, escopado pela Empresa, independente de Execuções de Fluxos e do marcador global de espera por humano no Contato. O ADR 0010 registra essa separação.
- A caixa de entrada reúne Conversas conduzidas pelo Agente e transferências de Fluxos. Estas conservam seus controles, estado e regras atuais. A Pausa da IA nova só se aplica ao Agente que atende diretamente pelo Canal. A remoção dos Fluxos será outra tarefa.
- Mostrar estados claros de Agente ativo, Pausa da IA, transferência de Fluxo, falha de envio e indisponibilidade do Canal; manter os filtros por Canal, busca e responsável já existentes onde fizerem sentido.

### Posse, envio e concorrência

- Pessoas da Equipe podem pausar e assumir. O envio humano iniciado com Agente ativo realiza pausa e atribuição atomicamente antes de entregar a Mensagem ao provedor. Se o envio falhar, pausa e atribuição persistem e a falha aparece no chat.
- Uma Conversa pausada aceita envio da pessoa atribuída. Outra pessoa precisa executar tomada explícita; essa transição deve ser atômica, visível às demais e registrada com responsável anterior e novo.
- A entrada e o job de resposta do Agente consultam a pausa. A decisão final de enviar a resposta da IA precisa ser serializada com a transição para pausa; um prompt já iniciado não autoriza envio depois que a pessoa assumiu. Mensagens recebidas durante a pausa não disparam nova resposta automática.
- Envio humano continua por requisição HTTP e usa os adapters existentes de texto e Template. A interface e a validação mantêm a regra atual da janela de 24 horas para texto humano no WhatsApp oficial.
- A lógica de Mensagens pendentes distingue recebimentos do Contato de saídas enviadas com sucesso pela Empresa. Uma resposta humana cobre Mensagens anteriores; o retorno do Agente considera apenas a última entrada ainda sem resposta, agrega o contexto do Histórico e evita resposta duplicada.

### Prazo e retorno

- Owner/Admin configura por Empresa horas e minutos, convertidos para um prazo de 1 a 1.440 minutos, padrão de 30 minutos. A mudança vale apenas para novas pausas; cada pausa guarda o prazo aplicável ao seu início.
- Cada Mensagem de entrada ou saída enviada com sucesso enquanto a IA estiver pausada renova o vencimento. Envio que falhou não conta como atividade enviada.
- Não há liberação automática da pessoa responsável por inatividade na Pausa da IA. Uma tomada explícita é permitida. O comportamento já existente das transferências de Fluxos permanece separado.
- Devolução manual e vencimento usam a mesma regra: se a última Mensagem relevante for entrada do Contato, o Agente responde uma vez; se a Empresa enviou a última resposta com sucesso, o Agente aguarda nova entrada. Um processamento repetido do vencimento não envia resposta duplicada.
- Uma resposta automática bloqueada pela janela do WhatsApp oficial aparece como saída falha no chat sem requisição à Meta. Falha do provedor ou da IA também permanece visível. Nesses casos, a Pausa da IA e a atribuição continuam, sem novas tentativas automáticas até ação humana ou nova Mensagem do Contato.

### Mídia e tempo real

- Para Mensagens novas, os dois provedores preservam a mídia original recebida em armazenamento privado, ligada à Mensagem com metadados necessários para exibir ou baixar. O arquivo acompanha a existência da Mensagem. Eventos reentregues pelo provedor não duplicam a mídia.
- A tela renderiza texto, imagem, áudio, vídeo e documento; exibe também legenda, descrição, transcrição ou texto extraído quando houver. Áudio e vídeo protegidos precisam permitir reprodução e busca no player. Documentos visualizáveis podem abrir na tela; os demais oferecem download autenticado. Falha de obtenção da mídia mostra estado claro e mantém o texto disponível.
- Figurinhas não têm arquivo renderizado na tela e mostram apenas “Figurinha não exibida”. Arquivos antigos não são recuperados retroativamente. O Agente continua sem interpretação de vídeo; a entrega acrescenta sua visualização humana.
- O acesso à mídia valida autenticação, Empresa e Conversa; URLs públicas ou credenciais do provedor não são expostas ao cliente.
- Usar o canal privado de Reverb já existente para sinalizar novas Mensagens e mudanças de estado, atribuição e falha em todas as Conversas. O cliente obtém o estado canônico pelo servidor e o reconcilia ao reconectar. O envio não migra para WebSocket.

## Testing Decisions

- Fronteira confirmada pelo solicitante: testar o comportamento observável pelas requisições de atendimento e configuração, pelos webhooks dos dois provedores e pelo processamento dos jobs de resposta/retorno. Os testes atravessam autorização, persistência e seleção do adapter sem fixar métodos internos ou a forma exata das tabelas. Usar banco de testes real e simular apenas IA, HTTP externo, armazenamento e broadcasting quando necessário.
- Prior art: os testes atuais de atendimento cobrem listagem, isolamento por Empresa, tomada concorrente, envio humano e retorno ao Fluxo; os testes de resposta do WhatsApp cobrem buffer, envio e falhas; os testes de resolução de mídia e a suíte de conformidade exercitam ambos os provedores.
- Cobrir a listagem por Contato + Canal, filtros, Conversas de Canais desconectados e manutenção das transferências de Fluxos; verificar autorização de membro, papéis para configurar prazo e negação entre Empresas.
- Cobrir pausa explícita, pausa por envio, tomada concorrente, falha do envio humano, nova entrada durante pausa e bloqueio de resposta da IA que já estava em processamento.
- Congelar o tempo para testar valor padrão, limites da configuração, renovação do prazo por cada lado, configuração alterada com pausa vigente, retorno manual/automático, última entrada pendente, última saída da Empresa, execução repetida do vencimento e ausência de nova tentativa após falha.
- Cobrir janela do WhatsApp oficial encerrada com resposta visível como falha e nenhuma chamada de texto à Meta; manter teste separado para Template humano permitido nessa janela.
- Exercitar texto e mídia nova nos dois provedores com fixtures e armazenamento simulado: imagem, áudio, vídeo, documento, figurinha, reentrega e falha de download. Verificar visualização autorizada, rejeição entre Empresas, manutenção do texto derivado e ausência de recuperação de arquivo antigo.
- Verificar autorização do canal privado e emissão do aviso de atualização para Mensagens ativas e pausadas, atribuição, retorno e falha. Validar o estado entregue à página e executar verificação manual da interface e reconexão com Reverb; não introduzir dependência de teste de navegador apenas para esta entrega.

## Out of Scope

- Remover Fluxos, migrar suas Execuções ou alterar as regras atuais de transferência por Fluxo.
- Enviar imagem, áudio, vídeo ou documento pela Equipe ou pelo Agente nesta entrega.
- Interpretar vídeo com IA, gerar novas transcrições de vídeo ou tornar figurinhas renderizáveis.
- Recuperar arquivos de mídia recebidos antes da mudança.
- Estender o atendimento em tempo real para Chat no site ou Instagram Direct.
- Criar uma entidade de Conversa com abertura e encerramento próprios ou substituir o Histórico por uma nova fonte de dados.

## Further Notes

- A tela atual foi concebida como caixa de exceção exclusiva de Fluxos. Esta especificação amplia a leitura sem interromper atendimentos de Fluxos já pausados. A decisão de retirar Fluxos do produto foi tomada como direção futura e terá especificação própria.
- O estado global de espera por humano do Contato não serve como fonte da Pausa da IA por Canal. A coexistência dos dois mecanismos é deliberada enquanto Fluxos estiverem presentes; ver ADR 0010.
- A [política da Meta](https://whatsappbusiness.com/policy/) permite texto livre apenas dentro da janela de 24 horas após a última Mensagem do Contato. Fora dela, apenas Template aprovado pode ser enviado; o chat deve tornar visível a resposta automática bloqueada para ação da Equipe.
- A operação em tempo real já possui Reverb e processo dedicado documentados no projeto. Esta entrega amplia os eventos e os dados exibidos, sem adicionar outro serviço de WebSocket.
