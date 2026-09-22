# Atendimento em tempo real

A caixa de entrada de exceção usa Laravel Reverb para atualizar a lista e a conversa sem recarregar a página.

Em produção:

1. Configure `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`, `REVERB_HOST`, `REVERB_PORT` e `REVERB_SCHEME` no ambiente da aplicação.
2. Configure as variáveis `VITE_REVERB_*` com os mesmos valores públicos e gere os assets com `npm run build`.
3. Mantenha um processo separado do PHP web e dos workers executando:

   ```sh
   php artisan reverb:start --host=0.0.0.0 --port=${REVERB_PORT:-8081}
   ```

4. Inclua esse processo no supervisor do provedor, com reinício automático e logs. O scheduler continua necessário para `inbox:release-stale` e os demais comandos agendados.

O canal `team.{id}.inbox` é privado e só autoriza pessoas que pertencem à Empresa correspondente. O dashboard de Execuções não usa Reverb: continua usando polling.
