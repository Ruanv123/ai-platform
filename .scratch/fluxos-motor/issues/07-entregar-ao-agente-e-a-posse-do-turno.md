# 07: Entregar ao Agente e a posse do turno

**What to build:** O Fluxo empresta o turno ao Agente, que conversa livremente usando a persona, as Instruções e a Base de conhecimento já configuradas. O Fluxo declara a condição em que quer o turno de volta, e ela é verificada em código a cada mensagem. O Agente também pode devolver por conta própria quando a situação fugiu do previsto.

**Blocked by:** 05

**Status:** completed

- [x] Nó de Entregar ao Agente escolhe qual Agente da Empresa assume
- [x] Condição de retorno declarada no Nó, avaliada em código pelo avaliador do 05 a cada mensagem — nunca pelo modelo
- [x] Satisfeita a condição, o turno volta ao Fluxo no Nó seguinte
- [x] O Agente recebe uma ferramenta de devolver o turno e o Fluxo retoma quando ela é chamada
- [x] O Agente inativo não assume; a Execução segue o caminho de erro do Nó
- [x] A persona, o tom, as Instruções e as Coleções do Agente salvo continuam valendo dentro do Fluxo
- [x] Teste usa o `::fake()` do agente, no padrão de `PlaygroundTest`
