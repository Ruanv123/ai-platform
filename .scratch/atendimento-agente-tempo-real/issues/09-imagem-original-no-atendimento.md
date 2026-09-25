# 09: Exibir imagens originais no atendimento

**What to build:** A Equipe vê a imagem que o Contato enviou nos dois Tipos de canal WhatsApp, junto de legenda e descrição. A mídia nova fica acessível apenas à Empresa e acompanha a existência da Mensagem.

**Blocked by:** 02 — Mostrar todas as Conversas na caixa de entrada.

**Status:** done

- [x] Imagens novas recebidas pelo WhatsApp oficial e de testes são guardadas em armazenamento privado e ligadas à Mensagem, mantendo a legenda e a descrição já usada pelo Agente.
- [x] A Conversa mostra prévia e abertura da imagem com acesso autenticado e escopado pela Empresa; uma pessoa de outra Empresa recebe negação sem revelar o arquivo.
- [x] Reentrega do evento do provedor não duplica Mensagem nem mídia; arquivo removido ou indisponível mostra estado claro e preserva o texto derivado.
- [x] A exclusão da Mensagem remove sua mídia; imagens antigas continuam com a descrição disponível, sem tentativa de recuperação retroativa.
- [x] Testes de funcionalidade exercitam ambos os provedores, armazenamento simulado, autorização, reentrega e indisponibilidade do arquivo.
