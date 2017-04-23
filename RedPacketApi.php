<?php
/**
 * Created by PhpStorm.
 * User: bajian
 * Date: 2017/4/21
 * Time: 14:53
 * 使用方式参考test.php
 */

class RedPacketApi
{
    const URL_SEND_RED_PACKET = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack';

    // 相关配置必备参数
    protected $_parameters = [];

    //从RedPacketConfig中注入的重要配置参数
    private $_keys;

    //从RedPacketConfig中注入的重要证书参数
    private $_cert;

    public $_lastMchBillno;

    //错误消息
    public $error;



    /**
     * 初始化配置，设置关键字和证书
     * @param array $config
     * @throws Exception
     */
    public function __construct($config)
    {
        $keys = [
            'wxappid',
            'mch_id',
            'key'
        ];
        $files = [
            'api_cert',
            'api_key',
            'rootca'
        ];

        foreach ($keys as $key) {
            try {
                $this->_keys[$key] = $config[$key];
            } catch (Exception $e) {
                throw new Exception('参数缺失：' . $key);
            }
        }
        $this->setParameter($this->_keys);

        foreach ($files as $file) {
            try {
                $cret_file = $config[$file];
                if (is_file($cret_file)) {
                    $this->_cert[$file] = $cret_file;
                }
            } catch (Exception $e) {
                throw new Exception('证书错误');
            }
        }
    }


    /**
     * 设置红包发放所需要的参数
     * @param  $parameter 键值数组/键
     * @param  $value 值
     * @return RedPacketApi
     */
    public function setParameter($parameter, $value = null)
    {
        if (! is_array($parameter)) {
            $parameter= [
                $parameter => $value
            ];
        }

        foreach ($parameter as $key => $value) {
            $key = trim($key);
            $value = trim($value);
            $this->_parameters[$key] = $value;
        }
        return $this;
    }

    /**
     * 获取参数值
     * @param  $parameter 键名
     * @return multitype:
     */
    public function getParameter($parameter)
    {
        return $this->_parameters[$parameter];
    }


    /**
     * @author bajian
     * @param array $parameters
     * @return bool|SimpleXMLElement
     */
    public function sendRedPacket(Array $parameters){
        // 官方硬性要求,貌似新版已经取消
//        $stime = date('H:i');
//        if ($stime < '08:00') {
//            $this->error = '请北京时间0：00-8：00时间之外触发红包赠送';
//            return false;
//        }
        $required = [
            're_openid',
            'nick_name',
            'send_name',
            'total_amount',
            'wishing',
            'act_name',
            'remark',
            'client_ip'
        ];
        foreach ($required as $key) {
            if (isset($parameters[$key]) && $parameters[$key]) {
                $this->setParameter($key, $parameters[$key]);
            } else
                if (! isset($this->_parameters[$key]) || ! $this->_parameters[$key]) {
                    $this->error = '参数缺失:' . $key;
                    return false;
                }
        }

        if (! isset($parameters['mch_billno'])) {
            $parameters['mch_billno'] = $this->getMchBillno();
        }


        $this->setParameter('mch_billno', $parameters['mch_billno']);
        $this->setParameter('total_num', 1);
        $this->setParameter('nonce_str', $this->getRand(30, 3));


        $postXml = $this->_createXml();

        $result = $this->curl_post_ssl(self::URL_SEND_RED_PACKET, $postXml);



        if (! $result)
            return false;
        $resultObj = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $resultObj;
//        var_dump(json_encode($resultObj,JSON_UNESCAPED_UNICODE));
//        var_dump(json_encode(simplexml_load_string($postXml, 'SimpleXMLElement', LIBXML_NOCDATA),JSON_UNESCAPED_UNICODE));




    }

    /**
     * 生成商户的订单号 最长为28个字符
     * @return string
     */
    public function getMchBillno()
    {
        $this->_lastMchBillno = $this->_parameters['mch_id'] . date('ymdHis') . $this->getRand(5); // $this->getRandNum();
        return $this->_lastMchBillno;
    }

    /**
     * 获取随机数
     * @param number $len 随机数的位数
     * @param number $type 取值范围 1表示数字 2小写字母 3数字小写混合 4大写字母
     * @return string
     */
    public function getRand($len = 30, $type = 3)
    {
        $str = '';
        $max = - 1;

        if (! $type) {
            $type = 3;
        }

        if ($type & 1) {
            $str .= '1234567890';
            $max += 10;
        }

        if ($type & 2) {
            $str .= 'abcdefghijklmnopqrstuvwxyz';
            $max += 26;
        }

        if ($type & 4) {
            $str .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $max += 26;
        }

        $rand = '';
        for ($i = 0; $i < $len; $i ++) {
            $rand .= $str[rand(0, $max)];
        }

        return $rand;
    }


