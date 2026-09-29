# 11: Conectar com a Meta (Tech Provider)

**What to build:** A Empresa clica em "Conectar com a Meta", escolhe na janela da Meta a Conta do WhatsApp e o número, e volta com o Canal conectado, Templates sincronizados e Agente escolhível. O App próprio passa a ser um link secundário na mesma tela. Ver spec `../spec.md`, seção "Conexão pelo Tech Provider", e ADR 0012.

**Blocked by:** 03, 09

**Status:** resolved

- [x] A tela do WhatsApp tem "Conectar com a Meta" como ação principal e "Usar meu próprio App da Meta" como link secundário para o formulário atual
- [x] O popup do cadastro incorporado (SDK JS da Meta, com o `config_id` da plataforma) devolve só o `code`, que o front envia ao backend
- [x] O backend troca o `code` pelo business token e descobre `waba_id` e `phone_number_id` via `debug_token`, sem confiar em IDs vindos do front
- [x] O backend cria Conta `tech_provider`, Número e Canal, chama `POST /{waba}/subscribed_apps` (grava `subscribed_at`) e `POST /{phone}/register` com PIN de 6 dígitos gerado e criptografado (grava `registered_at`)
- [x] Busca nome exibido, qualidade e limite do número e sincroniza os Templates
- [x] Sucesso deixa o Canal `Connected`; falha em qualquer passo deixa o Canal em `Error` com `last_error` em português
- [x] A tela mostra o andamento e o resultado da conexão
- [x] Testes pela rota que recebe o `code` com `Http::fake`, conferindo a ordem das chamadas à Graph API e o estado gravado; o popup é validado manualmente
