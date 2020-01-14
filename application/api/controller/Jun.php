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
class Jun extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    private $rate = 0.008;
    private $gateLib;

    private $minToken = ['ETH_USDT' => 0.88, 'EOS_USDT' => 30, 'XLM_USDT' => 2000];

    // 杠杆
    private $balanceRate = 4;
    private $configData;


    public function test_config()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/eth_usdt'), true);
        $ethPrice = $priceData['last'] * 1;

        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/bch_btc'), true);
        $bchPrice = $priceData['last'] * 1;

        $config = $this->getExConfig();
        $config['ETH'] = $config['ETH'] . '(' . $config['ETH'] * $ethPrice . ' USDT)';
        $config['BCH'] = $config['BCH'] . '(' . $config['BCH'] * $bchPrice . ' BTC)';
        $this->success('请求成功', $config);
    }

    public function jun_cang()
    {
        $this->jun_eth_usdt();
        $this->jun_bch_btc();
        sleep(25);
        $this->jun_eth_usdt();
        $this->jun_bch_btc();

        $this->success('请求成功');
    }

    public function jun_eth_usdt()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/eth_usdt'), true);
        $price = $priceData['last'] * 1;


        if (abs(($price - $this->configData['eth_last_price']) / $price) >= 0.008) {
            $totalMoney = $this->configData['ETH'] * $price + $this->configData['USDT'] * 1;
            $halfMoney = $totalMoney / 2;
            $needBuy = ($halfMoney - $this->configData['ETH'] * $price) / $price;
            $needSell = ($halfMoney - $this->configData['USDT']) / $price;
            $g = json_decode(Http::get('https://data.gateio.life/api2/1/orderBook/ETH_USDT'), true);
            $bidPrice = $g['bids'][0]; // 买一
            $askPrice = $g['asks'][count($g['asks']) - 1]; // 卖一
            if ($needBuy > 0.04) {
                $gateRes = $this->gateLib->buy('ETH_USDT', $askPrice[0], min(1.2, $needBuy));
                // 记录last price
                $this->configData['eth_last_price'] = $askPrice[0];
                $this->updateExConfig($this->configData);
                trace('jun_eth_usdt买单结果：' . json_encode($gateRes), 'error');
            } elseif ($needSell > 0.04) {
                $gateRes = $this->gateLib->sell('ETH_USDT', $bidPrice[0], min(1.2, $needSell));
                // 记录last price
                $this->configData['eth_last_price'] = $bidPrice[0];
                $this->updateExConfig($this->configData);
                trace('jun_eth_usdt卖单结果：' . json_encode($gateRes), 'error');
            }
        }
    }

    public function jun_bch_btc()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/bch_btc'), true);
        $price = $priceData['last'] * 1;
        if (abs(($price - $this->configData['bch_last_price']) / $price) >= 0.008) {
            $totalMoney = $this->configData['BCH'] * $price + $this->configData['BTC'] * 1;
            $halfMoney = $totalMoney / 2;
            $needBuy = ($halfMoney - $this->configData['BCH'] * $price) / $price;
            $needSell = ($halfMoney - $this->configData['BTC']) / $price;
            $g = json_decode(Http::get('https://data.gateio.life/api2/1/orderBook/BCH_BTC'), true);
            $bidPrice = $g['bids'][0]; // 买一
            $askPrice = $g['asks'][count($g['asks']) - 1]; // 卖一
            if ($needBuy > 0.035) {
                $gateRes = $this->gateLib->buy('BCH_BTC', $askPrice[0], min(1, $needBuy));
                // 记录last price
                $this->configData['bch_last_price'] = $askPrice[0];
                $this->updateExConfig($this->configData);
                trace('jun_bch_btc买单结果：' . json_encode($gateRes), 'error');
            } elseif ($needSell > 0.035) {
                $gateRes = $this->gateLib->sell('BCH_BTC', $bidPrice[0], min(1, $needSell));
                // 记录last price
                $this->configData['bch_last_price'] = $bidPrice[0];
                $this->updateExConfig($this->configData);
                trace('jun_bch_btc卖单结果：' . json_encode($gateRes), 'error');
            }
        }
    }

    private function getExConfig()
    {
        $config = new Config();
        $result = $config->where("name", "ex_config")->find();
        if (!$result) {
            $balanceData = $this->gateLib->get_balances();
            $balance = $balanceData['available'];
            $data = [
                'eth_last_price' => 1,
                'bch_last_price' => 1,
                'ETH' => $balance['ETH'] * $this->balanceRate,
                'USDT' => $balance['USDT'] * $this->balanceRate,
                'BTC' => $balance['BTC'] * $this->balanceRate,
                'BCH' => $balance['BCH'] * $this->balanceRate,
            ];
            Db::name('config')->insert([
                'name' => 'ex_config',
                'group' => 'dictionary',
                'type' => 'string',
                'content' => json_encode($data),
                'value' => json_encode($data)
            ]);
            return $data;
        }
        return json_decode($result['value'], true);
    }

    private function updateExConfig($exConfig)
    {
        $config = new Config();
        $result = $config->where("name", "ex_config")->find();
        $result['value'] = json_encode($exConfig);
        Db::name('config')->update(['value' => $result['value'], 'id' => $result['id']]);
    }

    public function __construct()
    {
        parent::__construct();
        $this->gateLib = new GateLib();
        $this->configData = $this->getExConfig();
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

    public function ttt()
    {
        // 记录盈利
//        $config = new Config();
//        $moneyResult = $config->where("name", "money")->find();
//        $moneyResult['value']+=1;
//        Db::name('config')->update(['value' => $moneyResult['value'],'id'=>$moneyResult['id']]);
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
                            // 记录盈利
                            $config = new Config();
                            $moneyResult = $config->where("name", "money")->find();
                            $moneyResult['value'] += (($b_data[$value]['b_bid'][0] - $b_data[$value]['g_ask'][0]) * $amount);
                            Db::name('config')->update(['value' => $moneyResult['value'], 'id' => $moneyResult['id']]);
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
                    if ($b_balance['usdt'] > ($amount * $b_data[$value]['b_ask'][0])) {
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

                            // 记录盈利
                            $config = new Config();
                            $moneyResult = $config->where("name", "money")->find();
                            $moneyResult['value'] += (($b_data[$value]['g_bid'][0] - $b_data[$value]['b_ask'][0]) * $amount);
                            Db::name('config')->update(['value' => $moneyResult['value'], 'id' => $moneyResult['id']]);
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
