# 02: Uma Empresa por usuário: rotas sem slug e sem verificação de e-mail

**What to build:** Prefactor da tenancy. A Empresa (`Team`) corrente passa a ser sempre a única Empresa do usuário logado. As rotas perdem o prefixo `{current_team}` (ex.: `/dashboard`), somem a troca de team, a criação de team pela tela e o seletor de team. A verificação de e-mail é desligada (feature do Fortify e middleware `verified`). Reset de senha, 2FA e passkeys continuam funcionando. Nesta etapa o cadastro ainda pode criar o team pessoal (isso muda no 03). Referência: spec, seção Tenancy e autenticação.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] Rotas autenticadas não têm mais o slug do team na URL; o middleware de URL defaults é removido
- [x] A Empresa corrente é resolvida a partir do usuário logado em todo lugar que antes usava o slug
- [x] Rotas e telas de criar team, trocar team e seletor de team removidas
- [x] Feature de verificação de e-mail desligada; nenhuma rota exige `verified`
- [x] Usuário recém-cadastrado acessa a aplicação sem confirmar e-mail
- [x] Reset de senha, 2FA e passkeys seguem funcionando
- [x] Testes do starter kit afetados atualizados para o novo comportamento (não apagados); testes de verificação de e-mail ajustados para refletir a feature desligada
- [x] Wayfinder regenerado e frontend compila sem referências a rotas removidas
