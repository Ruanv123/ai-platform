# Leitura do site com Firecrawl só lendo e extração própria

Na Leitura do site, o Firecrawl só faz duas coisas: mapear as URLs do site e devolver o markdown das páginas. O significado é extraído pela plataforma com a própria LLM e structured output: a escolha das páginas-chave, os Dados da Empresa propostos, a Voz da marca e os pares de FAQ. O mesmo leitor passa a servir o Documento URL. Se o Firecrawl falhar, o leitor cai no Readability local, que já existia. Assim, ler uma página nunca fica refém da disponibilidade de um serviço externo.

## Considered Options

- **`/extract` ou formato JSON do Firecrawl com schema**: rejeitada. Consome mais créditos, prende a extração à LLM e ao prompt do Firecrawl, e não funciona no fallback, já que o Readability só entrega markdown. Teríamos duas extrações diferentes para o mesmo resultado.
- **Crawl completo do site**: rejeitada. Em e-commerce vira milhares de páginas de produto, que já chegam pela Loja. O custo fica imprevisível.
- **Continuar só com o Readability**: rejeitada. Não renderiza JavaScript e volta vazio em sites Wix, Framer e SPAs, que são comuns entre as Empresas do público-alvo.

## Consequences

- O markdown é o contrato entre o leitor e a extração. Trocar o Firecrawl por outro leitor não mexe na extração.
- A chave do Firecrawl é da plataforma. O custo é contido por um teto de páginas por Leitura do site e por no máximo uma Leitura a cada 24 h por Empresa, e as páginas contam na cota de armazenamento da Base de conhecimento.
