# 14: Nó de Requisição HTTP

**What to build:** O Fluxo chama uma API externa e continua a conversa com o que voltou. A Empresa monta método, endereço, cabeçalhos e corpo usando o que o Contato disse, e declara como a resposta vira Variáveis. Falha temporária é tentada de novo; falha definitiva segue um caminho próprio no desenho, para que o Contato receba uma resposta em vez de silêncio.

**Blocked by:** 06

**Status:** completed

- [x] Método, endereço, cabeçalhos e corpo com interpolação de Variável, Campo do Contato e Variável global
- [x] Mapeamento declarado da resposta para Variáveis
- [x] Retry com backoff para falha temporária
- [x] Saída de erro própria no Nó para falha definitiva, com o caminho decidido no desenho
- [x] Lista de campos sensíveis declarada no Nó, preparada para o mascaramento que vem na spec de observabilidade
- [x] Credencial nunca aparece em registro de nenhum tipo
- [x] Testes usam `Http::fake` e `Http::assertSent`
