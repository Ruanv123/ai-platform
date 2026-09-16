# 04: Editor do Agente fora do modal

**What to build:** Criar e editar um Agente deixa de acontecer dentro de um modal e passa a ter uma página inteira. Quem cria um Agente é levado direto para o editor dele em vez de voltar para a lista; quem salva uma edição permanece onde está, com um toast. As rotas saem do prefixo de conhecimento e passam a viver sob o próprio recurso, porque o Agente é o centro do produto e não um apêndice da Base de conhecimento. O Playground continua na página separada dele nesta entrega. Referência: spec, user stories 2, 3, 17, 18 e 19.

**Blocked by:** 03 (Agente vinculado a Canais e lista em tabela)

**Status:** ready-for-agent

- [ ] Rotas de Agente movidas para o prefixo próprio, com os controllers no namespace de Agentes; nomes de rota preservados
- [ ] Criar e editar renderizam a mesma página de editor; o modal é removido
- [ ] Criar redireciona para o editor do Agente recém-criado
- [ ] Salvar uma edição permanece na página e apenas emite o toast
- [ ] Erros de validação aparecem em pt-BR junto do campo correspondente
- [ ] Member não acessa o editor
- [ ] Rotas do frontend regeneradas e importações atualizadas
- [ ] Feature tests: página de criação e de edição renderizam, redirecionamento após criar, permanência após salvar, permissões por Papel, isolamento entre Empresas
