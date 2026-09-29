# 16: Template com cabeçalho de mídia

**What to build:** A Empresa cria um Template com imagem ou documento no cabeçalho ("seu exame ficou pronto" com o PDF) e o envia pela conversa escolhendo o arquivo daquele Contato.

**Blocked by:** 09, 14

**Status:** resolved

- [x] A criação de Template aceita cabeçalho de imagem ou documento com arquivo de exemplo
- [x] O exemplo é enviado pela Resumable Upload API (com o `app_id` da Forma de conexão) e o `media_handle` e a cópia local ficam no componente de header
- [x] A submissão à Meta usa o `media_handle`
- [x] Na conversa, enviar um Template com header de mídia exige o upload do arquivo, que segue o fluxo de `/media` do ticket 14
- [x] Validação de formato e tamanho em português
- [x] Templates com header de vídeo sincronizados da Meta aparecem na lista, mas o envio fica indisponível com aviso
- [x] Testes pelas rotas de Templates e da conversa com `Http::fake`
