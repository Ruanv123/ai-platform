# 08: Webhooks do RD Station CRM

**What to build:** Ao conectar, a plataforma registra pela API do RD Station CRM os webhooks de contato (atualizado, apagado) e de negócio (criado, atualizado, apagado), com uma chave aleatória por CRM enviada pelo RD no header de autenticação; só o hash da chave é guardado. Os webhooks chegam num endpoint próprio do provedor, fora do grupo autenticado, com throttle e sem identificador do CRM na URL (ADR 0012). O controller pede ao adapter que valide e identifique: chave inválida recebe 401; CRM desconhecido ou desconectado recebe 200 e é descartado; válido recebe 200 e enfileira um aviso canônico (tipo de recurso, id externo, apagado ou não) na fila `crm-webhooks`, sem guardar o payload. O job trata o webhook como aviso (ADR 0014): busca o estado atual na API, com debounce por (CRM, tipo, id externo). Pessoa do CRM apagada — inclusive o lado perdedor de um merge — desfaz o Vínculo e apaga os Negócios vinculados dela. Negócio criado ou atualizado de Pessoa do CRM com Vínculo atualiza o Negócio vinculado; de Pessoa do CRM sem Vínculo, busca a Pessoa do CRM, normaliza o telefone e, havendo Contato existente com ele, cria o Vínculo; sem Contato, descarta — nunca cria Contato. Negócio apagado apaga o Negócio vinculado. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] Conectar registra os cinco webhooks no RD com a chave do CRM; reconectar não duplica inscrições
- [ ] Chave inválida recebe 401; CRM desconhecido ou desconectado recebe 200 sem efeito; nada do payload é armazenado
- [ ] Avisos repetidos do mesmo recurso numa janela curta resultam numa única busca na API
- [ ] Pessoa do CRM apagada desfaz o Vínculo e apaga os Negócios vinculados dela
- [ ] Troca de telefone da Pessoa do CRM não desfaz o Vínculo
- [ ] Negócio criado ou atualizado de Pessoa do CRM vinculada atualiza Etapa do funil, Situação, valor e Responsável do Negócio vinculado
- [ ] Negócio de Pessoa do CRM sem Vínculo cria o Vínculo quando existe Contato com o mesmo telefone, e é descartado quando não existe; nenhum Contato é criado
- [ ] Negócio apagado apaga o Negócio vinculado
- [ ] Webhook causado por Operação da própria plataforma só confirma o estado já guardado
- [ ] Testes pelo endpoint com fixtures reais de webhook do RD e a API fingida; fixtures de webhook e de registro de inscrição na suíte de conformidade
