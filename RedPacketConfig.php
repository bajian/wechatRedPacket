<?php

/**
 * Created by PhpStorm.
 * User: bajian
 * Date: 2017/4/21
 * Time: 14:56
 */
class RedPacketConfig
{
    //=======【证书路径设置】=====================================
    /**
     * 证书路径,注意应该填写绝对路径,发送红包和查询需要，可登录商户平台下载
     * API证书下载地址：https://pay.weixin.qq.com/index.php/account/api_cert，下载之前需要安装商户操作证书）
     * @var path 跟这个文件同一目录下的cert文件夹放置证书！！！！
     * PHP开发环境请使用商户证书文件apiclient_cert.pem和apiclient_key.pem ，rootca.pem是CA证书。
     */
    const SSLCRET12 = 'cert/apiclient_cert.p12';
    const SSLCERT_PATH = 'cert/apiclient_cert.pem';
    const SSLKEY_PATH = 'cert/apiclient_key.pem';
    const SSLROOTCA = 'cert/rootca.pem';

    const API_KEY = '0bHtge4inMVYv3h1TRb2Ywxxxxxxxxxx';//注册商户的时候获得的KEY

    const MCH_ID = '12843xxxxx';//商户ID

    const APPID = 'wx05336cfd4exxxxx';//公众号APPID


    //=======【证书路径设置】=====================================
    /**
     * 获取文件的路径，证书需要完整路径
     * @return string
     */
    public static function getRealPath(){
        return __DIR__.'/';
    }


}