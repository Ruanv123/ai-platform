# 09: Onboarding com Leitura do site

**What to build:** O onboarding vira uma sequência de passos, com o estado guardado na Empresa: em que passo está e quando terminou. Isso substitui a regra atual de que quem já tem Empresa não entra no onboarding. Quem tem o onboarding pendente volta ao passo em que parou.

1. **Nome e site, ou "não tenho site".** Com site, cria a Empresa com o fuso padrão e inicia a Leitura do site, que conta no limite de 24 horas. Endereços recusados (Instagram, WhatsApp) mostram o erro no próprio passo. Sem site, cria a Empresa e segue para o formulário manual dos Dados da Empresa, que é o atual sem nome e site.
2. **Progresso da Leitura.** A página acompanha a situação por polling e mostra páginas encontradas e lidas. Leitura concluída segue para o passo 3. Leitura com falha segue para o formulário manual, com o motivo.
3. **Dados da Empresa e Voz da marca pré-preenchidos**, com a página de origem ao lado de cada campo. Campos não encontrados ficam vazios. Salvar confirma a proposta com os valores editados.

Neste ticket, salvar o passo 3 ou o formulário manual conclui o onboarding e leva ao dashboard. O passo de Objetivo vem no ticket 11. Ver `.scratch/leitura-do-site/spec.md`.

**Blocked by:** 07

**Status:** ready-for-agent

- [ ] Usuário novo cai no passo 1; quem tem onboarding pendente volta ao passo em que parou; quem concluiu vai ao dashboard
- [ ] Passo 1 com site cria a Empresa e uma Leitura em andamento; sem site, leva ao formulário manual
- [ ] Instagram e WhatsApp são recusados no passo 1 sem requisição externa
- [ ] A tela de progresso reflete a situação da Leitura e avança sozinha ao concluir
- [ ] Leitura com falha leva ao formulário manual com o motivo
- [ ] O passo 3 traz os valores propostos e a origem nas props; salvar grava os Dados da Empresa e a Voz da marca editados e marca a proposta como confirmada
- [ ] Testes de onboarding existentes atualizados para o novo fluxo
