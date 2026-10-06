# CRM sem espelho: Vínculos, metadados e estado mínimo dos Negócios vinculados

Status: superseded by ADR-0018

O CRM é a fonte da verdade dos seus registros, e a plataforma não guarda cópia de Pessoas do CRM, Organizações do CRM nem Atividades. Guarda só três coisas: o **Vínculo** entre Contato e Pessoa do CRM, os **metadados de configuração** do CRM (Funis de vendas, Etapas do funil, Campos do CRM, Responsáveis, Motivos de perda) e um **estado mínimo dos Negócios vinculados** (Funil de vendas, Etapa do funil, Situação, valor e Responsável). Registros são lidos e escritos ao vivo no Provedor de CRM por um adapter com contrato único. É um meio-termo deliberado entre a Loja, espelhada inteira (ADR 0013), e não guardar nada.

## Considered Options

- **Espelho completo, como a Loja**: rejeitada. A Loja espelha porque a busca semântica precisa de embeddings locais sobre um catálogo que muda pouco e só num sentido. O CRM é editado por humanos o tempo todo e escrito pela plataforma, então um espelho exigiria resolver conflito de escrita dos dois lados, e uma base de centenas de milhares de Pessoas do CRM não tem uso no atendimento.
- **Nenhum estado local, nem dos Negócios**: rejeitada. Pausar a IA quando um Negócio vinculado entra numa Etapa comercial e dar ao Agente o estado do Negócio a cada turno exigem saber a Etapa anterior e não chamar a API em todo turno, e nem todo Provedor de CRM manda o valor anterior no webhook.

## Consequences

- O webhook do CRM é só um aviso, como no ADR 0013: o job busca o estado atual na API, atualiza o estado mínimo e só então calcula se o Negócio entrou ou saiu de uma Etapa comercial. Ordem, duplicata e formato do payload deixam de importar.
- Negócio de Pessoa do CRM sem Vínculo tenta achar um Contato existente pelo telefone; sem Contato, é descartado. A plataforma nunca cria Contato a partir do CRM.
- Metadados envelhecem sem aviso, porque o RD Station CRM não tem webhook para funis, etapas nem campos. São recarregados ao conectar, ao abrir a tela do CRM, diariamente e quando uma Operação do CRM falha por referência inexistente.
- Desconectar apaga Vínculos, metadados e estado mínimo, e nunca apaga nada no CRM da Empresa.
