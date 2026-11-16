<?php
namespace app;

/**
 * 应用请求对象：还原反向代理后的真实客户端 IP。
 *
 * 部署链路为「前端 Nginx -> http://127.0.0.1:8000 -> 本机 PHP-FPM」，
 * 框架 ip() 默认只返回 REMOTE_ADDR，会把操作日志的来源 IP 全部记成 127.0.0.1。
 * 这里仅当 TCP 对端是可信代理时才解析转发头；直连请求不采信客户端自带的头，避免伪造。
 */
class Request extends \think\Request
{
    /** 默认可信代理：本机回环（含 IPv4-mapped IPv6），与当前 Nginx 拓扑一致。 */
    private const DEFAULT_TRUSTED_PROXIES = [
        '127.0.0.0/8',
        '::1/128',
        '::ffff:127.0.0.0/104',
    ];

    /** @var string[] 精确 IP 或 CIDR 网段 */
    private static array $trustedProxies = self::DEFAULT_TRUSTED_PROXIES;

    /**
     * 覆盖可信代理列表（由 AppService 启动时从唯一配置源 myconfig 注入）。
     * 空配置回落到默认本机回环，避免把任意来源都当成代理而采信伪造头。
     */
    public static function setTrustedProxies(array $proxies): void
    {
        $rules = array_values(array_filter($proxies, 'is_string'));
        $rules = array_values(array_filter(array_map('trim', $rules), static fn (string $rule): bool => $rule !== ''));

        self::$trustedProxies = $rules !== [] ? $rules : self::DEFAULT_TRUSTED_PROXIES;
    }

    /**
     * 获取客户端 IP：可信代理转发时取转发头，直连时取 REMOTE_ADDR。
     */
    public function ip(): string
    {
        if (!empty($this->realIP)) {
            return $this->realIP;
        }

        $remote = $this->normalizeIp((string) $this->server('REMOTE_ADDR', ''));
        $this->realIP = $remote !== '' ? $remote : '0.0.0.0';

        if ($remote === '' || !$this->isTrustedProxy($remote)) {
            return $this->realIP;
        }

        $candidates = [];

        // Nginx 用 $proxy_add_x_forwarded_for 逐跳追加，最右侧一跳由可信代理写入、无法被客户端伪造。
        // 从右往左取第一个非可信代理地址，兼容客户端伪造的左侧 XFF 段。
        $xff = (string) $this->server('HTTP_X_FORWARDED_FOR', '');
        if ($xff !== '') {
            $candidates = array_reverse(array_map('trim', explode(',', $xff)));
        }

        // 单层代理常见写法，Nginx 会以 $remote_addr 覆盖 X-Real-IP；XFF 缺失或无效时兜底。
        foreach (['HTTP_X_REAL_IP', 'HTTP_X_CLIENT_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP'] as $header) {
            $value = trim((string) $this->server($header, ''));
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        foreach ($candidates as $candidate) {
            $ip = $this->normalizeIp($candidate);
            if ($ip !== '' && !$this->isTrustedProxy($ip)) {
                $this->realIP = $ip;
                break;
            }
        }

        return $this->realIP;
    }

    /**
     * 规范化并校验 IP：兼容方括号、端口、IPv6 zone id 与 IPv4-mapped IPv6。
     */
    private function normalizeIp(string $value): string
    {
        $ip = trim($value);
        if ($ip === '') {
            return '';
        }

        if ($ip[0] === '[') { // [2001:db8::1]:443
            $end = strpos($ip, ']');
            $ip = $end !== false ? substr($ip, 1, $end - 1) : substr($ip, 1);
        } elseif (substr_count($ip, ':') === 1) { // 192.0.2.1:8080
            $ip = explode(':', $ip)[0];
        }

        if (($zone = strpos($ip, '%')) !== false) { // fe80::1%eth0
            $ip = substr($ip, 0, $zone);
        }

        if (stripos($ip, '::ffff:') === 0) {
            $v4 = substr($ip, 7);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ip = $v4;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
    }

    /**
     * REMOTE_ADDR / 候选 IP 是否属于可信代理（支持 IPv4/IPv6 CIDR）。
     */
    private function isTrustedProxy(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        foreach (self::$trustedProxies as $rule) {
            [$network, $prefix] = array_pad(explode('/', $rule, 2), 2, null);
            $netBin = @inet_pton($network);
            if ($netBin === false || strlen($netBin) !== strlen($packed)) {
                continue;
            }

            $bits = $prefix === null || $prefix === '' ? strlen($netBin) * 8 : (int) $prefix;
            if ($bits < 0 || $bits > strlen($netBin) * 8) {
                continue;
            }

            $bytes = intdiv($bits, 8);
            if ($bytes > 0 && substr($packed, 0, $bytes) !== substr($netBin, 0, $bytes)) {
                continue;
            }

            $rest = $bits % 8;
            if ($rest > 0) {
                $mask = (0xFF << (8 - $rest)) & 0xFF;
                if ((ord($packed[$bytes]) & $mask) !== (ord($netBin[$bytes]) & $mask)) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }
}