    /**
     * 创建XML的方法
     * @param number $retcode
     * @param string $reterrmsg
     * @return boolean|string
     */
    private function _createXml()
    {
        try {
            $sign = $this->_getSign();
            if (! $sign) {
                return false;
            }
            $this->setParameter('sign', $sign);

            return $this->arrayToXml($this->_parameters);
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }


    /**
     * 参数转换成XML
     * @param array $arr 参数数组
     * @return string
     */
    public function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }


    /**
     * 获得签名结果
     * @return boolean|Ambigous <string, boolean>
     */
    protected function _getSign()
    {
        try {

            if ($this->_checkSign() == false) { // 检查生成签名参数
                $this->error = '生成签名参数缺失！';
                return false;
            }

            ksort($this->_parameters);
            $unSignParaString = $this->_formatQueryParaMap($this->_parameters, false);
            return $this->_sign($unSignParaString, $this->_keys['key']);
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }


    /**
     * 检查签名所需参数是否齐全
     * @return boolean
     */
    private function _checkSign()
    {
        // return true;
        if ($this->_parameters["nonce_str"] == null ||
            $this->_parameters["mch_billno"] == null ||
            $this->_parameters["mch_id"] == null ||
            $this->_parameters["wxappid"] == null ||
            $this->_parameters["nick_name"] == null ||
            $this->_parameters["send_name"] == null ||
            $this->_parameters["re_openid"] == null ||
            $this->_parameters["total_amount"] == null ||
            $this->_parameters["total_num"] == null ||
            $this->_parameters["wishing"] == null ||
            $this->_parameters["client_ip"] == null ||
            $this->_parameters["act_name"] == null ||
            $this->_parameters["remark"] == null) {
            return false;

        }
        return true;
    }

    /**
     * 将数组转成 key=value&key=value 的参数类型
     * @param  $paraMap
     * @param  $urlencode
     * @return string
     */
    private function _formatQueryParaMap($paraMap,$urlencode)
    {
        $buff = "";
        $reqPar = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if (null != $v && "null" != $v && "sign" != $k && "key" != $k) {
                if ($urlencode) {
                    $v = urlencode($v);
                }
                $buff .= $k . "=" . $v . "&";
            }
        }
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }


    /**
     * 签名
     * @param $content string 签名的字符串
     * @param $key string 密钥
     * @throws Exception
     * @return string|boolean
     */
    private function _sign($content, $key)
    {
        try {
            if (null == $key) {
                $this->error = '签名key不能为空！';
                return false;
            }
            if (null == $content) {
                $this->error = '签名内容不能为空';
                return false;
            }
            $signStr = $content . "&key=" . $key;
            return strtoupper(md5($signStr));

        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }


    /**
     * cURL抓取
     *
     * @param $url 链接地址
     * @param $vars 参数
     * @param
     *          $second
     * @param
     *          $aHeader
     * @return mixed|boolean
     */
    private function curl_post_ssl($url, $data, $second = 30, $aHeader = [])
    {
        $ch = curl_init();
        // 超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 这里设置代理，如果有的话
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // cert 与 key 分别属于两个.pem文件
        curl_setopt($ch, CURLOPT_SSLCERT, $this->_cert['api_cert']);
        curl_setopt($ch, CURLOPT_SSLKEY, $this->_cert['api_key']);
        curl_setopt($ch, CURLOPT_CAINFO, $this->_cert['rootca']);
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $this->error = 'aa:'.curl_errno($ch);
            curl_close($ch);
            return false;
        }
    }

    /**
     * 获取服务器ip
     *
     * @return string
     */
    public function getServerIp()
    {
        $server_ip = '127.0.0.1';
        if (isset($_SERVER)) {
            if (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR']) {
                $server_ip = $_SERVER['SERVER_ADDR'];
            } elseif (isset($_SERVER['LOCAL_ADDR']) && $_SERVER['LOCAL_ADDR']) {
                $server_ip = $_SERVER['LOCAL_ADDR'];
            }
        } else {
            $server_ip = getenv('SERVER_ADDR');
        }
        return $server_ip;
    }



}