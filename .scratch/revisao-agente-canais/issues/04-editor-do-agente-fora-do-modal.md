# 04: Editor do Agente fora do modal

**What to build:** Criar e editar um Agente deixa de acontecer dentro de um modal e passa a ter uma página inteira. Quem cria um Agente é levado direto para o editor dele em vez de voltar para a lista; quem salva uma edição permanece onde está, com um toast. As rotas saem do prefixo de conhecimento e passam a viver sob o próprio recurso, porque o Agente é o centro do produto e não um apêndice da Base de conhecimento. O Playground continua na página separada dele nesta entrega. Referência: spec, user stories 2, 3, 17, 18 e 19.

**Blocked by:** 03 (Agente vinculado a Canais e lista em tabela)

**Status:** done

- [x] Rotas de Agente movidas para o prefixo próprio, com os controllers no namespace de Agentes; nomes de rota preservados
- [x] Criar e editar renderizam a mesma página de editor; o modal é removido
- [x] Criar redireciona para o editor do Agente recém-criado
- [x] Salvar uma edição permanece na página e apenas emite o toast
- [x] Erros de validação aparecem em pt-BR junto do campo correspondente
- [x] Member não acessa o editor
- [x] Rotas do frontend regeneradas e importações atualizadas
- [x] Feature tests: página de criação e de edição renderizam, redirecionamento após criar, permanência após salvar, permissões por Papel, isolamento entre Empresas
