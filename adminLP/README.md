# Atualização do painel de parceiros

Publique juntos `adminLP/parceiros-admin.html`, `api/parceiros.php` e `api/lib/admin-actions.php`.

## Banco existente

Execute uma única vez `migrations/001_partner_archive.sql` na base da aplicação antes de usar arquivar/restaurar. Para instalações novas, a coluna já está em `schema.sql`. A migração não foi aplicada automaticamente.

## Comportamento

- Arquivar oculta o cadastro da visualização padrão, preservando os dados e a situação. Restaurar retorna o cadastro à situação anterior.
- Retirar de Aprovados ou arquivar não bloqueia nem exclui o acesso existente na Sou+Blu. O painel avisa antes da confirmação.
- A criação de acesso exige digitar o código mostrado no diálogo, que identifica o nome, e-mail e destino. O código vale por cinco minutos e uma tentativa, vinculado à sessão, administrador e cadastro. É confirmação de intenção, não um segundo fator de autenticação.
- Uma lista desatualizada é rejeitada pela API; atualize antes de tentar novamente.
- A integração de criação de conta e envio de e-mail permanece a existente. Falhas de integração são mostradas no painel.

## Validação

`php tests/admin-confirmation.php` testa a confirmação isoladamente, sem banco, rede ou mensagens. Os fluxos do painel foram verificados com dados fictícios e API simulada; a integração real e a migração precisam ser verificadas no ambiente de implantação.
