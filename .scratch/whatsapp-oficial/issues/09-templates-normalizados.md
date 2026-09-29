# 09: Templates normalizados (prefactor)

**What to build:** A estrutura dos Templates passa a ter componentes, variáveis e botões em tabelas próprias, sem JSON. Sincronizar, criar, submeter, listar, ver detalhe e enviar pela conversa continuam funcionando como hoje. Ver spec `../spec.md`, seção "Esquema".

**Blocked by:** 01

**Status:** resolved

- [x] Tabelas `whatsapp_template_components` (type, format, text, media_path, media_handle, position), `whatsapp_template_variables` (position, example) e `whatsapp_template_buttons` (type, text, url, phone_number, example, position)
- [x] `whatsapp_templates` ganha `quality_rating` e perde `body`, `header_type`, `header_text`, `footer`, `buttons` e `variables`
- [x] `whatsapp_template_histories` perde `details`
- [x] A sincronização popula componentes, variáveis e botões a partir da Meta
- [x] A submissão monta o payload da Meta a partir da estrutura normalizada
- [x] O envio de Template pela conversa e pelo nó Enviar Template lê as variáveis da nova estrutura
- [x] A tela de Templates (lista, detalhe, criação) funciona com a nova estrutura, incluindo botões de resposta rápida, URL, telefone e copiar código
- [x] `WhatsAppTemplateTest` e os testes de envio de Template continuam verdes
