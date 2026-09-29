# 12: Resumo do atendimento

**What to build:** Depois de 30 minutos sem mensagem do Contato num Canal, se ele tem Vínculo e mandou pelo menos 2 mensagens desde o último Resumo daquele Canal, a plataforma gera um Resumo do atendimento com o modelo `gpt-5.6-luna` e o registra como Atividade de nota na Pessoa do CRM e no Negócio aberto mais recente, se houver. O Resumo inclui mensagens do Contato, do Agente e da Equipe; cada janela gera um Resumo novo, sem reescrever o anterior. O custo em IA fica na Operação do CRM do Resumo. A opção fica na tela do CRM, ligada por padrão ao conectar, e pode ser desligada. O trabalho roda na fila `crm-maintenance`. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 06

**Status:** ready-for-agent

- [ ] Após 30 minutos de silêncio, Contato com Vínculo e pelo menos 2 mensagens na janela recebe um Resumo registrado como nota no CRM
- [ ] Contato sem Vínculo ou com menos de 2 mensagens na janela não gera Resumo nem chama a IA
- [ ] O Resumo considera mensagens do Contato, do Agente e da Equipe
- [ ] Uma nova janela gera um novo Resumo; o anterior não é alterado
- [ ] A Operação do Resumo guarda o custo em IA
- [ ] Desligar a opção impede novos Resumos
- [ ] Testes com o fake do SDK de IA e a API do RD fingida, controlando o tempo
