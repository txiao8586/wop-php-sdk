# wop-php-sdk 变异测试报告

- 分母口径：KILLED + SURVIVED（INVALID 语法破坏排除）
- 总变异体：878（killed 728 / survived 150 / invalid 0）
- **击杀率 MSI = 82.92%**（728/878）

| 算子 | 变异 | killed | survived | invalid | 击杀率 |
|---|---|---|---|---|---|
| arith-minus | `-`→`+` | 10 | 2 | 0 | 83.3% |
| arith-plus | `+`→`-` | 8 | 0 | 0 | 100.0% |
| bit-xor | `^`→`|` | 2 | 0 | 0 | 100.0% |
| bool-flip | true↔false | 26 | 16 | 0 | 61.9% |
| eq-neg | `===`→`!==` | 90 | 1 | 0 | 98.9% |
| int-plus1 | 整数 n→n+1 | 74 | 27 | 0 | 73.3% |
| int-zero | 整数 n→0 | 80 | 21 | 0 | 79.2% |
| logic | `&&`↔`||` | 49 | 8 | 0 | 86.0% |
| neq-neg | `!==`→`===` | 45 | 1 | 0 | 97.8% |
| not-del | `!x`→`x` | 28 | 0 | 0 | 100.0% |
| rel-gt | `>`→`>=` | 6 | 3 | 0 | 66.7% |
| rel-gte | `>=`→`>` | 6 | 2 | 0 | 75.0% |
| rel-lt | `<`→`<=` | 7 | 10 | 0 | 41.2% |
| rel-lte | `<=`→`<` | 5 | 3 | 0 | 62.5% |
| str-empty | 字符串→`''` | 292 | 56 | 0 | 83.9% |

