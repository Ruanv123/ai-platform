# 06: Coleções

**What to build:** A Base de conhecimento ganha Coleções. Owner e Admin criam, renomeiam, editam descrição e excluem Coleções; Member visualiza. Toda Empresa tem a Coleção "Geral", criada no onboarding, que não pode ser excluída. Este ticket introduz o comportamento compartilhado de pertencer à Empresa (global scope por Empresa do usuário autenticado, `team_id` preenchido na criação, 404 para recurso de outra Empresa) e a permissão `knowledge:manage`, que serão reutilizados pelos próximos models. Referência: spec, user stories 21–32 e seção Base de conhecimento.

**Blocked by:** 03 (Onboarding da Empresa)

**Status:** done

- [x] Comportamento reutilizável de pertencer à Empresa: scope só aplicado em contexto HTTP autenticado; recurso de outra Empresa responde 404
- [x] `TeamPermission` ganha `knowledge:manage` (Owner e Admin); permissões do usuário expostas ao frontend
- [x] Onboarding cria a Coleção "Geral" marcada como padrão; seeder/factory cobrem o caso
- [x] Item "Base de conhecimento" na sidebar com lista de Coleções (nome, descrição, quantidade de documentos)
- [x] Owner e Admin criam, editam e excluem Coleções com confirmação; Member não vê as ações e recebe 403 via requisição direta
- [x] Coleção "Geral" não pode ser excluída (UI e backend)
- [x] Feature tests: CRUD, permissões por papel, isolamento entre Empresas (404), "Geral" indelével
