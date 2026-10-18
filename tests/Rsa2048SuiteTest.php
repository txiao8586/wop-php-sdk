<?php

declare(strict_types=1);

namespace Wop\Sdk\Tests;

use Wop\Sdk\Config\WopSdkConfig;
use Wop\Sdk\WopClient;
use Wop\Sdk\WopConfig;
use Wop\Sdk\WopException;

/**
 * RSA2048 套件向量锚（2026-10-18 PR 审查修复回归）：
 * L2 build→verify 往返（ALG_BY_SUITE 补 RSA2048 条目的回归锚）与
 * 配置位宽校验（validateKeys 位宽与套件一致性）。
 */
final class Rsa2048SuiteTest extends VectorCase
{
    private const PATH = '/gateway/logistics.order.query';
    private const SUITE = 'WOP-RSA2048-SHA256';

    /** keys.rsa2048 fixture 便捷访问。 @return array{publicSpkiB64: string, privatePkcs8B64: string} */
    private static function k2048(): array
    {
        return self::keys()['rsa2048'];
    }

    /** c) RSA2048 L2 往返：出向 build 信封可被对端解开，入向 L2 响应 verify 走通。 */
    public function testL2BuildVerifyRoundtripWith2048Keys(): void
    {
        $keys = self::k2048();
        $body = '{"code":"00000","biz":"ok"}';
        $merchant = new WopClient(new WopConfig('app_10012481831', self::SUITE, $keys['privatePkcs8B64'], $keys['publicSpkiB64']));

        // 出向：draft 为 L2 信封；对端（测试即网关）解包 DEK 须为 AES-256-GCM 且能解开密文
        $draft = $merchant->buildRequest('POST', self::PATH, $body, 'L2');
        $encrypt = \Wop\Sdk\EncryptHeader::parse($draft->header('x-wop-encrypt'));
        $this->assertTrue($encrypt->isEncrypted());
        $this->assertNotNull($encrypt->dek);
        $dek = \Wop\Sdk\DekPayload::decode((string) \Wop\Sdk\RsaOaep::unwrap($encrypt->dek, $keys['privatePkcs8B64']));
        $this->assertTrue($dek->algMatches(self::SUITE), 'RSA2048 套件下 DEK alg 须为 AES-256-GCM');
        $plaintext = \Wop\Sdk\Aes256Gcm::decrypt(
            \Wop\Sdk\Base64Url::decode(\Wop\Sdk\EncryptedEnvelope::extract($draft->wireBody)),
            $dek->iv,
            $dek->key
        );
        $this->assertSame($body, $plaintext);

        // 入向：平台 L2 响应（平台私钥加签 + 商户公钥包 DEK）verify 走通
        [$headers, $wireBody] = $this->platformL2Response($body);
        $result = $merchant->verifyResponse($headers, $wireBody, self::PATH);
        $this->assertTrue((bool) $result->ok, 'L2 响应须验证通过: ' . (string) $result->reason);
        $this->assertSame($body, $result->plaintext);
    }

    /** d) 位宽校验：rsa2048 套件配 rsa3072 钥必须拒（configuration 类）。 */
    public function testValidatorRejects2048SuiteWith3072Keys(): void
    {
        $rsa3072 = self::keys()['rsa3072'];
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('位宽');
        WopSdkConfig::builder()
            ->appKey('app_10012481831')
            ->suite(self::SUITE)
            ->merchantPrivateKey($rsa3072['privatePkcs8B64'])
            ->platformPublicKey($rsa3072['publicSpkiB64'])
            ->serverRoot('https://gw.example.com/gateway')
            ->build();
    }

    /** d) 公钥位宽错配同样拒（platformPublicKey 分支）。 */
    public function testValidatorRejects2048SuiteWithMismatchedPublicKey(): void
    {
        $keys = self::k2048();
        $this->expectException(WopException::class);
        $this->expectExceptionMessage('platformPublicKey 位宽');
        WopSdkConfig::builder()
            ->appKey('app_10012481831')
            ->suite(self::SUITE)
            ->merchantPrivateKey($keys['privatePkcs8B64'])
            ->platformPublicKey(self::keys()['rsa3072']['publicSpkiB64'])
            ->serverRoot('https://gw.example.com/gateway')
            ->build();
    }

    /** d) 正向对照：rsa2048 套件配 2048 钥通过校验。 */
    public function testValidatorAccepts2048SuiteWithMatchingKeys(): void
    {
        $keys = self::k2048();
        $config = WopSdkConfig::builder()
            ->appKey('app_10012481831')
            ->suite(self::SUITE)
            ->merchantPrivateKey($keys['privatePkcs8B64'])
            ->platformPublicKey($keys['publicSpkiB64'])
            ->serverRoot('https://gw.example.com/gateway')
            ->build();
        $this->assertSame(self::SUITE, $config->suite);
    }

    /**
     * 构造平台→商户 L2 响应（平台私钥加签；DEK 用商户公钥 OAEP 包装）。
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function platformL2Response(string $plainBody): array
    {
        $keys = self::k2048();
        $suite = \Wop\Sdk\Suite::parse(self::SUITE);
        $dek = new \Wop\Sdk\DekPayload($suite->dekAlg, random_bytes(32), random_bytes(12));
        $encrypted = \Wop\Sdk\Aes256Gcm::encrypt($plainBody, $dek->key, $dek->iv);
        $wireBody = \Wop\Sdk\EncryptedEnvelope::wrap(\Wop\Sdk\Base64Url::encode($encrypted->cipherTag));

        $headers = [
            'x-wop-nonce' => 'respnonce00000000000000000000a',
            'x-wop-timestamp' => '1774340000000',
            'x-wop-content-digest' => \Wop\Sdk\ContentDigest::build($wireBody, $suite),
            'x-wop-encrypt' => 'L2;dek=' . \Wop\Sdk\RsaOaep::wrap($dek->encode(), $keys['publicSpkiB64']),
        ];
        $canonical = \Wop\Sdk\CanonicalRequest::build(
            'v1/1800',
            'POST',
            self::PATH,
            '',
            \Wop\Sdk\CanonicalRequest::canonicalHeaders($headers)
        );
        $signedNames = array_keys($headers);
        \sort($signedNames, SORT_STRING);
        $headers['x-wop-sign'] = \Wop\Sdk\SignHeader::build(
            self::SUITE,
            1800,
            $signedNames,
            \Wop\Sdk\RsaSigner::sign($canonical, $keys['privatePkcs8B64'])
        );
        return [$headers, $wireBody];
    }
}
