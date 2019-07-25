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

    private $minToken = ['ETH_USDT' => 0.88, 'EOS_USDT' => 30, 'XLM_USDT' => 2000];

    public function __construct()
    {
        parent::__construct();
        $this->gateLib = new GateLib();
    }

    public function bit()
    {
        $g_result = $this->gateLib->get_orderbook('EOS_ETH');
        $result['g_bid'] = $g_result['bids'][0];
        $result['g_ask'] = $g_result['asks'][count($g_result['asks'])-1];

        $b_result = json_decode(Http::get('https://api.bittrex.com/api/v1.1/public/getorderbook?market=eth-eos&type=both'),true);
        $result['b_bid'][0] = $b_result['result']['buy'][0]['Rate'];
        $result['b_bid'][1] = $b_result['result']['buy'][0]['Quantity'];
        $result['b_ask'][0] = $b_result['result']['sell'][0]['Rate'];
        $result['b_ask'][1] = $b_result['result']['sell'][0]['Quantity'];

        $b_rate = ($result['b_bid'][0] - $result['g_ask'][0]) / $result['b_bid'][0];
        $g_rate = ($result['g_bid'][0] - $result['b_ask'][0]) / $result['g_bid'][0];

        $result['status'] = 20;
        $result['remark'] = 'bit暂无机会！';
        $record = 'bit暂无成交';
        if ($b_rate > $this->rate) {
            $result['status'] = 21;
            $result['remark'] = 'Bit 买一(' . $result['b_bid'][0] . ')比 Gate卖一(' . $result['g_ask'][0] . ') 大' . $b_rate . '数量为：' . min($result['b_bid'][1], $result['g_ask'][1]);
            // 去 BCEX 卖，Gate 买
        }
        if ($g_rate > $this->rate) {
            $result['status'] = 22;
            $result['remark'] = 'Gate 买一(' . $result['g_bid'][0] . ')比 Bit卖一(' . $result['b_ask'][0] . ') 大' . $g_rate . '数量为：' . min($result['g_bid'][1], $result['b_ask'][1]);
            // 去 BCEX 买，Gate 卖
        }
        $model = new Bg();
        $model->save([
            'token' => 'EOS_ETH',
            'g_ask' => $result['g_ask'][0] . '-' . $result['g_ask'][1],
            'g_bid' => $result['g_bid'][0] . '-' . $result['g_bid'][1],
            'b_ask' => $result['b_ask'][0] . '-' . $result['b_ask'][1],
            'b_bid' => $result['b_bid'][0] . '-' . $result['b_bid'][1],
            'status' => $result['status'],
            'remark' => $result['remark'],
            'record' => $record,
        ]);

        $this->success('请求成功', $result);
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
        for ($i = 0; $i < 15; $i++) {
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
                    $b_balance = $this->balance();
                    $b_data[$value]['status'] = 11;
                    $b_amount = min($b_data[$value]['b_bid'][1], $b_data[$value]['g_ask'][1]);
                    $b_data[$value]['remark'] = 'BCEX 买一(' . $b_data[$value]['b_bid'][0] . ')比 Gate卖一(' . $b_data[$value]['g_ask'][0] . ') 大' . $b_rate . '数量为：' . $b_amount;

                    $record = '余额不足';
                    $amount = min($b_amount, $this->minToken[$value]);
                    // 花 BCEX 的 eth 卖 余额够
                    if ($b_balance['eth'] > $amount) {
                        // Gate 买
                        $gateRes = $this->gateLib->buy($value, $b_data[$value]['g_ask'][0], $amount);
                        if ($gateRes['message'] == 'Success') {
                            // BCEX 卖
                            $bcex_res = $this->bcex_request('/api_market/placeOrder', [
                                'market_type' => '1',
                                'market' => $market,
                                'token' => $token,
                                'type' => '2',    //买/卖(1为买，2为卖)
                                'price' => '' . $b_data[$value]['b_bid'][0],
                                'amount' => '' . $amount
                            ], 'POST');
                            $record = json_encode(['bcex_res' => $bcex_res, 'gate_res' => $gateRes], JSON_UNESCAPED_UNICODE);
                        }
                    }
                    trace('可以下单：' . $record, 'error');
                }
                if ($g_rate > $this->rate) {
                    $b_balance = $this->balance();
                    $b_data[$value]['status'] = 12;
                    $b_amount = min($b_data[$value]['g_bid'][1], $b_data[$value]['b_ask'][1]);
                    $b_data[$value]['remark'] = 'Gate 买一(' . $b_data[$value]['g_bid'][0] . ')比 BCEX卖一(' . $b_data[$value]['b_ask'][0] . ') 大' . $g_rate . '数量为：' . $b_amount;


                    $record = '余额不足';
                    $amount = min($b_amount, $this->minToken[$value]);
                    // 去 BCEX 买，Gate 卖

                    // 花 BCEX 的 usdt 卖 余额够
                    if ($b_balance['usdt'] > ($amount*$b_data[$value]['b_ask'][0])) {
                        $gateRes = $this->gateLib->sell($value, $b_data[$value]['g_bid'][0], $amount);
                        if ($gateRes['message'] == 'Success') {
                            // BCEX 卖 usdt
                            $bcex_res = $this->bcex_request('/api_market/placeOrder', [
                                'market_type' => '1',
                                'market' => $market,
                                'token' => $token,
                                'type' => '1',    //买/卖(1为买，2为卖)
                                'price' => '' . $b_data[$value]['b_ask'][0],
                                'amount' => '' . $amount
                            ], 'POST');
                            $record = json_encode(['bcex_res' => $bcex_res, 'gate_res' => $gateRes], JSON_UNESCAPED_UNICODE);
                        }
                    }
                    trace('可以下单：' . $record, 'error');
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


    public function test()
    {

        $results = $this->gateLib->sell('BTC_USDT', 10000, 100);

//        $results = $this->bcex_request('/api_market/market/depth', ['market' => 'USDT', 'token' => 'XLM']);
        $this->success('请求成功', $results);
    }

    private function balance()
    {
        $results = $this->bcex_request('/api_market/getBalance', [
            'page' => "1",
            'size' => "10",
            'tokens' => ['ETH', 'USDT'],
        ], 'POST');
        $balance['eth'] = $results['data']['data'][0]['usable'];
        $balance['usdt'] = $results['data']['data'][1]['usable'];
        return $balance;
    }

}
