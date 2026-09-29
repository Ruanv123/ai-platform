# 05: Linktree seguido até o site próprio

**What to build:** Quando o endereço informado é um Linktree, a Leitura do site lê a página do Linktree e segue o primeiro link que seja um site próprio, ignorando redes sociais e WhatsApp. A Leitura guarda o endereço informado e o resolvido, e o bloco da Leitura mostra qual site foi lido de fato. Se não houver site aproveitável, a Leitura falha com um motivo claro, que orienta a informar o site direto ou preencher à mão. Ver `.scratch/leitura-do-site/spec.md`.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Linktree com um site próprio entre os links é lido como aquele site
- [ ] Links de Instagram, outras redes sociais e WhatsApp no Linktree são ignorados
- [ ] Linktree sem site próprio deixa a Leitura com falha e motivo claro
- [ ] A Leitura guarda e mostra o endereço informado e o resolvido
