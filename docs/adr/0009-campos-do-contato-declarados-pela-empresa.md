# Campos do Contato declarados pela Empresa

Status: superseded by ADR-0018

O Contato deixa de ser só identificador — Empresa mais telefone, mais o nome vindo do WhatsApp — e passa a carregar **Campos** que a Empresa declara: nome, tipo (texto, número, data, escolha) e valor por Contato. Junto vêm **Tags**, marcas livres sem valor. Ambos sobrevivem entre Execuções, diferente das Variáveis, que morrem com a Execução que as criou.

## Considered Options

- **Só Variáveis de Execução**: rejeitada porque o Contato volta no dia seguinte e a plataforma não sabe nada sobre ele. Toda Execução recomeçaria perguntando o convênio, o CPF e a cidade.
- **Colunas fixas no `contacts`**: rejeitada porque o que uma clínica precisa guardar não é o que uma oficina precisa, e cada cliente novo viraria uma migração.

## Consequences

- `Contact` passa a ser a ficha do cliente dentro da Empresa, e não apenas quem fala. Isso muda o peso da tabela e o que significa apagar um Contato.
- Campos do Contato são o que alimenta condição de Gatilho, personalização de mensagem, variável de Template e corpo de requisição HTTP. Passam a ser dependência de quase todo Nó.
- Dado pessoal passa a ser declarado pela Empresa, o que torna o mascaramento de campos sensíveis no Traço requisito e não enfeite.
- Existe também escopo de **Variáveis globais da Empresa** — constantes, endereços de API, textos reaproveitados — para que não seja preciso repetir o mesmo valor em dez Nós.
