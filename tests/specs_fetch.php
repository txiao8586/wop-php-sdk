<?php

declare(strict_types=1);

/**
 * 黄金向量钉版拉取（对齐 java-sdk 构建期模式）：真源 wop-platform/wop-specs，
 * 副本不落库，按 ref+sha256 拉取校验，mismatch 即失败（fail-closed）。
 * 升级流程见 CONTRIBUTING：先合 wop-specs PR，再同步 WOP_SPECS_REF 与下方 sha256。
 * 消费方：phpunit（tests/bootstrap.php）与 behat（FeatureContext）。
 */

const WOP_SPECS_REF = 'a1f7feb5b4af5c9052149e2adfd2063caa102677';

$wopSpecsSha256 = [
    'crypto/crypto-vectors.json' => 'c7dd65740a1da9a5cfb804bca0c8b1bd7d4ab967adeb075297677a4bef148736',
    'interop/v1/interop-cases.json' => 'c920ca1a93ccb3899a659f59fed6ec4652cf9e1b3b58bbdac23c45ac3ed2353e',
];

foreach ($wopSpecsSha256 as $src => $expected) {
    $url = 'https://raw.githubusercontent.com/wop-platform/wop-specs/' . WOP_SPECS_REF . "/{$src}";
    $data = file_get_contents($url);
    if ($data === false) {
        fwrite(STDERR, "wop-specs {$src} 拉取失败\n");
        exit(1);
    }
    $digest = hash('sha256', $data);
    if ($digest !== $expected) {
        fwrite(STDERR, "wop-specs {$src} sha256 mismatch：期望 {$expected}，实得 {$digest}；向量升级须先合 wop-specs PR 再同步钉版\n");
        exit(1);
    }
    file_put_contents(__DIR__ . '/fixtures/' . basename($src), $data);
}
