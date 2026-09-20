# 02: Tipo de canal WhatsApp de testes

**What to build:** O caso novo de `ChannelType` para conectar um número por serviço não oficial (Evolution Go), com a sua tabela de conexão própria (endereço do serviço, nome da instância, credencial), seguindo o padrão de tabela específica por Tipo de canal do ADR 0001, com segredos em cast `encrypted`. O Tipo só aparece no catálogo quando `app()->isLocal()` ou o usuário é administrador da plataforma — mesmo par usado pelo `HorizonServiceProvider`. Tela de configuração mínima, sem QR code e sem ciclo de vida de instância. Referência: ADR 0002; spec, user stories 37, 39, 40.

**Blocked by:** nada

**Status:** done

- [x] Caso novo em `ChannelType`, com rótulo **WhatsApp de testes**, descrição e ícone
- [x] Catálogo esconde o Tipo fora de ambiente local e de administrador da plataforma
- [x] Migration, model e factory da conexão do Canal de testes, com segredos `encrypted`
- [x] Configuração do Canal salva a conexão e move o `ChannelStatus` como o WhatsApp oficial já faz
- [x] Empresa pode ter o Canal oficial e o de testes ao mesmo tempo
- [x] Testes: catálogo escondido para cliente comum, visível para administrador e em local; configuração salva; os dois Canais coexistindo

## Comments

- Implementado no commit `93728eb` (Implement WhatsApp test channel configuration).
