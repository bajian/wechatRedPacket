
## 说明

这就是一个炒鸡简单 的 微信公众号向关注用户发红包的库

首先你得对微信发红包的相关文档有一定了解和开通微信红包服务
- [发普通红包](https://pay.weixin.qq.com/wiki/doc/api/tools/cash_coupon.php?chapter=13_4&index=3).

## 如何使用

### 1、导入自己的cert
将cert证书解压并放在`cert`目录中替换原有文件

>（原文件内容都是被我删掉一部分的了，毕竟是公司的密钥）

### 2、配置`RedPacketConfig.php`
```
    const API_KEY = '0bHtge4inMVYv3h1TRb2Ywxxxxxxxxxx';//注册商户的时候获得的KEY

    const MCH_ID = '12843xxxxx';//商户ID

    const APPID = 'wx05336cfd4exxxxx';//公众号APPID
```

### 3-1、普通PHP中调用API发红包了
```
require 'RedPacketApi.php';
require 'RedPacketConfig.php';

$api=new RedPacketApi([
    'wxappid'=>RedPacketConfig::APPID,
    'mch_id'=>RedPacketConfig::MCH_ID,
    'key'=>RedPacketConfig::API_KEY,
    'api_cert'=>RedPacketConfig::getRealPath(). RedPacketConfig::SSLCERT_PATH,//三个路径都是绝对路径
    'api_key'=>RedPacketConfig::getRealPath().RedPacketConfig::SSLKEY_PATH,
    'rootca'=>RedPacketConfig::getRealPath().RedPacketConfig::SSLROOTCA
]);

$re=$api->sendRedPacket([
    're_openid'=>'o4ugXtwanykZPmdiyN4iTA0L6u08',//发送的openid
    'nick_name'=>'e游艺',//没有用，但是需要的参数
    'send_name'=>'e游艺',//收到的红包名称
    'total_amount'=>'100',//金额 分
    'wishing'=>'恭喜发财',//红包祝福语，不超过32个字符
    'act_name'=>'商户余额提现',//活动名称，不会显示
    'remark'=>'商户余额提现',//备注，不会显示
    'client_ip'=>$api->getServerIp()//发送的服务器ip地址
]);//返回的是一个被解析过的xml对象,或者false表示失败，可以通过$api->error获得错误的原因

var_dump(json_encode($resultObj,JSON_UNESCAPED_UNICODE));
//{"return_code":"SUCCESS","return_msg":"发放成功","result_code":"SUCCESS","err_code":"SUCCESS"
,"err_code_des":"发放成功","mch_billno":"128xxxxx0120170421115229a9l"
,"mch_id":"1284xxxxx","wxappid":"wx05336cfd4ecxxxxx"
,"re_openid":"o4ugXtwanykZPmdiyN4iTA0L6u08","total_amount":"100"
,"send_listid":"1000041701201704213000104668287"}

```

### 3-2、laravel （Controller）中调用API发红包了
>假设库放在 \app\Http\Controllers\Api\lib 下
```
require 'lib/wechatRedPacket/RedPacketConfig.php';
require 'lib/wechatRedPacket/RedPacketApi.php';
use RedPacketApi;
use RedPacketConfig;

后面调用部分和`3-1`一样
```

#### 完整使用案例参见 `WithdrawController.php` 
> (laravel5.4中正常使用，包含很多业务逻辑，代码没那么整洁,多多包含，如有建议/指正，不胜欢迎)

TODO 暂时还没去写查询红包记录的接口

