# 03: Calendários agendáveis

**What to build:** Com a Agenda conectada, a tela da Agenda lista os Calendários da conta Google. O Owner ou Admin libera um Calendário para agendamento e edita suas regras: nome e descrição de exibição, duração do compromisso (padrão 30 min), antecedência mínima (padrão 2 h) e janela, que por padrão é o Horário de atendimento da Empresa e pode ser personalizada no mesmo formato. Aqui nasce o cliente do Google Calendar, que renova o access token pelo refresh token e persiste o novo. Quando o Google revoga o acesso (`invalid_grant`), a Agenda fica com erro "Reconecte a conta Google" e a tela oferece reconectar. Desconectar a Agenda apaga os Calendários agendáveis. Ver `.scratch/agenda-google/spec.md`.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] A tela da Agenda lista os Calendários vindos do Google
- [ ] Liberar um Calendário cria um Calendário agendável com os padrões
- [ ] Editar nome, descrição, duração, antecedência mínima e janela própria
- [ ] Voltar a janela para o Horário de atendimento da Empresa
- [ ] Deixar de liberar um Calendário remove o Calendário agendável
- [ ] Access token expirado é renovado e persistido antes da chamada
- [ ] `invalid_grant` deixa a Agenda com erro e a tela mostra "Reconectar"
- [ ] Desconectar a Agenda apaga os Calendários agendáveis
- [ ] Calendário agendável de outra Empresa é inacessível
- [ ] Testes com `Http::fake` para a Calendar API e o endpoint de token
