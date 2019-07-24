<?php

namespace app\api\controller;

use app\admin\model\ban\Bg;
use app\api\library\GateLib;
use app\common\controller\Api;
use app\common\model\Config;
use fast\Http;

/**
 * 首页接口
 */
class Ban extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    private $rate = 0.008;
    private $gateLib;

    private $minToken = ['ETH_USDT'=>1.1, 'EOS_USDT'=>30, 'XLM_USDT'=>2000];

    public function __construct()
    {
        parent::__construct();
        $this->gateLib = new GateLib();
    }

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功', ['name' => 'jack']);
    }

    public function coins()
    {
        $g_results = json_decode(Http::get('https://data.gateio.co/api2/1/pairs'), true);

        $b_results = $this->bcex_request('/api_market/getTradeLists');
        $b_results = $b_results['data'];
        $usdts = array_merge($b_results['main']['USDT'], $b_results['main']['ETH'], $b_results['main']['BTC']);
        $b_data = [];
        foreach ($usdts as $key => $value) {
            array_push($b_data, $value['token'] . '_' . $value['market']);
        }
        $results = [];
        foreach ($g_results as $key => $value) {
            foreach ($b_data as $k => $v) {
                if ($value == $v) {
                    array_push($results, $v);
                }
            }
        }
        $b_data = [];
        foreach ($results as $key => $value) {
            $name = explode('_', $value);
            $token = $name[0];
            $market = $name[1];
            $depth = $this->bcex_request('/api_market/market/depth', ['market' => $market, 'token' => $token]);
            $depth = $depth['data'];
            if (count($depth['bids']) > 0 && count($depth['asks']) > 0) {
                $b_data[$value]['b_bid'] = $depth['bids'][0]; // 买一
                $b_data[$value]['b_ask'] = $depth['asks'][count($depth['asks']) - 1]; // 卖一

                $g = json_decode(Http::get('https://data.gateio.co/api2/1/orderBook/' . $value), true);
                $b_data[$value]['g_bid'] = $g['bids'][0]; // 买一
                $b_data[$value]['g_ask'] = $g['asks'][count($g['asks']) - 1]; // 卖一

                $b_rate = ($b_data[$value]['b_bid'][0] - $b_data[$value]['g_ask'][0]) / $b_data[$value]['b_bid'][0];
                $g_rate = ($b_data[$value]['g_bid'][0] - $b_data[$value]['b_ask'][0]) / $b_data[$value]['g_bid'][0];

                $b_data[$value]['status'] = 0;
                $b_data[$value]['remark'] = '暂无机会！';
                $record = '暂无成交';
                if ($b_rate > $this->rate) {
                    $b_data[$value]['status'] = 1;
                    $b_data[$value]['remark'] = 'BCEX 买一(' . $b_data[$value]['b_bid'][0] . ')比 Gate卖一(' . $b_data[$value]['g_ask'][0] . ') 大' . $b_rate . '数量为：' . min($b_data[$value]['b_bid'][1], $b_data[$value]['g_ask'][1]);
                    // 去 BCEX 卖，Gate 买
                }
                if ($g_rate > $this->rate) {
                    $b_data[$value]['status'] = 2;
                    $b_data[$value]['remark'] = 'Gate 买一(' . $b_data[$value]['g_bid'][0] . ')比 BCEX卖一(' . $b_data[$value]['b_ask'][0] . ') 大' . $g_rate . '数量为：' . min($b_data[$value]['g_bid'][1], $b_data[$value]['b_ask'][1]);
                    // 去 BCEX 买，Gate 卖
                }
                $model = new Bg();
                $model->save([
                    'token' => $value,
                    'g_ask' => $b_data[$value]['g_ask'][0] . '-' . $b_data[$value]['g_ask'][1],
                    'g_bid' => $b_data[$value]['g_bid'][0] . '-' . $b_data[$value]['g_bid'][1],
                    'b_ask' => $b_data[$value]['b_ask'][0] . '-' . $b_data[$value]['b_ask'][1],
                    'b_bid' => $b_data[$value]['b_bid'][0] . '-' . $b_data[$value]['b_bid'][1],
                    'status' => $b_data[$value]['status'],
                    'remark' => $b_data[$value]['remark'],
                    'record' => $record,
                ]);
            }
//            if (count($b_data) > 3) {
//                break;
//            }
        }
        $this->success('请求成功', $b_data);
    }

    public function order()
    {
        $start = time();
        for ($i = 0; $i < 6; $i++) {
            $this->_order();
            sleep(2);
        }
        $end = time();
        trace('执行时间：' . ($end - $start), 'error');
        $this->success('请求成功', '执行时间：' . ($end - $start));
    }

    private function _order()
    {
//        $coins = ['ETH_USDT', 'EOS_USDT', 'XLM_USDT'];
        $coins = ['ETH_USDT',];
        foreach ($coins as $key => $value) {
            $name = explode('_', $value);
            $token = $name[0];
            $market = $name[1];
            $depth = $this->bcex_request('/api_market/market/depth', ['market' => $market, 'token' => $token]);
            $depth = $depth['data'];
            if (count($depth['bids']) > 0 && count($depth['asks']) > 0) {
                $b_data[$value]['b_bid'] = $depth['bids'][0]; // 买一
                $b_data[$value]['b_ask'] = $depth['asks'][count($depth['asks']) - 1]; // 卖一

                $g = json_decode(Http::get('https://data.gateio.co/api2/1/orderBook/' . $value), true);
                $b_data[$value]['g_bid'] = $g['bids'][0]; // 买一
                $b_data[$value]['g_ask'] = $g['asks'][count($g['asks']) - 1]; // 卖一

                $b_rate = ($b_data[$value]['b_bid'][0] - $b_data[$value]['g_ask'][0]) / $b_data[$value]['b_bid'][0];
                $g_rate = ($b_data[$value]['g_bid'][0] - $b_data[$value]['b_ask'][0]) / $b_data[$value]['g_bid'][0];

                $b_data[$value]['status'] = 10;
                $b_data[$value]['remark'] = '暂无机会！';
                $record = '暂无成交';
                if ($b_rate > $this->rate) {
                    $b_data[$value]['status'] = 11;
                    $b_amount = min($b_data[$value]['b_bid'][1], $b_data[$value]['g_ask'][1]);
                    $b_data[$value]['remark'] = 'BCEX 买一(' . $b_data[$value]['b_bid'][0] . ')比 Gate卖一(' . $b_data[$value]['g_ask'][0] . ') 大' . $b_rate . '数量为：' . $b_amount;

                    // BCEX 卖
                    $bcex_res = $this->bcex_request('/api_market/placeOrder', [
                        'market_type' => '1',
                        'market' => $market,
                        'token' => $token,
                        'type' => '2',    //买/卖(1为买，2为卖)
                        'price' => ''.$b_data[$value]['b_bid'][0],
                        'amount' => ''.min($b_amount,$this->minToken[$value])
                    ], 'POST');
                    // Gate 买
                    $gateRes = $this->gateLib->buy($value, $b_data[$value]['g_ask'][0], $b_amount);
                    $record = json_encode(['bcex_res' => $bcex_res, 'gate_res' => $gateRes],JSON_UNESCAPED_UNICODE);
                    trace('下单成功：' . $record, 'error');

                }
                if ($g_rate > $this->rate) {
                    $b_data[$value]['status'] = 12;
                    $b_amount = min($b_data[$value]['g_bid'][1], $b_data[$value]['b_ask'][1]);
                    $b_data[$value]['remark'] = 'Gate 买一(' . $b_data[$value]['g_bid'][0] . ')比 BCEX卖一(' . $b_data[$value]['b_ask'][0] . ') 大' . $g_rate . '数量为：' . $b_amount;
                    // 去 BCEX 买，Gate 卖

                    // BCEX 买
                    $bcex_res = $this->bcex_request('/api_market/placeOrder', [
                        'market_type' => '1',
                        'market' => $market,
                        'token' => $token,
                        'type' => '1',    //买/卖(1为买，2为卖)
                        'price' => ''.$b_data[$value]['b_ask'][0],
                        'amount' => ''.min($b_amount,$this->minToken[$value])
                    ], 'POST');
                    // Gate 卖
                    $gateRes = $this->gateLib->sell($value, $b_data[$value]['g_bid'][0], $b_amount);
                    $record = json_encode(['bcex_res' => $bcex_res, 'gate_res' => $gateRes],JSON_UNESCAPED_UNICODE);
                    trace('下单成功：' . $record, 'error');
                }
                $model = new Bg();
                $model->save([
                    'token' => $value,
                    'g_ask' => $b_data[$value]['g_ask'][0] . '-' . $b_data[$value]['g_ask'][1],
                    'g_bid' => $b_data[$value]['g_bid'][0] . '-' . $b_data[$value]['g_bid'][1],
                    'b_ask' => $b_data[$value]['b_ask'][0] . '-' . $b_data[$value]['b_ask'][1],
                    'b_bid' => $b_data[$value]['b_bid'][0] . '-' . $b_data[$value]['b_bid'][1],
                    'status' => $b_data[$value]['status'],
                    'remark' => $b_data[$value]['remark'],
                    'record' => $record,
                ]);
            }
        }
    }

    private function bcex_request($url, $params = [], $method = 'GET')
    {
        $url = 'https://api.bcex.vip' . $url;

        $params['api_key'] = \config('bcex.key');
        $key = \config('bcex.secret');

        //按照键名对关联数组进行升序排序，排序规则依据ASCII码
        ksort($params);
        //参数转为json格式字符串
        $str = json_encode($params);

        //获取私钥字符串

        //解析私钥供其他函数使用
        $private_key = openssl_pkey_get_private($key);

        //生成签名
        openssl_sign($str, $sign, $private_key, OPENSSL_ALGO_SHA1);

        //从内存中释放密钥资源
        openssl_free_key($private_key);

        //签名进行base64编码
        $sign = base64_encode($sign);

        //签名进行urlencode编码
        $sign = urlencode($sign);

        //将签名加入参数数组
        $params['sign'] = $sign;

        if ($method === 'GET') {
            $results = Http::get($url, $params);
        } else {
            $results = Http::post($url, $params);
        }
        return json_decode($results, true);
    }


//    public function test()
//    {
//        $results = $this->bcex_request('/api_market/placeOrder', [
//            'market_type' => "1",
//            'market' => 'USDT',
//            'token' => 'XLM',
//            'type' => "1",    //买/卖(1为买，2为卖)
//            'price' => "0.084",
//            'amount' => "1.1"
//        ], 'POST');
//
////        $results = $this->gateLib->sell('XLM_USDT', 0.08, 15);
//
////        $results = $this->bcex_request('/api_market/market/depth', ['market' => 'USDT', 'token' => 'XLM']);
//        $this->success('请求成功', $results);
//    }
//
//    public function balance()
//    {
//        $results = $this->bcex_request('/api_market/getBalance', [
//            'page' => "1",
//            'size' => "10",
//            'tokens' => ['ETH', 'EOS'],
//        ], 'POST');
//        $this->success('请求成功', $results);
//    }

}
