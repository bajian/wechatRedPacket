<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Cache;
use Auth;
use DB;
use Log;
use App\User;
use App\Merchant;
use App\MerchantChargeHistory;
use App\Car;
use Mockery\Exception;
require 'lib/wechatRedPacket/RedPacketConfig.php';
require 'lib/wechatRedPacket/RedPacketApi.php';
use RedPacketApi;
use RedPacketConfig;

class WithdrawController extends Controller
{
    const WITHDRAW_SECRET='asxxxxxxxxxxxxxx';

    const STATUS_SUCCESS=1;
    const STATUS_FAILURE=0;

    const TYPE_WITHDRAW=4;

    const MAX_PERDAY_WITHDRAW_TIMES=10;

    /**
     * @author bajian
     * @param $version
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function applyWithdraw($version,Request $request){
        $re_openid=$request->input('re_openid');
        $total_amount=$request->input('total_amount');//单位：元
        $order_id=$request->input('order_id');
        $send_name=$request->input('send_name','e游艺');
        $wishing=$request->input('wishing','恭喜发财');
        $act_name=$request->input('act_name','商户余额提现');
        $signature=$request->input('signature');
        if (!$this->checkAllArgsExist($re_openid,$total_amount,$order_id,$signature))
            return $this->toJson(-1,'参数不全');
        if ($signature!==md5( $re_openid.$total_amount.$order_id.self::WITHDRAW_SECRET ))
            return $this->toJson(-1,'签名不正确');
        $total_amount=intval($total_amount);
        if ($total_amount<1 || $total_amount>200)
            return $this->toJson(-1,'提现金额必须在1-200元内');

        $merchant=Merchant::where('openid',$re_openid)->first();
        if (!$merchant)
            return $this->toJson('商户不存在:'.$re_openid);
        if ($merchant->balance<$total_amount)
            return $this->toJson('商户余额不足');
        $history=MerchantChargeHistory::where('merchant_id',$merchant->id)
            ->where('type',4)
            ->where('order_id',$order_id)
            ->first();
        if ($history)
            return $this->toJson('订单号已经存在');
        $withdrawCount=MerchantChargeHistory::where('merchant_id',$merchant->id)
            ->where('type',self::TYPE_WITHDRAW)
            ->where('status',self::STATUS_SUCCESS)
            ->where('created_at','>',date('Y-m-d').' 00:00:00')->count();
        if ($withdrawCount>self::MAX_PERDAY_WITHDRAW_TIMES)
            return $this->toJson('今日提现次数已经达到上限');

        //提现中：
        $keyLockWithdraw='keyLockWithdraw'.$merchant->id;
        $islockWithdraw=Cache::get($keyLockWithdraw);
        if ($islockWithdraw)
            return $this->toJson('提现频率太频繁，请稍后重试');
        Cache::put($keyLockWithdraw,1,2);

        DB::beginTransaction();
        try{
            $merchant = Merchant::lockForUpdate()->find($merchant->id);
            $balance_before=$merchant->balance;
            $merchant->decrement('balance',$total_amount);//先扣钱再发红包
            $balance_after=$merchant->balance;

            $re=$this->_sendRedPacket($re_openid,$total_amount *100,$send_name,$send_name,$wishing,$act_name,'商户余额提现');
            $resultLog=json_encode($re['result'],JSON_UNESCAPED_UNICODE);
            Log::info("$re_openid withdraw " . $resultLog);
            if (!$re['result']){
                DB::rollBack();
                $this->saveSentRecord(self::STATUS_FAILURE,self::TYPE_WITHDRAW,$order_id,$merchant->id,$total_amount * -1,
                    $balance_before,$balance_before,$send_name,$send_name,$wishing,$act_name,$re['instance']->_lastMchBillno,$re['instance']->error);
            }elseif (isset($re['result']->return_code) && $re['result']->return_code =='SUCCESS'){//通信成功
                $result=$re['result'];
                if ($result->result_code =='SUCCESS'){
                    DB::commit();
                    $this->saveSentRecord(self::STATUS_SUCCESS,self::TYPE_WITHDRAW,$order_id,$merchant->id,$total_amount * -1,
                        $balance_before,$balance_after,$send_name,$send_name,$wishing,$act_name,$re['instance']->_lastMchBillno,'');
                    Cache::forget($keyLockWithdraw);
                    return $this->toJson();
                }else{//余额不足、签名错误等错误情况

                }

            }else{//通信失败

            }
            DB::rollBack();
            $insertId=$this->saveSentRecord(self::STATUS_FAILURE,self::TYPE_WITHDRAW,$order_id,$merchant->id,$total_amount * -1,
                $balance_before,$balance_before,$send_name,$send_name,$wishing,$act_name,$re['instance']->_lastMchBillno,$resultLog);
            Cache::forget($keyLockWithdraw);
            return $this->toJson('操作失败，请稍后重试，失败ID:'.$insertId);

        }catch (Exception $e){
            Log::error('withdraw Transaction error:'.$e->getMessage());
            DB::rollBack();
        }
    }

    /**
     * @author bajian
     * @param $status int 1成功，0失败
     * @param $merchant_id
     * @param $total_amount string 单位 元，正/负数
     * @param $balance_before
     * @param $balance_after
     * @param $nick_name
     * @param $send_name
     * @param $wishing
     * @param $act_name
     * @param string $remark
     */
    private function saveSentRecord($status,$type,$order_id,$merchant_id,$total_amount,$balance_before,$balance_after,$nick_name,$send_name,$wishing,$act_name,$billno='',$errors=''){
        $model=new MerchantChargeHistory;
        $model->status=$status;
        $model->merchant_id=$merchant_id;
        $model->money=$total_amount;
        $model->balance_before=$balance_before;
        $model->balance_after=$balance_after;
        $model->type=$type;
        $model->order_id=$order_id;
        $model->billno=$billno;
        $model->extra="$nick_name | $send_name | $wishing | $act_name";
        $model->errors=$errors;
        $model->save();
        return $model->id;
    }


