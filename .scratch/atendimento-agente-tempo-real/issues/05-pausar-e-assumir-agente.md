# 05: Pausar e assumir o Agente

**What to build:** Uma pessoa da Equipe pausa o Agente e assume uma Conversa específica. O Agente deixa de responder nesse Canal, inclusive quando uma resposta já estava em geração, enquanto outro Canal do mesmo Contato continua normalmente.

**Blocked by:** 02 — Mostrar todas as Conversas na caixa de entrada.

**Status:** done

- [x] A ação de pausar cria ou atualiza estado exclusivo do par Contato + Canal e atribui atomicamente a pessoa que agiu; o estado não depende da Execução de Fluxo nem da espera global do Contato.
- [x] A interface oferece a ação somente em Conversas conduzidas diretamente por Agente ativo e mostra estado e responsável a toda a Equipe.
- [x] Mensagens recebidas durante a pausa ficam registradas sem iniciar resposta automática; uma resposta da IA iniciada antes da pausa não sai depois dela.
- [x] Uma pausa no Canal A não bloqueia o Agente no Canal B do mesmo Contato; não há liberação automática da atribuição por 15 minutos de inatividade.
- [x] Testes pela entrada do WhatsApp e pelo processamento da resposta cobrem isolamento, concorrência, resposta em andamento e preservação das transferências de Fluxos.
