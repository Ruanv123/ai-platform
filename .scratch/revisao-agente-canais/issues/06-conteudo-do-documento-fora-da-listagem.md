# 06: Conteúdo do Documento fora da listagem e volta para a Base de conhecimento

**What to build:** Duas coisas que preparam a tabela e já valem sozinhas. A primeira: o link acima do título da Coleção volta a funcionar — hoje ele aponta para a própria tela de Documentos, então não sai do lugar; passa a levar para a Base de conhecimento. A segunda: a listagem para de mandar o conteúdo extraído e os itens de FAQ de todos os Documentos de uma vez, o que numa Empresa com Documentos de arquivo significa megabytes por navegação. O conteúdo passa a ser buscado quando o Documento é aberto para edição, e o modal continua sendo modal. Referência: spec, user stories 66 e 67.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] Link de voltar aponta para a listagem de Coleções
- [x] Conteúdo extraído e itens de FAQ saem do payload da listagem
- [x] Endpoint devolve um Documento completo, respeitando Empresa e permissões
- [x] Modal de edição busca o Documento ao abrir, com estado de carregamento
- [x] Editar, salvar e reprocessar continuam funcionando como antes
- [x] Feature tests: conteúdo ausente da listagem, presente no endpoint do Documento, Documento de outra Empresa devolve não encontrado
