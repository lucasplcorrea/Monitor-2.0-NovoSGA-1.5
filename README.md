# Gestão de Filas — lucas.monitor2

Versão **0.2.2**. Módulo nativo de unidade para Novo SGA **1.5.x**, desenvolvido a partir dos contratos oficiais da versão **1.5.2**, com sintaxe PHP **5.6**. Coexiste com `sga.monitor`, sem alterar o core, criar tabelas ou executar SQL de instalação.

## Novidades da versão 0.2.2

- Novo nome **Gestão de Filas**, alinhado ao uso do módulo pelos gestores de cada setor.
- Interface clara e compacta, integrada ao visual Bootstrap 3 do Novo SGA.
- Cartões de senha com largura uniforme e nomes longos de prioridade sem alterar o layout.
- Limite do alerta piscante configurável entre 1 e 1440 minutos.
- Notificações do navegador, com agrupamento e controle de duplicidade por usuário e unidade.
- Identificação segura da sessão e correção do carregamento após reinstalações, incluindo uma versão própria nos assets para evitar cache antigo.

## Funcionalidades

- Senhas aguardando, chamadas pela mesa e em atendimento, agrupadas por serviço.
- Prioridade conforme o peso configurado no SGA, responsável e tempo de espera.
- Detalhes da senha em modal; alerta de espera piscante a partir de cinco minutos, configurável de 1 a 1440 minutos.
- Atualização dos cartões a cada cinco segundos, sem recarregar a página. Apenas uma requisição de filas por vez; pausa quando a aba está oculta e retoma ao voltar. Com notificações ativadas, consulta a cada 30 segundos em segundo plano.
- Histórico por número (com ou sem sigla), data de chegada ou ambos; até 500 registros, com aviso quando houver mais resultados.

A numeração única ou por serviço segue a configuração do SGA. A espera termina na chamada; duração e permanência consideram as datas do servidor e preservam horas acima de 24.

## Instalação

### Pela interface web

1. Baixe ou gere o arquivo `lucas.monitor2.zip` deste repositório. Mantenha exatamente esse nome, pois ele determina a chave do módulo no Novo SGA 1.5.
2. Entre como administrador e abra a administração de **Módulos**.
3. Importe `lucas.monitor2.zip` e aguarde o módulo **Gestão de Filas** aparecer na lista.
4. Abra o módulo e marque-o como ativo.
5. Em **Cargos**, conceda acesso à **Gestão de Filas** aos cargos apropriados.
6. Entre novamente no SGA para atualizar as permissões da sessão, selecione a unidade e abra o módulo pelo menu.

