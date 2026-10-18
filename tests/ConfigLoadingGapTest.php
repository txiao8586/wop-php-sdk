<?php

declare(strict_types=1);

namespace Wop\Sdk\Tests;

use Wop\Sdk\Config\Builder;
use Wop\Sdk\Config\ConfigJsonParser;
use Wop\Sdk\Config\ConfigValidator;
use Wop\Sdk\Config\HttpClientSettings;
use Wop\Sdk\Config\WopConfigLoader;
use Wop\Sdk\Config\WopSdkConfig;
use Wop\Sdk\Transport\TransportFactory;
use Wop\Sdk\RequestId;
use Wop\Sdk\Transport\CurlTransport;
use Wop\Sdk\WopClient;
use Wop\Sdk\WopException;

/**
 * 配置加载链覆盖闭合：parser 转义/数值负例、skipValue 家族、validator 逐分支、
 * builder setter、loader 发现顺序与 defaultClient（CI 行+分支门禁 ≥98%）。
 */
final class ConfigLoadingGapTest extends VectorCase
{
    private string $tempDir;

    private string $origCwd;

    private string $origHome;

    protected function setUp(): void
    {
        WopConfigLoader::clearCache();
        WopClient::resetDefault();
        $this->tempDir = sys_get_temp_dir() . '/wop-gap-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);
        $this->origCwd = (string) getcwd();
        $this->origHome = (string) getenv('HOME');
    }

    protected function tearDown(): void
    {
        WopConfigLoader::clearCache();
        WopClient::resetDefault();
        chdir($this->origCwd);
        putenv(WopConfigLoader::CONFIG_FILE_OVERRIDE_ENV);
        putenv(WopConfigLoader::CONFIG_FILE_ENV);
        putenv('HOME=' . $this->origHome);
        $this->removeTree($this->tempDir);
    }

    // ---------------- parser：文本与转义 ----------------

