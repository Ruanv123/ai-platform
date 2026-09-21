# 11: Fluxo de conversa e Fluxo de reação

**What to build:** A plataforma passa a distinguir dois tipos de Fluxo, e a distinção é lida do desenho, não declarada. Um Fluxo que contém algum Nó que espera o Contato toma o turno e é único por Canal no Gatilho de mensagem. Um Fluxo que não espera nada roda do começo ao fim e pode rodar em paralelo com quantos outros houver — é assim que etiquetar, registrar no CRM e alertar a equipe acontecem sem atrapalhar a conversa.

**Blocked by:** 04

**Status:** completed

- [x] A categoria é derivada do grafo na publicação e guardada na Versão, para o motor não recalcular a cada mensagem
- [x] É de conversa se, e somente se, contém Nó que espera o Contato
- [x] Publicar um segundo Fluxo de conversa no mesmo Canal com Gatilho de mensagem é recusado
- [x] Publicar um Fluxo de reação que ganhou Nó de espera avisa que ele mudou de natureza
- [x] Vários Fluxos de reação disparam na mesma mensagem, em paralelo
- [x] Fluxo de reação não ocupa Execução ativa nem toma o turno
- [x] Fluxo de reação pode enviar mensagem; a publicação avisa quando o Canal já tem Fluxo de conversa
- [x] Decisão e alternativas rejeitadas estão no ADR 0006
