# 11: Primeiro Agente no fim do onboarding

**What to build:** O onboarding ganha um último passo, para quem veio pelo site e para quem preencheu à mão: escolher o Objetivo do primeiro Agente numa escolha única. Escolher cria um Agente inativo, sem Versão do agente, com os pontos de partida do Objetivo e a Coleção do site ligada, se ela existir. Em seguida o onboarding é concluído e leva ao Playground desse Agente, que já usa os Dados da Empresa, a Voz da marca e a Coleção do site. Quem saiu por "continuar sem esperar" não passa por este passo. Ver `.scratch/leitura-do-site/spec.md` e o termo Agente inativo no `CONTEXT.md`.

**Blocked by:** 08, 09

**Status:** ready-for-agent

- [ ] Depois do passo 3 ou do formulário manual, o onboarding pede o Objetivo antes de concluir
- [ ] Escolher cria um Agente inativo, sem Versão do agente, com os pontos de partida do Objetivo
- [ ] O Agente nasce consultando a Coleção do site quando ela existe
- [ ] O onboarding é concluído e leva ao Playground do Agente criado
- [ ] O primeiro Agente não responde em nenhum Canal
