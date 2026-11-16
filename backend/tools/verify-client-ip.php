<?php
declare(strict_types=1);

/**
 * 操作日志来源 IP 回归验证（纯请求对象，不连数据库、不启动服务）。
 * 运行：E:\xampp\php\php.exe backend/tools/verify-client-ip.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';

$app = new \think\App(dirname(__DIR__));
$makeRequest = static fn (): \think\Request => $app->make('request', [], true);

$failures = 0;

$probe = $makeRequest();
$bound = $probe instanceof \app\Request;
printf("[%s] 容器解析 request => %s\n", $bound ? 'PASS' : 'FAIL', get_class($probe));
$failures += $bound ? 0 : 1;

$check = static function (string $name, array $server, string $expected) use ($makeRequest, &$failures): void {
    $request = $makeRequest();
    $request->withServer($server);
    $actual = $request->ip();
    $ok = $actual === $expected;
    $failures += $ok ? 0 : 1;
    printf("[%s] %s | 期望 %s | 实际 %s\n", $ok ? 'PASS' : 'FAIL', $name, $expected, $actual);
};

$check('直连：REMOTE_ADDR 即真实 IP', ['REMOTE_ADDR' => '203.0.113.9'], '203.0.113.9');
$check('直连：伪造转发头不被采信', [
    'REMOTE_ADDR'            => '203.0.113.9',
    'HTTP_X_REAL_IP'         => '198.51.100.1',
    'HTTP_X_FORWARDED_FOR'   => '198.51.100.2',
], '203.0.113.9');
$check('反代：X-Real-IP', [
    'REMOTE_ADDR'    => '127.0.0.1',
    'HTTP_X_REAL_IP' => '203.0.113.9',
], '203.0.113.9');
$check('反代：XFF 单段', [
    'REMOTE_ADDR'          => '127.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
], '203.0.113.9');
$check('反代：XFF 客户端伪造最左段', [
    'REMOTE_ADDR'          => '127.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.1, 203.0.113.9',
], '203.0.113.9');
$check('反代：XFF 跳过可信代理段', [
    'REMOTE_ADDR'          => '127.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.1, 203.0.113.9, 127.0.0.1',
    'HTTP_X_REAL_IP'       => '203.0.113.9',
], '203.0.113.9');
$check('反代：XFF 全无效回落 X-Real-IP', [
    'REMOTE_ADDR'          => '127.0.0.1',
    'HTTP_X_FORWARDED_FOR' => 'unknown, 999.1.1.1',
    'HTTP_X_REAL_IP'       => '203.0.113.9',
], '203.0.113.9');
$check('反代：所有头无效回落回环', [
    'REMOTE_ADDR'          => '127.0.0.1',
    'HTTP_X_FORWARDED_FOR' => 'unknown',
    'HTTP_X_REAL_IP'       => 'not-an-ip',
], '127.0.0.1');
$check('反代：IPv6 客户端', [
    'REMOTE_ADDR'    => '::1',
    'HTTP_X_REAL_IP' => '2001:db8::5',
], '2001:db8::5');
$check('反代：IPv4-mapped IPv6 规范化', [
    'REMOTE_ADDR'          => '::ffff:127.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '::ffff:203.0.113.9',
], '203.0.113.9');
$check('反代：带端口的 XFF', [
    'REMOTE_ADDR'          => '127.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.9:54321',
], '203.0.113.9');
$check('无 REMOTE_ADDR：不采信转发头', [
    'HTTP_X_REAL_IP' => '203.0.113.9',
], '0.0.0.0');

\app\Request::setTrustedProxies(['10.0.0.5/32']);
$check('自定义可信代理：生效', [
    'REMOTE_ADDR'    => '10.0.0.5',
    'HTTP_X_REAL_IP' => '203.0.113.9',
], '203.0.113.9');
$check('自定义可信代理：回环不再被信任', [
    'REMOTE_ADDR'    => '127.0.0.1',
    'HTTP_X_REAL_IP' => '203.0.113.9',
], '127.0.0.1');

\app\Request::setTrustedProxies([]);
$check('空配置回落默认可信代理', [
    'REMOTE_ADDR'    => '127.0.0.1',
    'HTTP_X_REAL_IP' => '203.0.113.9',
], '203.0.113.9');

printf("\n%s（失败 %d 项）\n", $failures === 0 ? '全部通过' : '存在失败', $failures);
exit($failures === 0 ? 0 : 1);
