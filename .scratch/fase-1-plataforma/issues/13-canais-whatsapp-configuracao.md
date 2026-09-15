# 13: Canais: configuração do WhatsApp

**What to build:** Nova área Canais com o card WhatsApp (configurável) e o card "Chat no site" (em breve). Owner e Admin cadastram o número do WhatsApp Cloud API com WABA ID, Phone Number ID, access token permanente e App Secret (segredos criptografados, nunca devolvidos ao frontend, apenas mascarados). A plataforma gera o verify token e exibe a URL do webhook com botão de copiar. "Testar conexão" consulta a Graph API e mostra número, nome verificado e quality rating, ou erro traduzido. Limite de 1 número por Empresa. O número pode ser vinculado a um Agente; excluir o Agente desvincula o número automaticamente (com aviso antes). Member vê status e Agente vinculado, sem credenciais. Referência: spec, user stories 64–65, 81–94 e seção Canais: WhatsApp.

**Blocked by:** 11 (Agentes CRUD)

**Status:** ready-for-agent

- [ ] Model de número de WhatsApp pertencente à Empresa (hasMany no banco), com método de conexão (`manual`), credenciais com cast criptografado, verify token único global gerado, Agente vinculado opcional, status (`unverified`, `connected`, `error`), último erro, dados da Meta, data do último teste e data de verificação do webhook
- [ ] `TeamPermission` ganha `channel:manage` (Owner e Admin)
- [ ] Item "Canais" na sidebar com cards WhatsApp e "Chat no site" (em breve)
- [ ] Cadastro e edição de credenciais; segredo vazio na edição mantém o valor atual; segredos nunca presentes nas props
- [ ] Validação impede um segundo número na mesma Empresa
- [ ] URL do webhook e verify token exibidos com botão de copiar
- [ ] "Testar conexão" via Graph API (versão em configuração) grava status e dados retornados; erros da Meta traduzidos para pt-BR
- [ ] Vincular, trocar e remover Agente; remover o número
- [ ] Excluir Agente vinculado desvincula o número na mesma operação; UI de exclusão do Agente avisa
- [ ] Feature tests com `Http::fake()`: cadastro, limite de 1, segredos ausentes nas props, edição preservando segredos, teste de conexão com sucesso e erro, vínculo e desvinculação ao excluir Agente, permissões e isolamento
