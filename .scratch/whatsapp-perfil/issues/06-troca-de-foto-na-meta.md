# 06: Troca de foto na Meta

**What to build:** Na aba Perfil do WhatsApp oficial, Owner e Admin escolhem uma imagem e recortam num quadrado, arrastando e dando zoom. Depois veem a prévia e confirmam. A foto é aplicada na Meta na hora, independente do "Salvar" dos textos. Sem App ID, a aba avisa e mostra um link para a Conexão. Ver `.scratch/whatsapp-perfil/spec.md`.

**Blocked by:** 01 (Abas Conexão | Perfil e leitura do Perfil na Meta), 05 (App ID na Conexão)

**Status:** ready-for-agent

- [ ] `react-easy-crop` adicionado (dependência aprovada)
- [ ] Diálogo de recorte, no próprio arquivo, com arrastar, zoom, prévia e confirmação. Gera um JPEG quadrado de 640 px enviado por multipart
- [ ] Rejeição no frontend de arquivo que não seja JPEG/PNG ou que passe de 5 MB
- [ ] Validação no servidor: JPEG/PNG, até 5 MB, quadrada, de 192 a 640 px (dimensões lidas sem GD)
- [ ] O contrato ganha "trocar a foto". A implementação Meta abre a sessão em `/{app_id}/uploads`, envia os bytes com `Authorization: OAuth` e `file_offset: 0` e aplica o `profile_picture_handle`
- [ ] Sem App ID: o bloco de foto mostra um aviso com link para `?tab=conexao`, e o servidor recusa a troca
- [ ] Sucesso: toast e foto nova visível na aba. Falha: toast de erro, sem alterar o `ChannelStatus`
- [ ] Permissões: Owner e Admin trocam, Member recebe 403 e não vê a ação
- [ ] Testes Pest cobrem a sequência sessão → bytes → handle (`Http::assertSent`), o bloqueio sem App ID, a validação e as permissões
- [ ] Testes Vitest cobrem o fluxo escolher → recortar → prévia → confirmar (com o canvas mockado), a rejeição de arquivo e o aviso de App ID
