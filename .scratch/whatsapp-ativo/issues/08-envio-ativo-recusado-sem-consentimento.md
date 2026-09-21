# 08: Envio ativo recusado sem consentimento

**What to build:** O motor recusa iniciar conversa com quem não consentiu naquela finalidade — e a recusa é do motor, não do desenho, porque depender de a Empresa ter lembrado de checar é como não ter proteção nenhuma. O risco que isso evita é o cliente perder o número dele por culpa da plataforma.

**Blocked by:** 06, 05

**Status:** ready-for-agent

- [ ] Envio ativo sem consentimento da finalidade é recusado pelo motor
- [ ] A recusa fica registrada no traço com motivo explícito, não em silêncio
- [ ] Resposta dentro da janela de 24 horas não é afetada por esta regra
- [ ] O Nó de Enviar Template declara a finalidade do envio
- [ ] Teste cobre cada finalidade, concedida e revogada
