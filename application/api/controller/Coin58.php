<?php

namespace app\api\controller;

use app\admin\model\ban\Bg;
use app\api\library\GateLib;
use app\common\controller\Api;
use app\common\model\Config;
use fast\Http;
use think\Db;

/**
 * 首页接口
 */
class Coin58 extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    private $baseUrl = 'https://openapi.58ex.com';

    private $key = '';
    private $secret = '';
    private $access_token = '';

    private $moneyRate = 5;

    // 1.行情orderbook
    // 2.账户余额
    // 3.下单接口

    public function __construct()
    {
        parent::__construct();
        $this->key = \config('58coin.key');
        $this->secret = \config('58coin.secret');
        $this->access_token = \config('58coin.ACCESS_TOKEN');
    }


    public function place_plan()
    {
        for ($i = 0; $i < 8; $i++) {
            $this->place();
            sleep(6);
        }
    }

    public function place()
    {
        $config = $this->get58Config();
        $lastPrice = $config['last_price'];
        $ticker = $this->get_ticker();
        $balance = $this->balance();
        // 买入
        if (($lastPrice - $ticker['ask'][0]) / $ticker['ask'][0] > 0.001) {

            $totalMoney = $config['eos'] * $ticker['ask'][0] + $config['usdt'] * 1;
            $halfMoney = $totalMoney / 2;
            $needBuy = ($halfMoney - $config['eos'] * $ticker['ask'][0]) / $ticker['ask'][0];
            $amount = round($needBuy, 2);
            if($amount>=0.1 && $balance['usdt']['available']>=$ticker['ask'][0]*$amount){
                $orderId = $this->order(1, $ticker['ask'][0], $amount);
                $config['last_price'] = $ticker['ask'][0];
                $config['usdt'] = $config['usdt'] - $amount*$ticker['ask'][0];
                $config['eos'] = $config['eos'] + $amount;
                $this->update58Config($config);
                trace('买入：[' . $ticker['ask'][0] . ',' . $amount . '],orderId:' . $orderId, 'error');
            }
        }
        // 卖出
        else if (($ticker['bid'][0] - $lastPrice) / $ticker['bid'][0] > 0.001) {
            $totalMoney = $config['eos'] * $ticker['bid'][0] + $config['usdt'] * 1;
            $halfMoney = $totalMoney / 2;
            $needSell = ($halfMoney - $config['usdt']) / $ticker['bid'][0];
            $amount = round($needSell, 2);
            if($amount>=0.1 && $balance['eos']['available']>=$amount) {
                $orderId = $this->order(2, $ticker['bid'][0], $amount);
                $config['last_price'] = $ticker['bid'][0];
                $config['usdt'] = $config['usdt'] + $amount*$ticker['bid'][0];
                $config['eos'] = $config['eos'] - $amount;
                $this->update58Config($config);
                trace('卖出：[' . $ticker['bid'][0] . ',' . $amount . '],orderId:' . $orderId, 'error');
            }
        }
        $this->success('请求成功');
    }


    // 1买入，2卖出
    public function order($side, $price, $amount)
    {
        $url = 'https://api.58ex.com/orders/place';
        $requestData = [
            'stp' => 1,
            'orderFrom' => 0,
            'productId' => 1441800,
            'type' => 1,
            'side' => $side, // 1买入，2卖出
            'price' => $price,
            'size' => $amount,
            'timeInForce' => 1,
            'postOnly' => 0,
            'tradePass' => ''
        ];
        $header = [
            'ACCESS_TOKEN:' . $this->access_token,
        ];
        $orderId = json_decode(Http::post($url, $requestData, [CURLOPT_HTTPHEADER => $header]), true)['data']['order'];
        return $orderId;
    }

    public function test_order($side, $price, $amount)
    {
        $url = 'https://api.58ex.com/orders/place';
        $requestData = [
            'stp' => 1,
            'orderFrom' => 0,
            'productId' => 1441800,
            'type' => 1,
            'side' => $side, // 1买入，2卖出
            'price' => $price,
            'size' => $amount,
            'timeInForce' => 1,
            'postOnly' => 0,
            'tradePass' => ''
        ];
        $header = [
            'ACCESS_TOKEN:' . $this->access_token,
        ];
        $orderId = json_decode(Http::post($url, $requestData, [CURLOPT_HTTPHEADER => $header]), true);
        $this->success('请求成功',$orderId);
    }

    public function get_ticker()
    {
        $url = $this->baseUrl . '/v1/spot/order_book?symbol=eos_usdt&limit=100';
        $res = json_decode(Http::get($url), true)['data'];
        $ask = $res['asks'][0];
        $bid = $res['bids'][count($res['bids']) - 1];
        return ['ask' => $ask, 'bid' => $bid];
    }

    public function balance()
    {
        $url = 'https://api.58ex.com/user/assets';
        $header = [
            'ACCESS_TOKEN:' . $this->access_token,
        ];
        $money = ['usdt' => [], 'eos' => []];
        $res = json_decode(Http::post($url, [], [CURLOPT_HTTPHEADER => $header]), true)['data']['assets'];
        for ($i = 0; $i < count($res); $i++) {
            if ($res[$i]['currencyId'] == 8) {
                $money['usdt'] = $res[$i];
            }
            if ($res[$i]['currencyId'] == 22) {
                $money['eos'] = $res[$i];
            }
        }
        return $money;
    }


    private function get58Config()
    {
        $config = new Config();
        $result = $config->where("name", "58_config")->find();
        if (!$result) {
            $balance = $this->balance();
            $data = [
                'last_price' => 0,
                'usdt' => $balance['usdt']['available']*$this->moneyRate,
                'eos' => $balance['eos']['available']*$this->moneyRate,
            ];
            Db::name('config')->insert([
                'name' => '58_config',
                'group' => 'dictionary',
                'type' => 'string',
                'content' => json_encode($data),
                'value' => json_encode($data)
            ]);
            return $data;
        }
        return json_decode($result['value'], true);
    }

    private function update58Config($exConfig)
    {
        $config = new Config();
        $result = $config->where("name", "58_config")->find();
        $result['value'] = json_encode($exConfig);
        Db::name('config')->update(['value' => $result['value'], 'id' => $result['id']]);
    }

//    public function balance()
//    {
//        list($msec, $sec) = explode(' ', microtime());
//        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
//        $time = substr($msectime,0,13);
//        $data = 'AccessKeyId='.$this->key.'&SignatureMethod=HmacSHA256&SignatureVersion=2&Timestamp='.$time;
//        $sign = hash_hmac('sha256', $data, $this->secret);
//        $url = $this->baseUrl.'/v1/spot/my/accounts?X-58COIN-APIKEY='.$this->key.'&Timestamp='.$time.'&Signature='.$sign;
//        $res = json_decode(Http::get($url),true);
//        $this->success('请求成功',$res);
//    }


}
