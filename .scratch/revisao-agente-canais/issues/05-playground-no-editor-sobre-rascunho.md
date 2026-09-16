# 05: Playground no editor, sobre rascunho

**What to build:** O chat de teste entra no editor, ao lado do formulário, e passa a rodar sobre o que está no formulário **agora** — não sobre a configuração salva. Ajustar o tom, mandar uma mensagem, ajustar de novo e só então salvar vira um único gesto, na mesma tela, e funciona inclusive para um Agente que ainda não existe. A mensagem de boas-vindas acompanha o que está sendo digitado enquanto o chat estiver vazio, e o histórico não é apagado quando a configuração muda, porque comparar a resposta antiga com a nova é o ponto. As fontes consultadas ficam recolhidas sob a resposta que elas justificaram. Referência: spec, user stories 8–16 e 20–23.

**Blocked by:** 04 (Editor do Agente fora do modal)

**Status:** ready-for-agent

- [ ] Rota única de streaming, sem binding de Agente, recebendo a configuração completa do formulário mais o histórico
- [ ] Payload validado com as mesmas regras do formulário de Agente, incluindo Coleções restritas à Empresa do usuário; autorização por pertencer a uma Empresa
- [ ] Agente de IA montado a partir de uma instância não persistida, com Empresa e Coleções setadas; template de instruções e ferramenta de busca inalterados
- [ ] Chat renderizado ao lado do formulário, colapsando em abas em telas estreitas
- [ ] Mensagem de boas-vindas acompanha o formulário enquanto o chat estiver vazio
- [ ] Histórico preservado quando a configuração muda; reiniciar continua manual
- [ ] Fontes consultadas em collapsible sob cada resposta, com indicação quando a Base de conhecimento não foi consultada
- [ ] Limite de uso sobe para 60 mensagens por hora por usuário, com mensagem em pt-BR
- [ ] Erro ou timeout do provedor vira mensagem amigável no chat
- [ ] Aviso de alterações não salvas e a página separada de Playground removidos
- [ ] Feature tests: streaming com rascunho de Agente inexistente, prompt reflete o rascunho e não a configuração salva, Coleção de outra Empresa recusada, Member consegue usar, erro do provedor tratado, limite dispara na mensagem 61
