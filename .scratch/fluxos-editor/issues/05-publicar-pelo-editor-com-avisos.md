# 05: Publicar pelo editor, com os avisos de validação

**What to build:** O botão Publicar funciona e, quando o desenho tem problema, a Empresa entende o problema na hora em vez de descobrir pelo cliente. As recusas e os avisos aparecem em português claro, apontando o Nó culpado no canvas.

**Blocked by:** 04, `fluxos-motor` 11

**Status:** ready-for-agent

- [ ] Publicar cria a Versão e atualiza o estado na barra
- [ ] Recusa com Nó órfão, aresta sem destino ou ciclo sem saída, destacando o Nó no canvas
- [ ] Recusa ao publicar um segundo Fluxo de conversa no mesmo Canal, explicando o motivo
- [ ] Aviso quando um Fluxo de reação ganhou Nó de espera e mudou de natureza
- [ ] Aviso quando um Fluxo de reação envia mensagem num Canal que já tem Fluxo de conversa
- [ ] Aviso quando um Fluxo de gatilho de webhook começa por Nó de Mensagem em vez de Template
- [ ] Member não consegue publicar
