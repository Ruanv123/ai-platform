# 07: Documentos em tabela paginada com filtros

**What to build:** Dentro de uma Coleção, os Documentos deixam de ser cards soltos e viram uma tabela: título com ícone do tipo, tipo, status, tamanho e data de atualização, com as ações num menu por linha. A listagem é paginada no servidor, com busca por título, filtro por tipo, filtro por status e ordenação — tudo na URL, para que recarregar a página ou compartilhar o link não perca o estado. O status de um Documento em processamento continua se atualizando sozinho, mas só enquanto houver algo processando na página que está aberta, e sem tirar a pessoa de onde ela está. Referência: spec, user stories 55–65 e 68–70.

**Blocked by:** 06 (Conteúdo do Documento fora da listagem e volta para a Base de conhecimento)

**Status:** ready-for-agent

- [ ] Listagem paginada no servidor, 20 por página
- [ ] Busca por título, filtro por tipo e filtro por status, carregados da query string
- [ ] Ordenação por título, tamanho e data de atualização, com atualização mais recente como padrão
- [ ] Filtros e ordenação preservados ao paginar e ao voltar para a tela
- [ ] Tabela com título e ícone do tipo, tipo, status, tamanho e data de atualização
- [ ] Editar, baixar, mover, reprocessar e excluir num menu de ações por linha
- [ ] Polling sinalizado apenas quando a página renderizada tem Documento pendente ou em processamento, com recarregamento parcial que preserva estado e rolagem
- [ ] Estado vazio claro quando o filtro não devolve nada, distinto da Coleção sem Documentos
- [ ] Mensagem de erro do Documento que falhou continua visível
- [ ] Card de uso de armazenamento da Empresa permanece acima da tabela
- [ ] `@tanstack/react-table` adicionado, com data table e paginação do shadcn em modo manual
- [ ] Member vê a tabela sem as ações de gerenciamento
- [ ] Feature tests: paginação com metadados, cada filtro, cada ordenação, preservação na navegação, sinalização de polling por página, isolamento entre Empresas
