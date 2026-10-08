# 08: Ferramenta enviar_template (o Agente decide)

**What to build:** Quando o Agente tem Templates de uso livre, ele ganha a ferramenta `enviar_template`, que lista cada Template com quando usá-lo e recebe os valores das variáveis, validados como nos outros envios.

**Blocked by:** 04

**Status:** done

- [x] Ferramenta disponível só quando há Template de uso livre aprovado para a Conta do Canal
- [x] Esquema da ferramenta com o nome do Template e um campo por variável, descrito pela descrição da variável
- [x] Mesma validação de valores, consentimento e pausa do envio automático
- [x] Resultado da ferramenta informa ao Agente se o Template foi enviado ou por que não
