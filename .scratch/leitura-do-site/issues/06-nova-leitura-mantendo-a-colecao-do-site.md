# 06: Nova Leitura mantendo a Coleção do site

**What to build:** Uma nova Leitura do site reconcilia a Coleção do site, e só mexe no que a Leitura criou:
- página que continua escolhida é reprocessada no mesmo Documento;
- Documento vindo da Leitura cuja página não foi mais escolhida ou não existe mais é removido;
- Documento vindo da Leitura que a Equipe apagou deixa a URL registrada como ignorada, e ela não volta nas próximas Leituras;
- Documentos adicionados à mão na Coleção do site nunca são tocados;
- se a Equipe apagou a Coleção do site inteira, a próxima Leitura cria outra.

Quando o site salvo nos Dados da Empresa difere do endereço da última Leitura, o bloco mostra a oferta "ler o novo site", respeitando o limite de 24 horas. Trocar o site nunca dispara Leitura sozinho, e as páginas do domínio antigo contam como sumidas. O FAQ do site segue as mesmas regras quando existir. Ver `.scratch/leitura-do-site/spec.md` e o termo Coleção do site no `CONTEXT.md`.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Página mantida é reprocessada no mesmo Documento, sem duplicar
- [ ] Página sumida tem o Documento vindo da Leitura removido
- [ ] Documento vindo da Leitura apagado pela Equipe não volta nas Leituras seguintes
- [ ] Documento adicionado à mão na Coleção do site continua intacto
- [ ] Coleção do site apagada é recriada na próxima Leitura
- [ ] Site salvo diferente do da última Leitura mostra "ler o novo site", e salvar o site não dispara Leitura
- [ ] Nova Leitura de outro domínio remove os Documentos do domínio antigo vindos da Leitura