    public function testEmptyFileRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('空文件');
        ConfigJsonParser::parse("  \t\n ");
    }

    public function testBomStripped(): void
    {
        $json = "\u{FEFF}" . $this->validJson();
        $this->assertSame('app_001', ConfigJsonParser::parse($json)->appKey);
    }

    public function testTrailingGarbageRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('根对象后存在多余内容');
        ConfigJsonParser::parse($this->validJson() . ' x');
    }

    public function testFullEscapeRoundTrip(): void
    {
        $json = str_replace('app_001', 'a\"b\\\\c/d\be\ff\ng\rh\ti', $this->validJson());
        $this->assertSame('a"b\\c/d' . "\x08" . 'e' . "\x0C" . 'f' . "\n" . 'g' . "\r" . 'h' . "\t" . 'i',
            ConfigJsonParser::parse($json)->appKey);
    }

    public function testUnicodeEscapeDecoded(): void
    {
        $json = str_replace('app_001', 'Aé', $this->validJson());
        $this->assertSame('Aé', ConfigJsonParser::parse($json)->appKey);
    }

    public function testEscapeNegatives(): void
    {
        $cases = [
            '非法转义' => ['a\q', '非法转义'],
            'u 非法十六进制' => ['a\uZZZZ', '\u 转义非法'],
        ];
        foreach ($cases as $name => [$needle, $message]) {
            try {
                ConfigJsonParser::parse(str_replace('app_001', $needle, $this->validJson()));
                $this->fail($name . ': 未抛异常');
            } catch (WopException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }
        // 位数不足须在串尾截断触发（转义后到文件尾）
        try {
            ConfigJsonParser::parse('{"appKey":"a\u12');
            $this->fail('位数不足: 未抛异常');
        } catch (WopException $e) {
            $this->assertStringContainsString('\u 转义不完整', $e->getMessage());
        }
    }

    public function testEscapeTruncatedAtEof(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('字符串转义不完整');
        ConfigJsonParser::parse('{"appKey":"a\\');
    }

    public function testStringUnterminated(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('字符串未闭合');
        ConfigJsonParser::parse('{"appKey":"abc');
    }

    public function testExpectString(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('期望字符串');
        ConfigJsonParser::parse('{"appKey": 1}');
    }

    // ---------------- parser：skipValue 家族（未知字段） ----------------

    public function testUnknownTopLevelKeySkipped(): void
    {
        $json = substr($this->validJson(), 0, -1)
            . ',"extra":{"a":[1,{"b":true}],"c":null,"d":"s"},"arr":[true,false,null,42],"lit":true,"num":-1.5e3}';
        $config = ConfigJsonParser::parse($json);
        $this->assertSame('app_001', $config->appKey);
    }

    public function testUnknownHttpClientKeySkipped(): void
    {
        $json = str_replace('"readTimeout":30000', '"readTimeout":30000,"foo":[1,2]', $this->validJson());
        $config = ConfigJsonParser::parse($json);
        $this->assertSame(30000, $config->httpClient->readTimeout);
    }

    public function testSkipValueUnexpectedEnd(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('意外结束');
        ConfigJsonParser::parse('{"extra": ');
    }

    public function testTrailingCommaRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('尾随逗号');
        ConfigJsonParser::parse('{"appKey":"a",}');
    }

    public function testExpectColonRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage("期望 ':'");
        ConfigJsonParser::parse('{"appKey" "x"}');
    }

    public function testExpectSeparatorRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage("期望 ',' 或 '}'");
        ConfigJsonParser::parse('{"appKey":"a" "suite":"b"}');
    }

    // ---------------- parser：数值域 ----------------

    public function testExpiredSecondsNegatives(): void
    {
        $cases = [
            '负数' => ['-1', '类型非法: -1'],
            '零' => ['0', '类型非法: 0'],
            '溢出' => ['99999999999999999999', '类型非法: 99999999999999999999'],
            '非数字' => ['abc', '期望数字'],
        ];
        foreach ($cases as $name => [$value, $message]) {
            try {
                ConfigJsonParser::parse(
                    str_replace('"expiredSeconds":1800', '"expiredSeconds":' . $value, $this->validJson())
                );
                $this->fail($name . ': 未抛异常');
            } catch (WopException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }
    }

    public function testHttpClientScalarNegatives(): void
    {
        $json = str_replace('"connectTimeout":10000', '"connectTimeout":-2', $this->validJson());
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('类型非法: -2');
        ConfigJsonParser::parse($json);
    }

    public function testHttpClientOverflowRejected(): void
    {
        $json = str_replace('"maxRetryCount":3', '"maxRetryCount":99999999999999999999', $this->validJson());
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('类型非法: 99999999999999999999');
        ConfigJsonParser::parse($json);
    }

    public function testMaxRetryCountZeroMeansDisabled(): void
    {
        $json = str_replace('"maxRetryCount":3', '"maxRetryCount":0', $this->validJson());
        $this->assertSame(0, ConfigJsonParser::parse($json)->httpClient->maxRetryCount);
    }

    public function testEmptyBackupRootsAccepted(): void
    {
        $json = str_replace('"backupServerRoots":["https://gw-backup.example.com/gateway"]',
            '"backupServerRoots":[]', $this->validJson());
        $this->assertSame([], ConfigJsonParser::parse($json)->backupServerRoots);
    }

    // ---------------- validator：逐分支 ----------------

    public function testMissingRequiredFields(): void
    {
        // 两种触发面：空白值（trim 侧）与整键缺失（=== '' 侧）
        foreach (['appKey', 'suite', 'merchantPrivateKey', 'platformPublicKey', 'serverRoot'] as $field) {
            foreach (['blank' => $this->validJson($field), 'unset' => $this->validJson(null, [], [$field])] as $kind => $json) {
                try {
                    ConfigJsonParser::parse($json);
                    $this->fail("$field/$kind: 未抛异常");
                } catch (WopException $e) {
                    $this->assertStringContainsString('配置文件缺少必填项: ' . $field, $e->getMessage(), "$field/$kind");
                }
            }
        }
    }

    public function testWhitespaceEnvValuesFallThrough(): void
    {
        // override 为纯空白 → 回落 env；env 也纯空白 → 回落 cwd 候选
        putenv(WopConfigLoader::CONFIG_FILE_OVERRIDE_ENV . '=   ');
        putenv(WopConfigLoader::CONFIG_FILE_ENV . '=   ');
        file_put_contents($this->tempDir . '/wopSdkConfig.json', $this->validJson());
        chdir($this->tempDir);
        $this->assertSame('app_001', WopConfigLoader::loadDefault()->appKey);
    }

    public function testLoadDefaultWithoutHomeEnvFailsFast(): void
    {
        $empty = sys_get_temp_dir() . '/wop-gap-nohome-' . bin2hex(random_bytes(4));
        mkdir($empty, 0777, true);
        chdir($empty);
        putenv('HOME');
        try {
            $this->expectException(WopException::class);
            $this->expectExceptionMessage('未找到可读配置文件');
            WopConfigLoader::loadDefault();
        } finally {
            $this->removeTree($empty);
        }
    }

    public function testValidateApiPathLowercaseHttpAbsoluteRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('path 不得为绝对 URL');
        ConfigValidator::validateApiPath('http://x.com/p');
    }

    public function testJoinUrlTrailingSlashRootAndRootPath(): void
    {
        $this->assertSame('https://gw.example.com/gateway/v1', ConfigValidator::joinUrl('https://gw.example.com/gateway/', '/v1'));
        // path='/' 归一为 root + '/'（ltrim 清空前导斜杠）
        $this->assertSame('https://gw.example.com/gateway/', ConfigValidator::joinUrl('https://gw.example.com/gateway', '/'));
    }

    public function testGatewayUrlPortAndBareHost(): void
    {
        $this->assertSame('https://gw.example.com:8443/gateway', ConfigValidator::validateGatewayUrl('https://gw.example.com:8443/gateway', 'x'));
        $this->assertSame('https://gw.example.com', ConfigValidator::validateGatewayUrl('https://gw.example.com', 'x'));
    }

    public function testCurlTransportTimeoutFallbacksAndSendFailure(): void
    {
        // 超时 <=0 → 缺省兜底分支在 send() 内取值时覆盖；.invalid TLD 决定性 DNS 失败
        $cases = [
            new CurlTransport(null),
            new CurlTransport(new HttpClientSettings(0, 0, 3)),
            new CurlTransport(new HttpClientSettings(1, 1, 3)),
        ];
        foreach ($cases as $transport) {
            try {
                $transport->send('GET', 'https://deterministic-failure.invalid/x', [], '');
                $this->fail('预期传输失败');
            } catch (WopException $e) {
                $this->assertStringContainsString('传输', $e->getMessage());
            }
        }
    }

    public function testRequestIdWhitespaceOnlyResolvesNull(): void
    {
        $this->assertNull(RequestId::resolve('   '));
        $this->assertNull(RequestId::resolve(null));
    }

    public function testUnknownSuiteRejected(): void
    {
        $json = $this->validJson();
        $json = str_replace('"suite":"WOP-RSA3072-SHA256"', '"suite":"WOP-FAKE-1"', $json);
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('不支持的算法套件');
        ConfigJsonParser::parse($json);
    }

    public function testBuilderExpiredSecondsZeroRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('expiredSeconds 须为正整数');
        $this->baseBuilder()->expiredSeconds(0)->build();
    }

    public function testBuilderHttpClientNegativeRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('httpClient 类型非法');
        $this->baseBuilder()->httpClient(new HttpClientSettings(-1, 30000, 3))->build();
    }

    public function testEmptyBackupRootRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('backupServerRoots[1] 不是合法 URL');
        $this->baseBuilder()->backupServerRoots(['', 'https://ok.example.com/gateway'])->build();
    }

    public function testGatewayUrlNotAParseableUrl(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('不是合法 URL');
        ConfigValidator::validateGatewayUrl('not a url', 'serverRoot');
    }

    public function testGatewayUrlBadPortRejected(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('不是合法 URL');
        ConfigValidator::validateGatewayUrl('https://gw.example.com:abc/gateway', 'serverRoot');
    }

    public function testValidateApiPathNegatives(): void
    {
        $cases = [
            '空' => ['', '请求路径为空'],
            '绝对 URL' => ['HTTPS://x.com/p', 'path 不得为绝对 URL'],
            '无前导斜杠' => ['v1/x', 'path 须以 / 开头'],
            'query' => ['/a?b', 'path 不得含 query 或 fragment'],
            'fragment' => ['/a#b', 'path 不得含 query 或 fragment'],
        ];
        foreach ($cases as $name => [$path, $message]) {
            try {
                ConfigValidator::validateApiPath($path);
                $this->fail($name . ': 未抛异常');
            } catch (WopException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }
    }

    // ---------------- builder setter 全量 ----------------

    public function testBuilderSettersChain(): void
    {
        $config = $this->baseBuilder()
            ->backupServerRoots(null)
            ->expiredSeconds(3600)
            ->httpClient(null)
            ->transport(null)
            ->build();
        $this->assertSame([], $config->backupServerRoots);
        $this->assertSame(3600, $config->expiredSeconds);
        $this->assertEquals(HttpClientSettings::defaults(), $config->httpClient);
    }

    // ---------------- loader：发现顺序与入口 ----------------

    public function testLoadExplicitMissingPathFailsFast(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('配置文件不可读');
        WopConfigLoader::load($this->tempDir . '/missing.json');
    }

    public function testLoadDefaultUsesConfigEnv(): void
    {
        putenv(WopConfigLoader::CONFIG_FILE_ENV . '=' . __DIR__ . '/fixtures/valid-wop-config.json');
        $this->assertSame('app_10012481831', WopConfigLoader::loadDefault()->appKey);
    }

    public function testLoadDefaultFromCwdCandidate(): void
    {
        file_put_contents($this->tempDir . '/wopSdkConfig.json', $this->validJson());
        chdir($this->tempDir);
        $this->assertSame('app_001', WopConfigLoader::loadDefault()->appKey);
    }

    public function testLoadDefaultFromCwdConfigSubdirCandidate(): void
    {
        mkdir($this->tempDir . '/config');
        file_put_contents($this->tempDir . '/config/wopSdkConfig.json', $this->validJson());
        chdir($this->tempDir);
        $this->assertSame('app_001', WopConfigLoader::loadDefault()->appKey);
    }

    public function testLoadDefaultNoCandidateFailsFast(): void
    {
        // cwd=空临时目录、HOME 指空目录、仓库内无打包 config/wopSdkConfig.json（仅 Default 模板）
        $empty = sys_get_temp_dir() . '/wop-gap-empty-' . bin2hex(random_bytes(4));
        mkdir($empty . '/.wop', 0777, true);
        putenv('HOME=' . $empty);
        chdir($empty);
        try {
            $this->expectException(WopException::class);
            $this->expectExceptionMessage('未找到可读配置文件');
            WopConfigLoader::loadDefault();
        } finally {
            $this->removeTree($empty);
        }
    }

    public function testClasspathMissingResourceFailsFast(): void
    {
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('classpath 资源不存在');
        WopConfigLoader::load('classpath:no-such.json');
    }

    public function testDefaultClientLoadsFromEnv(): void
    {
        putenv(WopConfigLoader::CONFIG_FILE_ENV . '=' . __DIR__ . '/fixtures/valid-wop-config.json');
        $client = WopClient::defaultClient();
        $this->assertInstanceOf(WopClient::class, $client);
        $this->assertSame($client, WopClient::defaultClient());
        WopClient::resetDefault();
        $this->assertNotSame($client, WopClient::defaultClient());
    }

    // ---------------- 私有构造行覆盖 ----------------

    public function testPrivateConstructorsAreNoOp(): void
    {
        foreach ([ConfigValidator::class, TransportFactory::class, WopConfigLoader::class] as $class) {
            $reflection = new \ReflectionClass($class);
            $ctor = $reflection->getConstructor();
            $this->assertNotNull($ctor);
            $ctor->invoke($reflection->newInstanceWithoutConstructor());
        }
    }

    // ---------------- 辅助 ----------------

    /** @param list<string> $unsetFields @param array<string, mixed> $overrides */
    private function validJson(?string $blankField = null, array $overrides = [], array $unsetFields = []): string
    {
        $base = json_decode((string) file_get_contents(__DIR__ . '/fixtures/valid-wop-config.json'), true);
        self::assertIsArray($base);
        $base['appKey'] = 'app_001';
        if ($blankField !== null) {
            $base[$blankField] = '  ';
        }
        foreach ($unsetFields as $f) {
            unset($base[$f]);
        }
        foreach ($overrides as $k => $v) {
            $base[$k] = $v;
        }
        return (string) json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function baseBuilder(): \Wop\Sdk\Config\Builder
    {
        $keys = self::keys()['rsa3072'];
        return WopSdkConfig::builder()
            ->appKey('app_001')
            ->suite('WOP-RSA3072-SHA256')
            ->merchantPrivateKey($keys['privatePkcs8B64'])
            ->platformPublicKey($keys['publicSpkiB64'])
            ->serverRoot('https://gw.example.com/gateway');
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