    /**
     * 此方法不含任何校验，只负责发红包逻辑，慎重调用
     * @author bajian
     * @param $re_openid string 接受红包微信id
     * @param $total_amount int 红包金额 单位：分
     * @param $nick_name string 没有用，但是需要的参数
     * @param $send_name string 收到的红包名称
     * @param $wishing string 红包祝福语，不超过32个字符
     * @param $act_name string 活动名称
     * @param $remark string 备注，不会显示
     * @return array [ 'result'=>$re, 'instance'=>$api ];
     */
    protected function _sendRedPacket($re_openid,$total_amount,$nick_name,$send_name,$wishing,$act_name,$remark='商户余额提现'){
        $api=new RedPacketApi([
            'wxappid'=>RedPacketConfig::APPID,
            'mch_id'=>RedPacketConfig::MCH_ID,
            'key'=>RedPacketConfig::API_KEY,
            'api_cert'=>RedPacketConfig::getRealPath(). RedPacketConfig::SSLCERT_PATH,//三个路径都是绝对路径
            'api_key'=>RedPacketConfig::getRealPath().RedPacketConfig::SSLKEY_PATH,
            'rootca'=>RedPacketConfig::getRealPath().RedPacketConfig::SSLROOTCA
        ]);

        $re=$api->sendRedPacket([
            're_openid'=>$re_openid,//发送的openid
            'nick_name'=>$nick_name,//没有用，但是需要的参数
            'send_name'=>$send_name,//收到的红包名称
            'total_amount'=>$total_amount,//金额 分
            'wishing'=>$wishing,//红包祝福语，不超过32个字符
            'act_name'=>$act_name,//活动名称
            'remark'=>$remark,//备注，不会显示
            'client_ip'=>$api->getServerIp()//发送的服务器ip地址
        ]);
        return [
            'result'=>$re,
            'instance'=>$api
        ];
    }







}
