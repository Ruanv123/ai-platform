# 01: Preparação: enum Weekday e ordem do submenu

**What to build:** Dois prefactors que não mudam nada do que o usuário consegue fazer, mas deixam os próximos tickets baratos.

O submenu de Configurações passa a listar Perfil, Segurança, Aparência, Empresa — nessa ordem, com as configurações da pessoa antes das do negócio. Os tickets seguintes só acrescentam seus itens no fim, sem reordenar nada. O cabeçalho da tela, que hoje fala apenas do perfil, passa a cobrir conta e Empresa, porque ele já vai encimar abas que não são sobre a pessoa.

Por baixo, a lista de dias da semana deixa de ser um método estático num controller de onboarding e vira um enum de domínio, com rótulo e opções, espelhando o que já existe para segmento. A lista de segmentos passa a ser consumida direto do enum dela. Nenhum controller precisa mais importar outro controller para montar props.

Os valores do enum são exatamente as chaves já gravadas no horário de atendimento de cada Empresa, então **nada no banco muda e não há migração**. Onboarding e edição da Empresa continuam funcionando exatamente como hoje.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] Submenu de Configurações na ordem Perfil, Segurança, Aparência, Empresa, em lista plana, sem separador nem título de grupo
- [x] Cabeçalho de Configurações descreve conta e Empresa, não só o perfil
- [x] Existe um enum de dias da semana com rótulo em português e método de opções, nos moldes do enum de segmento
- [x] Os valores do enum são idênticos às chaves já usadas no horário de atendimento; nenhuma migração é criada
- [x] A lista de dias e a de segmentos deixam de ser expostas por métodos estáticos no controller de onboarding
- [x] Nenhum controller importa outro controller para montar props
- [x] Onboarding da Empresa continua salvando horário, fuso e segmento sem mudança de comportamento
- [x] Os testes existentes de onboarding e de Empresa passam sem alteração de expectativa
