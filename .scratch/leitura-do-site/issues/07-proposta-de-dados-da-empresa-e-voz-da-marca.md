# 07: Proposta de Dados da Empresa e Voz da marca, conferida campo a campo

**What to build:** Ao concluir, a Leitura do site gera uma proposta. Um agente do `laravel/ai` com structured output lê o markdown das páginas e propõe:
- segmento (um dos valores existentes ou vazio);
- descrição do negócio, produtos e serviços, público-alvo;
- telefone, e-mail público e endereço;
- Horário de atendimento, no formato por dia da semana atual;
- Voz da marca: texto e de 2 a 4 frases reais do site.

Cada campo proposto leva a URL de origem. Campo não encontrado fica ausente, nunca inventado. Dados da Empresa e a descrição da Voz da marca saem em pt-BR, e as frases ficam no idioma original. O conteúdo do site é tratado como dado não confiável. A proposta tem situação: pendente, confirmada, descartada ou substituída.

Em Configurações > Empresa, uma proposta pendente é conferida assim:
- aparecem só os campos cujo valor proposto difere do atual, com o valor atual, o proposto e a página de origem;
- aceitar por campo e "aceitar tudo" gravam só os aceitos;
- descartar encerra a proposta;
- nada é sobrescrito sem aceite;
- uma nova Leitura concluída marca a proposta pendente anterior como substituída.

Rodar, confirmar e descartar exigem `UpdateTeam`. Ver `.scratch/leitura-do-site/spec.md`, ADR 0016 e ADR 0017.

**Blocked by:** 02, 03

**Status:** ready-for-agent

- [ ] Leitura concluída gera uma proposta pendente com a origem por campo e sem campos inventados
- [ ] A conferência lista só os campos divergentes do valor atual
- [ ] Aceitar um campo grava só aquele campo; "aceitar tudo" grava todos os divergentes
- [ ] Aceitar a Voz da marca muda as instruções dos Agentes
- [ ] Descartar encerra a proposta, que deixa de aparecer como pendente
- [ ] Nova Leitura concluída marca a proposta pendente anterior como substituída
- [ ] Member não confirma nem descarta; uma Empresa não confirma a proposta de outra
- [ ] Testes com o agente de extração falsificado e proposta verificada nas props da página
