# Plataforma de Agentes de IA para Atendimento

Plataforma SaaS multi-tenant onde uma empresa brasileira configura agentes de IA que atendem seus clientes nos canais de mensageria, respondendo a partir do conhecimento real do negócio. Cada conta é isolada e pertence a exatamente uma Empresa.

## Language

### Conta e pessoas

**Empresa**:
O tenant da plataforma: o negócio que contrata o produto e dono de tudo que é criado dentro dele. Implementada pelo model `Team`.
_Avoid_: Time, Organização, Conta, Workspace, Tenant

**Equipe**:
As pessoas que têm acesso a uma Empresa, cada uma com um Papel.
_Avoid_: Membros, Usuários, Time

**Papel**:
O nível de acesso de uma pessoa dentro da Empresa: Owner, Admin ou Member.
_Avoid_: Permissão, Role, Cargo

### Agente

**Agente**:
Uma configuração de atendimento pertencente à Empresa — persona, objetivo, estilo, instruções e Coleções consultáveis. Uma Empresa pode ter vários, e o mesmo Agente pode atender em vários Canais.
_Avoid_: Bot, Assistente, Chatbot, Persona

**Instrução**:
Uma orientação escrita pelo cliente que entra no prompt do Agente. Formam uma lista ordenada.
_Avoid_: Regra, Prompt, Diretriz

**Playground**:
O chat de teste do Agente. Roda sobre o rascunho do formulário, não sobre a configuração salva, e nada é persistido.
_Avoid_: Simulador, Preview, Sandbox

**Agente inativo**:
Um Agente que não responde nos Canais vinculados, mas continua utilizável no Playground.
_Avoid_: Agente desligado, Agente pausado

### Base de conhecimento

**Coleção**:
Um agrupamento de Documentos dentro da Empresa. É a unidade que um Agente escolhe consultar.
_Avoid_: Base de conhecimento, Pasta, Categoria, Knowledge base

**Base de conhecimento**:
O conjunto de todo o conhecimento da Empresa. É o nome da seção, nunca de uma Coleção individual.

**Documento**:
Uma fonte de conhecimento dentro de uma Coleção: arquivo, texto livre, FAQ ou URL. É processado em segundo plano e vira Trechos pesquisáveis.
_Avoid_: Arquivo, Fonte, Conteúdo

**Trecho**:
O pedaço de um Documento, com embedding, que a busca semântica retorna. Implementado pelo model `Chunk`.
_Avoid_: Chunk, Fragmento, Pedaço

### Canais

**Canal**:
A conexão configurada de uma Empresa com um meio de atendimento — uma linha em `channels`. Aponta para no máximo um Agente, e é o Canal que sabe se o atendimento está funcionando.
_Avoid_: Conexão, Integração, Canal de atendimento

**Tipo de canal**:
O meio de atendimento em si (WhatsApp, Chat no site, Instagram Direct), independente de qualquer configuração. O catálogo da tela de Canais é gerado a partir dos Tipos de canal.
_Avoid_: Canal, Provedor, Plataforma

**Conta do WhatsApp**:
As credenciais de app da Meta usadas pelo Canal: WABA ID, tokens, verify token e o estado de verificação do webhook.
_Avoid_: WABA, Conta Meta, Business Account

**Número do WhatsApp**:
O número que recebe e responde mensagens, com sua identidade e qualidade. É a ele que o Canal se prende.
_Avoid_: Telefone, WhatsApp, Linha

**Integração**:
Uma conexão com um CRM externo, para onde as oportunidades do atendimento são enviadas. É outra coisa que um Canal: Canal traz a conversa, Integração leva o resultado.
_Avoid_: Conector, Canal, App
