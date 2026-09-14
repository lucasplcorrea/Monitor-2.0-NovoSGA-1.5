<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
date_default_timezone_set('America/Sao_Paulo');
// Run only against an official 1.5.2 source checkout with Composer dependencies.
// All data and installer output live in a new temporary directory, never in its database.
$source = getenv('NOVOSGA_TEST_SOURCE');
if (!$source || !is_file($source.'/vendor/autoload.php')) {
    fwrite(STDERR, "Set NOVOSGA_TEST_SOURCE to a Novo SGA 1.5.2 checkout with vendor/.\n");
    exit(1);
}
$work = sys_get_temp_dir().'/monitor2-test-'.uniqid();
mkdir($work);
mkdir($work.'/modules');
mkdir($work.'/cache');
define('DS', DIRECTORY_SEPARATOR);
define('NOVOSGA_ROOT', $source);
define('NOVOSGA_CONFIG', $work);
define('NOVOSGA_CACHE', $work.'/cache');
define('NOVOSGA_TEMPLATES', $source.'/templates');
define('NOVOSGA_DEV', true);
define('MODULES_PATH', $work.'/modules');
define('MODULES_DIR', 'modules');
define('NOVOSGA_LOCALE_DIR', $source.'/locales');
require $source.'/vendor/autoload.php';
require dirname(__DIR__).'/Monitor2Controller.php';

use Novosga\Config\DatabaseConfig;
use Novosga\Config\AppConfig;
use Novosga\Service\ModuloService;
use Novosga\Model\Util\UsuarioSessao;

