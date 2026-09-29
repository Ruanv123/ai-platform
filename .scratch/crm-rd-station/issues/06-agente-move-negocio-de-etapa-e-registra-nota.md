# 06: Agente move Negócio de Etapa e registra nota

**What to build:** O Agente ganha as duas Ações de escrita restantes. "Mover Negócio de Etapa" recebe uma das Etapas liberadas do Agente e move o Negócio aberto do seu Funil de vendas; Etapa não liberada é recusada à Tool com mensagem que o Agente entende, e sem Negócio aberto a Tool responde isso ao Agente. "Registrar nota" registra uma Atividade de nota na Pessoa do CRM e, havendo, no Negócio aberto do Funil de vendas do Agente. As duas passam pelo motor de Operações do CRM, enfileiradas. Na configuração do Agente, a Empresa lista as Etapas liberadas, cada uma com a descrição de quando mover; ganho e perdido nunca podem ser liberados, e Etapas comerciais podem. Com as quatro Ações existindo, elas passam a vir ligadas por padrão nos Agentes com Objetivo Vendas ou Qualificação quando há CRM conectado — ao conectar e ao criar um Agente —, com o primeiro Funil de vendas e a sua primeira Etapa do funil. No Playground as duas escritas são simuladas. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] A configuração do Agente lista Etapas liberadas do seu Funil de vendas, cada uma com descrição de quando mover, e o prompt do Agente recebe essas descrições
- [ ] Ganho e perdido não podem ser liberados; a validação recusa
- [ ] Mover para Etapa liberada cria Operação e move o Negócio no CRM, atualizando o Negócio vinculado
- [ ] Mover para Etapa não liberada ou sem Negócio aberto devolve mensagem ao Agente sem criar Operação
- [ ] Registrar nota cria Atividade de nota na Pessoa do CRM e no Negócio aberto, quando há
- [ ] Ao conectar o CRM e ao criar Agente com CRM conectado, Agentes com Objetivo Vendas ou Qualificação recebem as quatro Ações ligadas com o primeiro Funil de vendas e a sua primeira Etapa do funil; outros Objetivos não
- [ ] No Playground, mover e registrar nota são simulados, sem Operação
- [ ] Testes pelas Tools com a API do RD fingida; fixtures do RD de mover Negócio e criar anotação na suíte de conformidade
