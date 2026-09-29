# 14: CRM com erro e CRM com atenção

**What to build:** Dois estados que tornam a integração honesta. **Erro:** quando o RD Station recusa a renovação do acesso, o CRM fica com erro, com motivo e a ação de reconectar, e Owner e Admin são avisados, no mesmo padrão da Agenda. Enquanto isso, Operações novas ou em andamento ficam pendentes e retidas; ao reconectar, são enviadas na ordem em que foram criadas; passadas 72 horas, falham com motivo. **Atenção:** os metadados são recarregados ao abrir a tela do CRM (quando a última carga tem mais de alguns minutos), por um comando diário e quando uma Operação falha por referência inexistente. Depois de cada carga, a configuração é conferida: Funil de vendas, Etapa do funil ou Campo do CRM usado por um Agente (Funil de vendas, Etapa inicial, Etapas liberadas, Campos visíveis), por um mapeamento ou pelas Etapas comerciais e que não existe mais deixa o CRM com atenção, com a lista do que quebrou e onde. Corrigir a configuração tira o CRM de atenção. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 05, 06, 09, 10

**Status:** ready-for-agent

- [ ] Renovação recusada deixa o CRM com erro, com motivo, e avisa Owner e Admin
- [ ] Com o CRM em erro, Operações ficam pendentes e retidas sem gastar tentativas
- [ ] Reconectar envia as Operações retidas na ordem de criação
- [ ] Operações retidas por mais de 72 horas falham com motivo legível
- [ ] Metadados recarregados ao abrir a tela, diariamente e após falha por referência inexistente
- [ ] Referência quebrada em Agente, mapeamento ou Etapas comerciais deixa o CRM com atenção e lista o que quebrou e onde
- [ ] Com atenção, as Operações que não dependem da referência quebrada continuam funcionando
- [ ] Corrigir as referências tira o CRM de atenção
- [ ] Testes com a API do RD fingida e o adapter falso, controlando o tempo para a retenção