O módulo é instalado inativo e não exige SQL manual. Em uma atualização, consulte também [Atualizar uma versão anterior](#atualizar-uma-versão-anterior).

### Gerar o pacote

Na raiz deste repositório, gere um ZIP novo (a remoção prévia evita manter entradas antigas):

```sh
rm -f lucas.monitor2.zip
zip -r lucas.monitor2.zip \
  manifest.json \
  Monitor2Controller.php \
  public \
  views
unzip -t lucas.monitor2.zip
unzip -l lucas.monitor2.zip
```

O nome **lucas.monitor2.zip** determina a chave do módulo no instalador 1.5. Não renomeie para incluir a versão. O conteúdo deve estar na raiz do arquivo, sem uma pasta intermediária. README, testes e imagens da documentação não fazem parte do pacote.

### Instalar pela linha de comando

Transfira esse ZIP para `/tmp/lucas.monitor2.zip` no servidor do SGA. Na raiz da instalação do **Novo SGA**, usando o PHP 5.6 do ambiente e um usuário com acesso aos arquivos e à configuração do SGA:

```sh
php bin/novosga.php module:install /tmp/lucas.monitor2.zip
```

O PHP CLI precisa das extensões e dependências exigidas pelo SGA, incluindo `ZipArchive`. O instalador precisa escrever em `modules/` e `var/cache/`. Não é necessário executar SQL manualmente.

Depois da instalação, entre como administrador no SGA:

1. Em **Módulos**, abra **Gestão de Filas** e marque-o como ativo.
2. Em **Cargos**, conceda acesso à **Gestão de Filas** aos cargos apropriados.
3. Confira a lotação dos usuários no grupo/unidade correspondente. Entre novamente para atualizar as permissões da sessão.
4. Selecione a unidade e abra **Gestão de Filas** no menu do SGA.

Não copie os antigos PHP para o diretório público e não altere `templates/home.html.twig`. Em uma migração, remova do servidor as cópias anteriormente publicadas de `monitor.php`, `atendimentos.php` e `consulta_atendimentos.php`, além dos links manuais antigos. A remoção neste repositório não remove instalações antigas em outros servidores.

## Integração e segurança

As preferências de alertas são locais ao navegador, separadas por usuário e unidade; não alteram estado no servidor. Não há credenciais próprias de banco, conexão PDO/mysqli independente, arquivo `.env` de banco, login, sessão ou token próprios. O módulo usa `$this->em()` e o Doctrine já configurado pelo Novo SGA. LDAP/AD continua sob responsabilidade da autenticação do SGA.

Todas as consultas passam pelo controller do módulo e pela autorização nativa. O módulo exige retorno estritamente `true` de `AcessoService::checkAccess`, pois no 1.5.2 uma sessão inválida pode produzir um objeto `JsonResponse`. Também verifica se o módulo está ativo. A unidade vem exclusivamente da sessão, nunca de parâmetros da requisição; detalhes e histórico também são filtrados por unidade.

`FilaService::builder()` e `applyOrders()` fornecem a consulta e ordenação nativas, ampliadas para os três estados. `AtendimentoService::buscaAtendimento()` resolve os detalhes com filtro de unidade. `ServicoService::servicosUnidade()` foi avaliado: uma consulta adicional por serviço não é necessária, pois os relacionamentos já são carregados com a fila. O histórico usa ORM sobre `AtendimentoHistorico`, com joins de serviço, prioridade e responsável. Não depende da antiga view externa.

Entradas são validadas no backend; parâmetros são vinculados pelo Doctrine. Campos dinâmicos usam escape Twig e `.text()` no JavaScript. O JSON contém apenas os campos necessários, sem documentos de clientes ou serialização integral de entidades. Falhas de consulta não retornam SQL, exceções ou stack traces. Não há logs de consultas ou dados pessoais. As ações são somente leitura.

Assets usam `module.html.twig` e os recursos locais de jQuery/Bootstrap 3 do SGA. As chamadas usam `SGA.url()`, sem domínio, IP ou protocolo fixo. O módulo não depende de Internet nem precisa de configuração própria para HTTPS/reverse proxy; pressupõe que o SGA já esteja acessível corretamente pelo proxy. Respostas do módulo usam `Cache-Control: private, no-store`; não configure cache público dessas rotas no proxy.

## Testar no Novo SGA 1.5.2

Na raiz da instalação, antes de abrir o navegador:

```sh
php -v
php -l modules/lucas/monitor2/Monitor2Controller.php
```

No navegador, abra o módulo pelo menu. Com **a sessão autenticada**, estes comandos no console testam as rotas sem fixar endereço do servidor:

```js
jQuery.getJSON(SGA.url('ajax_filas')).done(console.log);
jQuery.getJSON(SGA.url('historico'), { senha: 'A001' }).done(console.log);
jQuery.getJSON(SGA.url('historico'), { data: '2026-09-13' }).done(console.log);
jQuery.getJSON(SGA.url('historico'), { senha: 'A001', data: '2026-09-13' }).done(console.log);
jQuery.getJSON(SGA.url('historico'), { data: '2026-02-30' }).done(console.log);
```

Substitua a senha e a data de exemplo por registros de teste conhecidos. A data inválida deve produzir `success: false`. Não cole saídas com dados reais em tickets ou logs compartilhados.

Roteiro de homologação:

1. Em uma unidade de teste, emita senhas normais e prioritárias de dois serviços. Confira as três seções enquanto chama e inicia atendimentos pelo módulo oficial. O módulo Gestão de Filas é somente leitura.
2. Clique nas senhas e confira responsável, prioridade e datas. Aguarde mais de cinco segundos; confirme a atualização sem recarregamento e sem fechar o modal.
3. Oculte a aba; sem notificações ativadas, após uma eventual requisição em andamento terminar, não devem surgir novas consultas de filas. Com notificações, a consulta ocorre a cada 30 segundos. Ao voltar, a atualização deve retomar uma única vez.
4. Consulte registros já arquivados por número, data e ambos; confira ausência de resultados, entrada inválida e aviso de limite.
5. Com usuários de duas unidades, tente `info_senha` com um ID da outra unidade. Não pode retornar o atendimento. Acrescente `unidade` ou `unidade_id` às consultas: esses parâmetros não podem mudar o escopo da sessão.
6. Teste sem login, com sessão invalidada, cargo sem permissão e módulo inativo. Não deve haver dados. Teste também a troca de unidade em outra aba: o monitor deve limpar os dados e solicitar recarga.
7. Confira o funcionamento de `sga.monitor` e a ausência de requisições externas ou conteúdo misto no acesso HTTPS pelo proxy.

## Desinstalar

Na raiz da instalação do Novo SGA:

```sh
php bin/novosga.php module:remove lucas.monitor2
```

Isso usa o mecanismo oficial de remoção e não apaga atendimentos. O instalador 1.5.2 não sobrescreve um módulo de mesma chave; para reinstalar esta versão, remova-o pelo comando acima, instale o novo ZIP e refaça a ativação/permissões conforme necessário.

## Limitações

- A busca histórica consulta somente `historico_atendimentos`, como o comportamento original. Registros ainda em `atendimentos` aparecem no monitor enquanto estão nos estados 1, 2 ou 3; só entram na busca após o arquivamento normal do SGA. Este módulo não dispara arquivamento.
- A pesquisa aceita uma letra ASCII opcional e um número inteiro positivo de até 2147483647. A data usa `AAAA-MM-DD`, no fuso do servidor SGA.
- A configuração atual de numeração do SGA também formata os registros históricos. Trocar o modo de numeração não preserva a apresentação usada em emissões antigas.
- Filas não são truncadas. Unidades com filas muito grandes devem avaliar o custo do polling em homologação. O histórico retorna no máximo 500 registros, sem paginação.
- O acesso cobre os serviços da unidade permitida ao usuário, conforme o monitor oficial; não é limitado aos serviços que esse usuário atende.
- Os testes isolados não substituem homologação com o banco MariaDB real, LDAP/AD e Nginx Proxy Manager da instalação.
- As credenciais foram removidas dos arquivos atuais. O histórico anterior do Git não foi reescrito; credenciais que já tenham sido publicadas precisam ser revogadas no ambiente responsável.

## Referências de implementação

Código oficial fixado na tag `v1.5.2`: [sga.monitor](https://github.com/novosga/novosga/tree/v1.5.2/modules/sga/monitor), [ModuloService](https://github.com/novosga/novosga/blob/v1.5.2/src/Novosga/Service/ModuloService.php), [AcessoService](https://github.com/novosga/novosga/blob/v1.5.2/src/Novosga/Service/AcessoService.php). As imagens em `Imagens/` registram a interface antiga; não integram o módulo.

## Testes automatizados isolados

Com PHP 5.6, `pdo_sqlite`, `gettext` e `zip`, disponibilize um checkout oficial da tag `v1.5.2` em `/tmp/novosga-1.5.2` com as dependências Composer instaladas. Na raiz deste repositório, após gerar o ZIP:

```sh
NOVOSGA_TEST_SOURCE=/tmp/novosga-1.5.2 php tests/integration.php
```

O teste utiliza o ORM, os serviços, a autorização, o comando `module:install` e o Twig reais do SGA. Cria um banco SQLite em memória e um diretório temporário exclusivo para extração/cache; não lê a configuração de banco da instalação e não altera seu schema. Verifica isolamento, entradas inválidas, estados, duração, numeração, limite, permissões, sessão inválida, escape Twig e instalação/remoção. Não publique `tests/` no servidor.

Validação realizada: PHP 5.6.40, Doctrine ORM 2.5.14/DBAL 2.5.13 e Twig 1.42.5, compatíveis com as restrições Composer do SGA 1.5.2. Interface também verificada em navegador com dados sintéticos e os assets locais oficiais; a homologação no ambiente de destino continua necessária.

## Alertas e notificações (0.2.2)

A interface utiliza cores claras e controles Bootstrap 3 do SGA, serviços em linhas e senhas compactas de largura uniforme. Prioridades aparecem em vermelho; o nome completo fica disponível ao passar o mouse, no nome acessível do botão e no modal. O amarelo e a animação indicam espera acima do limite, somente para senhas aguardando. A preferência de acessibilidade por movimento reduzido mantém o destaque amarelo sem piscar.

Abra **Alertas**, escolha **Piscar após** (1 a 1440 minutos, padrão 5) e clique em **Salvar**. A preferência é deste navegador, separada por usuário e unidade, e não é compartilhada com outros computadores. Se o armazenamento estiver bloqueado, vale apenas para a aba atual.

Clique em **Ativar notificações** e autorize o navegador. O módulo agrupa em uma notificação as novas senhas que atingiram o limite, inclusive as que já estão acima dele ao ativar. Não exibe dados de clientes. Mantém um registro local das senhas notificadas para evitar repetir o aviso a cada atualização e reduzir duplicação entre abas. O clique no aviso tenta trazer o módulo à frente. **Desativar notificações** interrompe os avisos para este usuário/unidade; não revoga a permissão geral do site no navegador.

É necessário manter o módulo aberto e a sessão válida. Não há push com a página fechada, envio a outros usuários ou instalação de service worker. Cada gestor deve habilitar os avisos em seu navegador. Em segundo plano o intervalo solicitado é de 30 segundos; suspensão da aba/computador, economia de energia ou configurações do sistema podem atrasar ou impedir os avisos. Notificações desktop exigem navegador compatível, HTTPS e permissão; alguns navegadores móveis não suportam este modo. Veja [permissão de notificações](https://developer.mozilla.org/en-US/docs/Web/API/Notification/requestPermission_static) e [restrições em abas ocultas](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API).

## Atualizar uma versão anterior

A chave permanece `lucas.monitor2` e o ZIP continua com esse nome em todas as versões. O nome de exibição passa a **Gestão de Filas**. O SGA armazena os metadados no banco durante a instalação; substituir apenas arquivos ou o manifest de uma instalação existente não atualiza o módulo corretamente.

Anote os cargos autorizados, remova a versão anterior pela administração de **Módulos**, importe o novo `lucas.monitor2.zip` e refaça a ativação e as permissões. O procedimento equivalente pela linha de comando, com o novo ZIP já em `/tmp`, é:

```sh
php bin/novosga.php module:remove lucas.monitor2
php bin/novosga.php module:install /tmp/lucas.monitor2.zip
```

Os atendimentos permanecem no banco. Depois, entre novamente no SGA e recarregue a página ignorando o cache (`Ctrl+Shift+R`).

Se a tela exibir uma mensagem de troca de usuário/unidade logo após a reinstalação, reinicie ou recarregue o PHP-FPM para limpar o opcode cache. A versão 0.2.2 também aceita, durante essa transição, respostas do controller 0.1.x que ainda não possuam o identificador do usuário. O filtro da unidade e a autorização continuam obrigatórios no backend.

O Novo SGA 1.5 usa sua própria versão (`1.5.2`) como chave de cache para todos os assets dos módulos. A versão 0.2.2 acrescenta uma chave própria (`0.2.2`) ao CSS e ao JavaScript, evitando que o navegador ou reverse proxy reutilize arquivos de uma versão anterior após a reinstalação.

O Twig do SGA também pode manter a versão compilada de `index.html.twig`. Se o código-fonte da página não mostrar `script.js?v=0.2.2`, limpe os arquivos compilados e recarregue o PHP-FPM. Na raiz do SGA:

```sh
find var/cache -type f -delete
sudo systemctl reload php5.6-fpm
```

Adapte o nome do serviço ou reinicie somente o contêiner PHP quando o SGA estiver em Docker. O diretório `var/cache` deve continuar gravável pelo usuário do PHP.

O arquivo `tests/browser-checks.js` contém as verificações usadas no desenvolvimento com uma página local de dados sintéticos. Ele valida o limite de alerta, largura uniforme, escape de texto, persistência, permissão explícita, agrupamento e deduplicação das notificações e o intervalo de segundo plano. A entrega pelo sistema operacional deve ser conferida no computador de cada gestor.
