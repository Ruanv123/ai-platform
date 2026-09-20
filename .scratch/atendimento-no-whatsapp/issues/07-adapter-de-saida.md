# 07: Adapter de saída

**What to build:** A família de saída, uma por Tipo de canal, com três ações na v1: **enviar texto**, **marcar como lida** e **indicador de "digitando"**. Sem envio de mídia. Cada envio de texto registra a Mensagem de saída com o id devolvido pelo provedor, para que o status de entrega dos issues 03 e 04 consiga atualizá-la depois. Atenção ao par de nomes: "marcar como lida" é ação de saída e é o **oposto** do status `read` recebido do provedor, apesar do nome parecido em português — não compartilhe nome entre os dois no código. Referência: spec, user stories 22, 23, 25, 38.

**Blocked by:** 01, 02

**Status:** done

- [x] Interface de saída com enviar texto, marcar como lida e "digitando"
- [x] Implementação para o WhatsApp oficial e para o WhatsApp de testes
- [x] Envio de texto registra a Mensagem de saída com o id do provedor
- [x] Nenhum envio de mídia
- [x] Nomes distintos para a ação de saída e o status recebido
- [x] Testes com `Http::fake()` / `Http::assertSent`, cobrindo os dois Tipos de canal
