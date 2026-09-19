# Sem Conversa como entidade: o Contato é a unidade do atendimento

Ao modelar o atendimento real, optamos por não ter uma tabela de conversas. Existem **Contato** e **Mensagem**; o **Histórico** que o Agente lê é uma consulta às Mensagens daquele Contato naquele Canal, cortada no primeiro intervalo maior que 24 horas e com teto de mensagens.

## Considered Options

- **Conversa como entidade, com ciclo de vida** (aberta, encerrada por inatividade): rejeitada porque o único comportamento que ela carregaria é "onde cortar o histórico", e isso é uma regra de leitura — não justifica uma entidade, sua criação, seu encerramento e a decisão de a qual conversa cada mensagem pertence.

## Consequences

- O corte das 24 horas vive na consulta do Histórico, não no banco. Mudar a regra não exige migração nem reprocessamento.
- Estado de atendimento que precisaria de um "lugar" (por exemplo, humano assumindo o atendimento) terá que morar no Contato ou numa entidade nova e explícita, não numa Conversa implícita.
- Contato é por Empresa (Empresa + telefone), então a mesma pessoa em dois Canais é um só Contato com dois Históricos.