## 存活变异体（survived，人工复核等价性）
- `src/Aes256Gcm.php:44` logic：`||` → `&&`
- `src/Aes256Gcm.php:44` rel-lt：`<` → `<=`
- `src/Base64Url.php:52` bool-flip：`true` → `false`
- `src/Base64Url.php:59` int-plus1：`90` → `91`
- `src/Base64Url.php:62` int-plus1：`122` → `123`
- `src/Base64Url.php:65` int-plus1：`57` → `58`
- `src/Config/ConfigJsonParser.php:55` str-empty：`'}'` → `''`
- `src/Config/ConfigJsonParser.php:80` str-empty：`'expiredSeconds'` → `''`
- `src/Config/ConfigJsonParser.php:120` str-empty：`'}'` → `''`
- `src/Config/ConfigJsonParser.php:127` str-empty：`'httpClient.connectTimeout'` → `''`
- `src/Config/ConfigJsonParser.php:129` str-empty：`'readTimeout'` → `''`
- `src/Config/ConfigJsonParser.php:130` str-empty：`'httpClient.readTimeout'` → `''`
- `src/Config/ConfigJsonParser.php:133` str-empty：`'httpClient.maxRetryCount'` → `''`
- `src/Config/ConfigJsonParser.php:168` bool-flip：`true` → `false`
- `src/Config/ConfigJsonParser.php:175` rel-gte：`>=` → `>`
- `src/Config/ConfigJsonParser.php:210` int-plus1：`4` → `5`
- `src/Config/ConfigJsonParser.php:210` rel-gt：`>` → `>=`
- `src/Config/ConfigJsonParser.php:213` int-plus1：`4` → `5`
- `src/Config/ConfigJsonParser.php:213` int-zero：`4` → `0`
- `src/Config/ConfigJsonParser.php:217` int-plus1：`4` → `5`
- `src/Config/ConfigJsonParser.php:217` int-zero：`4` → `0`
- `src/Config/ConfigJsonParser.php:218` str-empty：`'UTF-8'` → `''`
- `src/Config/ConfigJsonParser.php:226` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:229` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:238` int-plus1：`1` → `2`
- `src/Config/ConfigJsonParser.php:239` str-empty：`'配置字段 '` → `''`
- `src/Config/ConfigJsonParser.php:245` bool-flip：`false` → `true`
- `src/Config/ConfigJsonParser.php:258` str-empty：`'"'` → `''`
- `src/Config/ConfigJsonParser.php:264` str-empty：`'t'` → `''`
- `src/Config/ConfigJsonParser.php:264` logic：`||` → `&&`
- `src/Config/ConfigJsonParser.php:264` str-empty：`'f'` → `''`
- `src/Config/ConfigJsonParser.php:264` logic：`||` → `&&`
- `src/Config/ConfigJsonParser.php:264` str-empty：`'n'` → `''`
- `src/Config/ConfigJsonParser.php:267` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:278` str-empty：`'}'` → `''`
- `src/Config/ConfigJsonParser.php:292` str-empty：`']'` → `''`
- `src/Config/ConfigJsonParser.php:302` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:311` rel-gte：`>=` → `>`
- `src/Config/ConfigJsonParser.php:321` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:336` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:339` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:344` rel-lt：`<` → `<=`
- `src/Config/ConfigJsonParser.php:356` logic：`||` → `&&`
- `src/Config/ConfigJsonParser.php:367` str-empty：`'配置文件 JSON 解析失败: '` → `''`
- `src/Config/ConfigValidator.php:60` rel-lte：`<=` → `<`
- `src/Config/ConfigValidator.php:60` rel-lte：`<=` → `<`
- `src/Config/ConfigValidator.php:60` logic：`||` → `&&`
- `src/Config/ConfigValidator.php:87` bool-flip：`false` → `true`
- `src/Config/ConfigValidator.php:93` str-empty：`'fragment'` → `''`
- `src/Config/HttpClientSettings.php:12` int-plus1：`10_000` → `11`
- `src/Config/HttpClientSettings.php:13` int-plus1：`30_000` → `31`
- `src/Config/HttpClientSettings.php:14` int-plus1：`3` → `4`
- `src/Config/HttpClientSettings.php:14` int-zero：`3` → `0`
- `src/Config/WopConfigLoader.php:15` str-empty：`'config/wopSdkConfig.json'` → `''`
- `src/Config/WopConfigLoader.php:37` str-empty：`'classpath:'` → `''`
- `src/Config/WopConfigLoader.php:45` str-empty：`'\\'` → `''`
- `src/Config/WopConfigLoader.php:45` str-empty：`'/'` → `''`
- `src/Config/WopConfigLoader.php:46` str-empty：`'file:'` → `''`
- `src/Config/WopConfigLoader.php:51` bool-flip：`false` → `true`
- `src/Config/WopConfigLoader.php:52` str-empty：`'配置文件读取失败: '` → `''`
- `src/Config/WopConfigLoader.php:84` bool-flip：`true` → `false`
- `src/Config/WopConfigLoader.php:87` str-empty：`'.'` → `''`
- `src/Config/WopConfigLoader.php:88` str-empty：`'HOME'` → `''`
- `src/Config/WopConfigLoader.php:88` str-empty：`'USERPROFILE'` → `''`
- `src/Config/WopConfigLoader.php:93` neq-neg：`!==` → `===`
- `src/Config/WopConfigLoader.php:94` str-empty：`'.wop'` → `''`
- `src/Config/WopConfigLoader.php:98` bool-flip：`false` → `true`
- `src/Config/WopConfigLoader.php:104` bool-flip：`false` → `true`
- `src/Config/WopConfigLoader.php:109` str-empty：`', '` → `''`
- `src/Config/WopConfigLoader.php:116` str-empty：`'\\'` → `''`
- `src/Config/WopConfigLoader.php:116` str-empty：`'/'` → `''`
- `src/Config/WopConfigLoader.php:118` str-empty：`'file:'` → `''`
- `src/Config/WopConfigLoader.php:123` str-empty：`'配置文件不可读: '` → `''`
- `src/Config/WopConfigLoader.php:127` bool-flip：`false` → `true`
- `src/Config/WopConfigLoader.php:128` str-empty：`'配置文件读取失败: '` → `''`
- `src/Config/WopConfigLoader.php:143` bool-flip：`false` → `true`
- `src/Config/WopConfigLoader.php:144` str-empty：`'classpath 资源读取失败: '` → `''`
- `src/Config/WopConfigLoader.php:153` str-empty：`'/'` → `''`
- `src/Config/WopSdkConfig.php:34` str-empty：`'WopSdkConfig[appKey='` → `''`
- `src/Config/WopSdkConfig.php:35` str-empty：`', suite='` → `''`
- `src/Config/WopSdkConfig.php:37` str-empty：`', serverRoot='` → `''`
- `src/Config/WopSdkConfig.php:38` str-empty：`', backupServerRoots='` → `''`
- `src/Config/WopSdkConfig.php:39` str-empty：`', expiredSeconds='` → `''`
- `src/Config/WopSdkConfig.php:40` str-empty：`', httpClient='` → `''`
- `src/Config/WopSdkConfig.php:41` str-empty：`'connectTimeout'` → `''`
- `src/Config/WopSdkConfig.php:42` str-empty：`'readTimeout'` → `''`
- `src/Config/WopSdkConfig.php:43` str-empty：`'maxRetryCount'` → `''`
- `src/Config/WopSdkConfig.php:45` str-empty：`']'` → `''`
- `src/ContentDigest.php:40` logic：`||` → `&&`
- `src/ContentDigest.php:40` int-zero：`1` → `0`
- `src/ContentDigest.php:45` eq-neg：`===` → `!==`
- `src/ContentDigest.php:46` bool-flip：`true` → `false`
- `src/EncryptHeader.php:46` int-zero：`1` → `0`
- `src/EncryptedEnvelope.php:31` int-plus1：`512` → `513`
- `src/RequestId.php:31` int-plus1：`6` → `7`
- `src/RequestId.php:31` int-zero：`6` → `0`
- `src/RequestId.php:31` int-plus1：`6` → `7`
- `src/RequestId.php:31` int-zero：`6` → `0`
- `src/RequestId.php:31` int-plus1：`0x0f` → `1`
- `src/RequestId.php:31` int-zero：`0x0f` → `0`
- `src/RequestId.php:31` int-plus1：`0x40` → `1`
- `src/RequestId.php:31` int-zero：`0x40` → `0`
- `src/RequestId.php:32` int-plus1：`8` → `9`
- `src/RequestId.php:32` int-zero：`8` → `0`
- `src/RequestId.php:32` int-plus1：`8` → `9`
- `src/RequestId.php:32` int-zero：`8` → `0`
- `src/RequestId.php:32` int-plus1：`0x3f` → `1`
- `src/RequestId.php:32` int-zero：`0x3f` → `0`
- `src/RequestId.php:32` int-plus1：`0x80` → `1`
- `src/RequestId.php:32` int-zero：`0x80` → `0`
- `src/RsaOaep.php:43` int-zero：`7` → `0`
- `src/RsaOaep.php:74` arith-minus：`-` → `+`
- `src/RsaOaep.php:74` arith-minus：`-` → `+`
- `src/RsaOaep.php:74` int-zero：`1` → `0`
- `src/RsaOaep.php:83` rel-lt：`<` → `<=`
- `src/RsaOaep.php:102` str-empty：`'RSA 私钥解析失败: '` → `''`
- `src/RsaSigner.php:73` str-empty：`'RSA 公钥解析失败（应为 SPKI PEM 或 Base64）: '` → `''`
- `src/SignHeader.php:38` bool-flip：`false` → `true`
- `src/SignHeader.php:38` logic：`||` → `&&`
- `src/SignHeader.php:38` rel-lte：`<=` → `<`
- `src/SignHeader.php:43` int-zero：`1` → `0`
- `src/Suite.php:57` bool-flip：`true` → `false`
- `src/Suite.php:57` bool-flip：`true` → `false`
- `src/Transport/CurlTransport.php:18` int-plus1：`65536` → `65537`
- `src/Transport/CurlTransport.php:18` int-zero：`65536` → `0`
- `src/Transport/CurlTransport.php:29` rel-gt：`>` → `>=`
- `src/Transport/CurlTransport.php:30` int-plus1：`10_000` → `11`
- `src/Transport/CurlTransport.php:30` int-zero：`10_000` → `0`
- `src/Transport/CurlTransport.php:36` rel-gt：`>` → `>=`
- `src/Transport/CurlTransport.php:37` int-zero：`30_000` → `0`
- `src/Transport/CurlTransport.php:65` int-plus1：`1` → `2`
- `src/Transport/CurlTransport.php:65` int-zero：`1` → `0`
- `src/Transport/CurlTransport.php:93` int-plus1：`1` → `2`
- `src/Transport/GuzzleTransport.php:19` int-plus1：`65536` → `65537`
- `src/Transport/GuzzleTransport.php:26` str-empty：`'timeout'` → `''`
- `src/Transport/GuzzleTransport.php:26` int-plus1：`30` → `31`
- `src/Transport/GuzzleTransport.php:26` int-zero：`30` → `0`
- `src/Transport/GuzzleTransport.php:36` str-empty：`'stream'` → `''`
- `src/Transport/GuzzleTransport.php:36` bool-flip：`true` → `false`
- `src/Transport/GuzzleTransport.php:44` str-empty：`', '` → `''`
- `src/Transport/GuzzleTransport.php:75` int-plus1：`1` → `2`
- `src/Transport/TransportFactory.php:23` str-empty：`'未找到可用传输（ext-curl 未安装）；请启用 ext-curl 或构造注入 GuzzleTransport'` → `''`
- `src/WopClient.php:97` logic：`||` → `&&`
- `src/WopClient.php:105` str-empty：`': '` → `''`
- `src/WopClient.php:244` str-empty：`'响应套件 '` → `''`
- `src/WopClient.php:244` str-empty：`' 与客户端配置 '` → `''`
- `src/WopClient.php:256` bool-flip：`true` → `false`
- `src/WopClient.php:278` str-empty：`' 字节与套件 '` → `''`
- `src/WopClient.php:375` str-empty：`'已签名头 '` → `''`
- `src/WopClient.php:385` int-plus1：`1000` → `1001`