function check($condition, $label) {
    if (!$condition) { throw new Exception('FAIL: '.$label); }
    echo 'PASS: '.$label."\n";
}
// The connection belongs to SGA's DatabaseConfig; test database only.
$db = DatabaseConfig::getInstance(array('driver' => 'pdo_sqlite', 'memory' => true, 'host' => 'test'));
$db->setDev(true);
$em = $db->createEntityManager();
$metadata = $em->getMetadataFactory()->getAllMetadata();
$schemaTool = new Doctrine\ORM\Tools\SchemaTool($em);
$schemaTool->createSchema($metadata);
$conn = $em->getConnection();
$schema = $conn->getSchemaManager();
function fixture($table, $values) {
    global $schema, $conn;
    foreach ($schema->listTableColumns($table) as $name => $column) {
        if (!array_key_exists($name, $values) && $column->getNotnull() && !$column->getAutoincrement()) {
            $type = $column->getType()->getName();
            $values[$name] = in_array($type, array('integer', 'smallint', 'bigint', 'boolean')) ? 1 : 'fixture';
        }
    }
    $conn->insert($table, $values);
}
$moduleService = new ModuloService($em);
$command = new Symfony\Component\Console\Tester\CommandTester(new Novosga\Console\ModuleInstallCommand($em));
$command->execute(array('filename' => dirname(__DIR__).'/lucas.monitor2.zip'));
$module = $em->getRepository('Novosga\Model\Modulo')->findOneBy(array('chave' => 'lucas.monitor2'));
check($module !== null, 'official module:install command');
check($module->getChave() === 'lucas.monitor2' && !$module->getStatus(), 'official ZIP extraction, manifest and installation (inactive)');
$module->setStatus(1);
$em->flush();
fixture('modulos', array('id' => 900, 'chave' => 'sga.monitor', 'nome' => 'Monitor', 'status' => 1));
fixture('grupos', array('id' => 1));
fixture('grupos', array('id' => 2));
fixture('cargos', array('id' => 1));
fixture('unidades', array('id' => 1, 'grupo_id' => 1, 'nome' => 'Unidade teste'));
fixture('unidades', array('id' => 2, 'codigo' => '2', 'grupo_id' => 2, 'nome' => 'Outra unidade'));
fixture('usuarios', array('id' => 1, 'login' => 'fixture', 'nome' => 'Responsável teste', 'session_id' => 'test-session'));
fixture('usu_grup_cargo', array('usuario_id' => 1, 'grupo_id' => 1, 'cargo_id' => 1));
fixture('cargos_mod_perm', array('modulo_id' => $module->getId(), 'cargo_id' => 1, 'permissao' => 1));
fixture('locais', array('id' => 1));
fixture('servicos', array('id' => 1, 'nome' => '<img src=x onerror=alert(1)>', 'macro_id' => null));
fixture('prioridades', array('id' => 1, 'nome' => 'Convencional', 'peso' => 0));
fixture('prioridades', array('id' => 2, 'nome' => 'Prioridade', 'peso' => 10));
foreach (array(1, 2) as $unitId) {
    fixture('uni_serv', array('unidade_id' => $unitId, 'servico_id' => 1, 'local_id' => 1, 'sigla' => 'A'));
}
function ticketFixture($id, $unit, $status) {
    return array('id' => $id, 'unidade_id' => $unit, 'servico_id' => 1, 'prioridade_id' => $status === 1 ? 2 : 1,
        'usuario_tri_id' => 1, 'usuario_id' => $status === 1 ? null : 1, 'atendimento_id' => null,
        'status' => $status, 'sigla_senha' => 'A', 'num_senha' => $id, 'num_senha_serv' => $id + 100,
        'dt_cheg' => '2026-09-10 08:00:00', 'dt_cha' => $status === 1 ? null : '2026-09-10 08:12:00',
        'dt_ini' => $status < 3 ? null : '2026-09-10 08:15:00', 'dt_fim' => $status < 4 ? null : '2026-09-11 09:15:00');
}
foreach (array(1, 2, 3) as $status) { fixture('atendimentos', ticketFixture($status, 1, $status)); }
fixture('atendimentos', ticketFixture(4, 2, 1));
fixture('historico_atendimentos', ticketFixture(10, 1, 4));
fixture('historico_atendimentos', ticketFixture(11, 2, 4));
fixture('config', array('chave' => 'numeracao', 'valor' => '1', 'tipo' => 2));
\Slim\Environment::mock(array('REQUEST_METHOD' => 'GET', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'));
$app = new Novosga\App();
$app->prepare();
$context = $app->getContext();
$userEntity = $em->find('Novosga\Model\Usuario', 1);
$userEntity->setSessionId('test-session');
$em->flush();
$user = new UsuarioSessao($userEntity);
$user->setEm($em);
$user->setUnidade($em->find('Novosga\Model\Unidade', 1));
$context->setUser($user);
$context->setModule($module);
$controller = new modules\lucas\monitor2\Monitor2Controller($app, $module);
function action($name, $params = array()) {
    global $app, $controller, $context;
    $env = $app->environment();
    $env['slim.request.query_hash'] = $params;
    return $controller->$name($context);
}
$r = action('ajax_filas', array('unidade' => 2));
check($r->success && count($r->data['atendimentos']) === 3, 'three states, ignores client unit override');
check($r->data['atendimentos'][0]['preferencial'], 'priority follows weight');
check($r->data['usuario'] === 1, 'response identifies session user for browser preferences');
$r = action('info_senha', array('id' => '2'));
check($r->success && $r->data['atendimento']['espera'] === 720, 'detail and wait stops at call');
check(!action('info_senha', array('id' => '4'))->success, 'cross-unit detail denied');
foreach (array('0', '-1', '1 OR 1=1', array('1'), '2147483648') as $id) {
    check(!action('info_senha', array('id' => $id))->success, 'invalid detail ID rejected');
}
foreach (array(array('senha' => 'A010'), array('data' => '2026-09-10'), array('senha' => '010', 'data' => '2026-09-10')) as $params) {
    $r = action('historico', $params);
    check($r->success && count($r->data['atendimentos']) === 1, 'history filter and unit isolation');
}
check($r->data['atendimentos'][0]['duracao'] === 90000, 'duration beyond 24 hours');
foreach (array(array(), array('senha' => "' OR 1=1"), array('data' => '2026-02-30'), array('data' => array()), array('senha' => 'A0')) as $params) {
    check(!action('historico', $params)->success, 'invalid history input rejected');
}
$setting = $em->find('Novosga\Model\Configuracao', 'numeracao');
$setting->setValor(2); $em->flush();
$r = action('historico', array('senha' => 'A110'));
// Existing entities cache their Senha object; clear between simulated HTTP requests.
$em->clear('Novosga\Model\AtendimentoHistorico');
$r = action('historico', array('senha' => 'A110'));
check($r->success && $r->data['atendimentos'][0]['senha'] === 'A110', 'service numbering from SGA setting');
for ($i = 20; $i < 521; ++$i) { fixture('historico_atendimentos', ticketFixture($i, 1, 4)); }
$r = action('historico', array('data' => '2026-09-10'));
check($r->success && count($r->data['atendimentos']) === 500 && $r->data['limitado'], 'history limit and overflow flag');
$module->setStatus(0);
check(!action('ajax_filas')->success, 'inactive module denied');
$module->setStatus(1);
$userEntity->setSessionId('changed-session'); $em->flush();
// Change the wrapped session back without flushing to simulate the stale browser session.
$userEntity->setSessionId('test-session');
$r = action('ajax_filas');
check(!$r->success && $r->invalid, 'truthy invalid-session response cannot bypass authentication');
$userEntity->setSessionId('changed-session');
$conn->executeUpdate('DELETE FROM cargos_mod_perm');
$newUser = new UsuarioSessao($userEntity); $newUser->setEm($em); $newUser->setUnidade($em->find('Novosga\Model\Unidade', 1));
$context->setUser($newUser);
check(!action('ajax_filas')->success, 'user without module permission denied');
$context->setUser(null);
check(!action('historico', array('data' => '2026-09-10'))->success, 'anonymous access denied');
$twig = new Twig_Environment(new Twig_Loader_Filesystem(array(dirname(__DIR__).'/views', $source.'/templates')));
$twig->addExtension(new Twig_Extensions_Extension_I18n());
foreach (array('baseUrl', 'resources') as $function) {
    if ($function === 'resources') {
        $twig->addFunction(new Twig_SimpleFunction($function, function ($path, $key, $version = null) {
            return '/modules/'.$key.'/resources/'.$path.'?v='.($version ? $version : '1.5.2');
        }));
    } else {
        $twig->addFunction(new Twig_SimpleFunction($function, function () { return ''; }));
    }
}
$html = $twig->render('index.html.twig', array('unidade' => array('id' => 1, 'nome' => '<script>alert(1)</script>'), 'module' => array('chave' => 'lucas.monitor2', 'nome' => 'Gestão de Filas')));
check(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'real Twig render escapes unit name');
check(substr_count($html, '?v=0.2.2') === 2, 'module assets use their own cache version');
fixture('cargos_mod_perm', array('modulo_id' => $module->getId(), 'cargo_id' => 1, 'permissao' => 1));
$context->setUser($newUser);
$conn->executeUpdate('DROP TABLE historico_atendimentos');
$r = action('historico', array('data' => '2026-09-10'));
check(!$r->success && $r->message === 'Não foi possível consultar os atendimentos. Tente novamente.', 'database errors do not expose SQL or stack traces');
$moduleService->uninstall('lucas.monitor2');
check(!is_dir(MODULES_PATH.'/lucas/monitor2') && $em->find('Novosga\Model\Modulo', 900), 'official uninstall preserves sga.monitor');
echo "All integration checks passed on PHP ".PHP_VERSION.".\n";

Novosga\Util\FileUtils::rmdir($work);
