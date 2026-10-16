# Atualização do painel de parceiros

Publique juntos `adminLP/parceiros-admin.html`, `api/parceiros.php`, `api/lib/admin-actions.php` e `api/lib/partner-archive.php`.

## Banco existente

Execute uma única vez `migrations/001_partner_archive.sql` na base da aplicação antes de usar arquivar/restaurar. Para instalações novas, a coluna já está em `schema.sql`. A migração não foi aplicada automaticamente.

## Comportamento

- O painel tem quatro situações: Pendente, Em negociação (contato em andamento), Aprovado e Excluído. Em negociação não cria acesso nem envia e-mail. O campo existente `status VARCHAR(16)` já comporta esse valor.

- Arquivar oculta o cadastro da visualização padrão, preservando os dados e a situação. Restaurar retorna o cadastro à situação anterior.
- Retirar de Aprovados ou arquivar não bloqueia nem exclui o acesso existente na Sou+Blu. O painel avisa antes da confirmação.
- A criação de acesso exige digitar o código mostrado no diálogo, que identifica o nome, e-mail e destino. O código vale por cinco minutos e uma tentativa, vinculado à sessão, administrador e cadastro. É confirmação de intenção, não um segundo fator de autenticação.
- Uma lista desatualizada é rejeitada pela API; atualize antes de tentar novamente.
- A integração de criação de conta e envio de e-mail permanece a existente. Falhas de integração são mostradas no painel.

## Validação

`php tests/admin-confirmation.php` testa a confirmação isoladamente, sem banco, rede ou mensagens. Os fluxos do painel foram verificados com dados fictícios e API simulada; a integração real e a migração precisam ser verificadas no ambiente de implantação.

## Exclusão e arquivamento automático

- Excluídos substitui o nome Recusados; o valor interno `recusado` foi preservado para compatibilidade com os cadastros existentes.
- Excluir move o cadastro para Excluídos mediante confirmação. É recuperável ao mover para outra situação nos detalhes do cadastro. Não apaga dados nem desativa contas externas.
- Aprovados e Excluídos são arquivados após 72 horas da última mudança de situação. Cada cartão mostra a data prevista. Restaurar um arquivado reinicia as 72 horas.
- Cadastros antigos sem data de mudança recebem a data da primeira execução da regra, garantindo três dias completos antes do arquivamento.
- A API verifica os vencidos ao listar os cadastros. O painel aberto atualiza a lista a cada minuto, exceto durante uma confirmação ou consulta de detalhes.
- Para executar mesmo sem ninguém acessar o painel, configure no agendador da hospedagem `php /caminho/da/aplicacao/scripts/archive-partners.php`, a cada cinco minutos. Esse agendamento ainda não foi configurado na hospedagem. O script só aceita execução por CLI.
- Testes: `php tests/partner-archive.php`. A opção `--mysql-temp` verifica a atualização em tabela temporária isolada da conexão configurada, sem alterar os cadastros reais.

Somente os cartões de Excluídos exibem o botão Excluir: ele apaga definitivamente o registro local após confirmação. Essa operação não remove contas na Sou+Blu. Os demais cartões não exibem botões de ação. Arraste para mudar de divisão ou clique para abrir os detalhes, onde ficam o seletor de situação, Arquivar, Excluir e Restaurar (quando arquivado).

Teste da exclusão definitiva: `php -c php-local.ini tests/partner-delete.php --mysql-temp delete`. Cenários adicionais: `wrong-status`, `unconfirmed` e `stale`. Todos usam tabela temporária e preservam os registros reais.

O filtro Experiência com crédito separa as respostas Sim (com experiência), Não (sem experiência) e ausentes ou não reconhecidas (não informado). Combina com os demais filtros e é zerado em Limpar filtros. Os cartões exibem o perfil informado pelo candidato.

Experiência e visualização são aplicadas por botões de um clique, com seleção destacada. Situação e período ficam em seção expansível, que informa quantos campos estão ativos. Limpar filtros restaura os valores iniciais.

Arquivados agora têm área própria, acessada pelo botão Arquivados no topo. A lista unificada mostra a divisão anterior, a data do arquivamento e Restaurar. Os filtros valem para a área selecionada; Limpar filtros preserva a área atual.

A área de arquivados separa Aprovados e Recusados, com contadores próprios. Cadastros arquivados manualmente em outras situações continuam acessíveis em Outras situações.

A opção Restaurar foi removida da interface, incluindo cartões e detalhes dos arquivados.
