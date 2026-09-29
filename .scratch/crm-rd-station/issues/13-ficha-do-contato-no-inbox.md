# 13: Ficha do Contato no Inbox

**What to build:** Na ficha do Contato no atendimento, um bloco do CRM mostra: a situação do Vínculo com o atalho "abrir no CRM"; os Negócios abertos (Funil de vendas, Etapa do funil, valor e Responsável), lidos ao vivo ao abrir a ficha com cache curto e, se o CRM não responde, os Negócios vinculados guardados; as últimas Operações do CRM daquele Contato; a indicação de Pausa da IA pelo CRM com o Negócio que a causou; e o botão "desfazer Vínculo". Desfazer apaga o Vínculo e os Negócios vinculados do Contato e encerra uma pausa de origem CRM; a próxima necessidade refaz a busca por telefone. Sem CRM conectado, o bloco não aparece. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 05, 09

**Status:** ready-for-agent

- [ ] Contato com Vínculo mostra o atalho para a Pessoa do CRM no RD Station
- [ ] Negócios abertos são lidos ao vivo; com o CRM indisponível, aparecem os Negócios vinculados guardados
- [ ] As últimas Operações do CRM do Contato aparecem com situação e motivo
- [ ] Contato pausado pelo CRM mostra a pausa e o Negócio que a causou
- [ ] "Desfazer Vínculo" apaga Vínculo e Negócios vinculados e encerra a pausa de origem CRM
- [ ] Sem CRM conectado, o bloco não aparece
- [ ] Dados de uma Empresa nunca aparecem em outra
- [ ] Testes pelos controllers do Inbox com a API do RD fingida
