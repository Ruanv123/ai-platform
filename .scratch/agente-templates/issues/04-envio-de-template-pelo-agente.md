# 04: Envio de Template pelo Agente com variáveis preenchidas pela IA

**What to build:** O núcleo usado por todos os gatilhos: escolher o Template ligado ao Agente para um uso e Canal, checar consentimento e pausa, gerar os valores das variáveis com saída estruturada da IA e enviar pelo adaptador do Canal, registrando a Mensagem ou o motivo de não enviar.

**Blocked by:** 03

**Status:** done

- [x] Preenchimento das variáveis com saída estruturada, a partir de descrição, exemplo, motivo do envio, Contato, histórico e compromisso
- [x] Valor vazio, com quebra de linha ou acima do limite impede o envio
- [x] Marketing só com consentimento concedido; finalidade revogada bloqueia qualquer categoria
- [x] Conversa pausada não recebe envio automático
- [x] Mensagem de saída registrada com o Agente como autor; envio bloqueado registrado com o motivo
