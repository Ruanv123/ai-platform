# 13: Gatilho de webhook

**What to build:** O sistema do cliente — agenda, CRM, ERP — chama um endereço e um atendimento começa. O endereço é do Canal, e os Fluxos daquele Canal que declaram o nome de evento correspondente é que respondem. O payload vira Variáveis da Execução, de forma que a primeira mensagem já saia com os dados que o sistema mandou.

**Blocked by:** 12

**Status:** completed

- [x] Rota de gatilho por Canal, distinta e sem relação com o webhook da Meta, que não muda
- [x] Cada Fluxo declara um nome de evento que o payload precisa bater por igualdade; sem sobreposição, sem regra de desempate
- [x] Autenticação opcional por token de cabeçalho, que autentica a rota e não libera capacidade
- [x] Rate limit em três camadas: por Canal, por Empresa e teto global da plataforma
- [x] O payload inteiro vira Variáveis da Execução
- [x] Telefone que não corresponde a Contato conhecido é recusado por padrão, com a alternativa declarada no gatilho
- [x] O risco de a rota ficar protegida só por rate limit está registrado, na mesma natureza do risco aceito no ADR 0002
- [x] Teste dirige pela rota nova, no mesmo formato dos testes de webhook existentes
