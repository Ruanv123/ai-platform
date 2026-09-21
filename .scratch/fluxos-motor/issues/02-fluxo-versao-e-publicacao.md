# 02: Fluxo, Versão e publicação

**What to build:** A Empresa cria um Fluxo, salva o rascunho, publica e pausa. Publicar congela o desenho numa Versão imutável; editar depois mexe só no rascunho, e a tela mostra que existem alterações não publicadas. Pausar desliga o atendimento sem apagar a Versão que estava no ar. Ninguém de outra Empresa enxerga nada disso, e só Owner e Admin publicam.

**Blocked by:** None (can start immediately)

**Status:** completed

- [x] Fluxo tem estado único de três valores: rascunho, publicado, pausado
- [x] Publicar cria uma Versão imutável com o DSL completo; a Versão nunca é alterada depois
- [x] Ativar só é possível depois da primeira publicação
- [x] "Alterações não publicadas" é derivado da comparação entre rascunho e Versão, não um quarto estado
- [x] Validação de schema do DSL recusa a publicação de desenho malformado
- [x] Validação de integridade recusa Nó órfão, aresta sem destino e ciclo sem saída
- [x] Owner e Admin publicam; Member vê e não publica
- [x] Fluxo e Versão são escopados pela Empresa, com teste de isolamento
- [x] Testes dirigem pelas rotas, com asserção de Inertia e de banco, no padrão de `ChannelTest` e `AgentTest`
