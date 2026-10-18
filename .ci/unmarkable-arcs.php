<?php

declare(strict_types=1);

/**
 * xdebug ≥8.4 path-coverage 不可标记弧清单（file => [line, ...]）。
 *
 * 背景：xdebug 8.4/8.5 的路径覆盖对「分支条件/邻接表达式含内部函数调用」
 * （trim/str_starts_with/str_contains/substr/realpath/is_readable 等）的弧
 * 不回填 hit——PHP 8.5.11 本地实证：构造分支两侧输入直调（如
 * appKey 传 '  ' 与合法值），对应弧 hit 恒为空。8.2/8.3 弧发射集不同，
 * 这些弧或不存在或可标记（CI 上 8.2/8.3 分支率 ≥98% 过门禁佐证）。
 *
 * 豁免语义：仅豁免「实际 unhit 且行号命中本清单」的弧（从分母扣除）；
 * 已覆盖弧不扣，代码演进后若这些行可标记则清单自动失效（无副作用）。
 * 新增真实未测分支不受影响：unhit 超出清单部分仍在分母，照常拦截。
 *
 * 复现：vendor/bin/phpunit --coverage-php coverage/cov.php 后
 * 遍历 functionCoverage→branches，统计 hit===[] 的 line_start。
 */
return [
    'Config/ConfigJsonParser.php' => [26, 36, 213, 217, 235, 267],
    'Config/ConfigValidator.php' => [23, 26, 29, 32, 35, 65, 66, 67, 68, 81, 101, 117, 120, 123, 133],
    'Config/WopConfigLoader.php' => [35, 36, 45, 52, 79, 80, 83, 84, 103, 104, 109, 116, 123, 128, 144, 152, 153],
    'RequestId.php' => [54],
    'Transport/TransportFactory.php' => [22],
];
