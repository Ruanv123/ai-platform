# 11: Instalação iniciada pela Shopify e app embedded

**What to build:** O lojista instala o app pelo admin da Shopify e liga a loja a uma Empresa sem se perder. O app é embedded, com App Bridge e session token, sem cookies de terceiros, e passa pelo OAuth antes de qualquer tela. Depois do OAuth, a tela embedded mostra "conectar a uma Empresa" e abre a plataforma em nova aba com um token de instalação de uso único e curta duração, ligado à instalação pendente. A pessoa faz login ou cadastro e escolhe a Empresa, só entre as que é Owner ou Admin. Se a Empresa já tem Loja, a tela avisa que a atual será desconectada e seu espelho apagado, e pede confirmação. Depois da ligação, a tela embedded mostra o estado da Loja e um atalho para a plataforma. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Abrir o app na Shopify sem instalação conclui o OAuth antes de mostrar qualquer tela
- [ ] A tela embedded autentica por session token e funciona sem cookies de terceiros
- [ ] O token de instalação liga a instalação pendente à Empresa escolhida; expirado ou reutilizado é recusado
- [ ] Quem não tem conta faz cadastro e volta à ligação sem perder a instalação
- [ ] Só Empresas em que a pessoa é Owner ou Admin aparecem na escolha
- [ ] Empresa com Loja recebe o aviso, e confirmar desconecta a Loja atual antes de ligar a nova
- [ ] Depois da ligação, a tela embedded mostra estado da Loja e atalho para a plataforma
