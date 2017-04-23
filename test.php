<?php
/**
 * Created by PhpStorm.
 * User: bajian
 * Date: 2017/4/21
 * Time: 16:44
 */


require 'RedPacketApi.php';
require 'RedPacketConfig.php';
//use RedPacketApi;
//use RedPacketConfig;

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
//    'client_ip'=>'113.97.26.126'//发送的服务器ip地址
    'client_ip'=>$api->getServerIp()//发送的服务器ip地址
]);

var_dump($re);