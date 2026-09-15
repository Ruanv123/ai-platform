# 03: Onboarding da Empresa

**What to build:** Depois do cadastro, o usuário sem Empresa é levado a um onboarding que coleta os dados do negócio e cria a Empresa com ele como Owner. Enquanto não tiver Empresa, qualquer página autenticada redireciona para o onboarding; quem já tem Empresa não acessa o onboarding. Os dados ficam editáveis em Configurações > Empresa (Owner e Admin editam, Member só visualiza). O usuário ganha a flag `is_platform_admin`. Referência: spec, user stories 1–11 e seção Tenancy e autenticação.

**Blocked by:** 02 (Uma Empresa por usuário)

**Status:** ready-for-agent

- [ ] Cadastro não cria mais team pessoal nem usa o nome do usuário como nome de team
- [ ] Middleware redireciona usuário autenticado sem Empresa para o onboarding em qualquer rota da aplicação
- [ ] Onboarding coleta nome fantasia, segmento (select), site, descrição do negócio, produtos/serviços, público-alvo, horário de atendimento por dia da semana, fuso horário e contatos públicos (telefone, e-mail, endereço)
- [ ] Concluir o onboarding cria a Empresa e vincula o usuário como `Owner` numa transação e leva ao Dashboard
- [ ] Usuário com Empresa não acessa o onboarding nem consegue criar uma segunda Empresa
- [ ] Configurações > Empresa permite Owner e Admin editarem os dados; Member vê em modo leitura e recebe 403 ao tentar salvar
- [ ] Validação com mensagens claras
- [ ] Coluna booleana `is_platform_admin` no usuário, padrão falso
- [ ] Feature tests cobrindo redirecionamento, criação, bloqueio de segunda Empresa e permissões de edição
