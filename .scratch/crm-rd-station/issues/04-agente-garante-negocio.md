# 04: Agente garante Negócio

**What to build:** O Agente ganha a Ação "garantir Negócio", e com ela nasce o motor de Operações do CRM. A Tool recebe título e, opcionalmente, valor estimado, cria uma Operação do CRM pendente, enfileira e devolve ao Agente que o pedido foi registrado, sem esperar o CRM. O job garante o Vínculo — criando a Pessoa do CRM na primeira escrita quando nenhuma é encontrada pelo telefone, sob lock por Contato para que escritas concorrentes nunca criem duas —, busca ao vivo um Negócio aberto da Pessoa do CRM no Funil de vendas do Agente e atualiza esse Negócio ou cria um na Etapa inicial configurada. A Pessoa do CRM criada recebe o Responsável padrão da configuração do CRM; o Negócio criado herda o Responsável da Pessoa do CRM ou, sem ele, recebe o padrão. Nome e telefone de Pessoa do CRM existente nunca são sobrescritos, só preenchidos quando vazios. O Negócio resultante é guardado como Negócio vinculado (Funil de vendas, Etapa do funil, Situação, valor, Responsável). Nasce o supervisor `integrations` no Horizon com as filas `crm-webhooks`, `crm-operations` e `crm-maintenance`, separado do que responde o WhatsApp; `crm-operations` tem 5 tentativas com backoff exponencial, limite de requisições por CRM declarado pelo adapter, espera respeitando o tempo pedido pelo provedor sem gastar tentativa, renovação de token uma vez em acesso recusado e falha imediata em recusa definitiva. No Playground a escrita é simulada: devolve o que teria sido enviado e não cria Operação. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] A configuração do Agente escolhe Funil de vendas e Etapa inicial entre os metadados carregados; a tela do CRM escolhe o Responsável padrão
- [ ] A Tool cria a Operação do CRM pendente e responde ao Agente sem esperar o CRM
- [ ] Sem Pessoa do CRM com o telefone do Contato, a primeira escrita cria a Pessoa do CRM e o Vínculo
- [ ] Duas Operações concorrentes do mesmo Contato sem Vínculo criam uma única Pessoa do CRM
- [ ] Havendo Negócio aberto da Pessoa do CRM no Funil de vendas do Agente, inclusive criado pelo vendedor, ele é atualizado em vez de duplicado
- [ ] Responsável: Pessoa do CRM nova recebe o padrão; Negócio novo herda o da Pessoa do CRM ou recebe o padrão
- [ ] Nome e telefone de Pessoa do CRM existente não são sobrescritos
- [ ] O Negócio vinculado fica guardado com Funil de vendas, Etapa do funil, Situação, valor e Responsável
- [ ] Operação termina feita, ou com falha e motivo legível: falha temporária tenta de novo até 5 vezes; limite de requisições espera sem gastar tentativa; acesso recusado renova o token uma vez; recusa definitiva falha na hora
- [ ] O supervisor `integrations` existe no Horizon com as três filas e prioridade entre elas
- [ ] No Playground, garantir Negócio é simulado e não cria Operação nem chama o CRM
- [ ] Testes do motor pelo adapter falso (Vínculo concorrente, categorias de erro) e da Tool com a API do RD fingida; fixtures do RD de criar Pessoa do CRM, buscar Negócios abertos, criar e atualizar Negócio na suíte de conformidade
