# 01: Identificar Conversas por Contato e Canal

**What to build:** A pessoa da Equipe abre exatamente a Conversa do Contato no Canal escolhido, mesmo quando o mesmo Contato tem Mensagens e transferências em mais de um Canal. Esta mudança prepara a caixa de entrada existente para incluir o Agente sem confundir Históricos ou ações de Fluxos.

**Blocked by:** None (can start immediately).

**Status:** done

- [x] Lista, navegação e ações identificam a Conversa pelo par Contato + Canal; dois Canais do mesmo Contato têm destinos distintos.
- [x] A leitura mostra somente as Mensagens do Canal escolhido e mantém paginação, ficha do Contato e detalhes da Execução quando houver transferência de Fluxo.
- [x] Assumir, responder e devolver uma transferência existente agem no Canal e na Execução corretos; uma URL antiga nunca abre silenciosamente o Canal errado.
- [x] Requisições de outra Empresa não revelam a Conversa; testes de funcionalidade cobrem dois Canais do mesmo Contato e preservam o comportamento de Fluxos.
