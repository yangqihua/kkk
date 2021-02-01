<?php

namespace app\api\controller;

use app\admin\model\ban\Bg;
use app\admin\model\JunBalance;
use app\admin\model\JunHistory;
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
    private $yangGateLib;

    public $pairs = [
        "yang" => [
//            'bsv3l_usdt' => ['coin' => 'bsv3l', 'money' => 'usdt', 'rate' => 3, 'condition' => 0.01, 'min' => 200, 'max' => 3000],
            'doge_usdt' => ['coin' => 'doge', 'money' => 'usdt', 'rate' => 2, 'condition' => 0.09, 'min' => 2, 'max' => 30000],
            'bch_usdt' => ['coin' => 'bch', 'money' => 'usdt', 'rate' => 2, 'condition' => 0.008, 'min' => 0.01, 'max' => 3],
            'crv_usdt' => ['coin' => 'crv', 'money' => 'usdt', 'rate' => 1.5, 'condition' => 0.015, 'min' => 0.1, 'max' => 300],
        ],
//        "xu" => [
////            'bsv3l_usdt' => ['coin' => 'bsv3l', 'money' => 'usdt', 'rate' => 3, 'condition' => 0.01, 'min' => 200, 'max' => 3000],
//            'btc3l_usdt' => ['coin' => 'btc3l', 'money' => 'usdt', 'rate' => 2, 'condition' => 0.01, 'min' => 100, 'max' => 3000],
//        ]
    ];

    public function __construct()
    {
        parent::__construct();
    }

    private function getExchange($user)
    {
        $password = \config('exchange_key.' . $user);
        $key = $password['key'];
        $secret = $password['secret'];
        return new GateLib($key, $secret);
    }

    private function getConfig($user)
    {
        $pairs = $this->pairs[$user];
        $config = new Config();
        $result = $config->where("name", $user)->find();
        if (!$result) {
            $exchange = $this->getExchange($user);
            $balanceData = $exchange->get_balances();
            $balance = $balanceData['available'];
            $data = [];
            foreach ($pairs as $coin => $money) {
                $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/' . $money['coin'] . '_' . $money['money']), true);
                $price = $priceData['last'] * 1;
                $data[$coin] = [
                    'last_price' => $price,
                    'init_price' => $price,
                    $money['coin'] => $balance[strtoupper($money['coin'])] * $money['rate'],
                    $money['money'] => $balance[strtoupper($money['coin'])] * $price * $money['rate'],
                ];
            }
            Db::name('config')->insert([
                'name' => $user,
                'group' => 'dictionary',
                'type' => 'string',
                'content' => '',
                'value' => json_encode($data)
            ]);
            return $data;
        } else {
            $data = json_decode($result['value'], true);
            $flag = false;
            foreach ($pairs as $coin => $money) {
                // 之前不存在这个交易对，则加入到配置中去，不更改以前的信息
                if (!array_key_exists($coin, $data)) {
                    $flag = true;
                    $exchange = $this->getExchange($user);
                    $balanceData = $exchange->get_balances();
                    $balance = $balanceData['available'];
                    $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/' . $money['coin'] . '_' . $money['money']), true);
                    $price = $priceData['last'] * 1;
                    $data[$coin] = [
                        'last_price' => $price,
                        $money['coin'] => $balance[strtoupper($money['coin'])] * $money['rate'],
                        $money['money'] => $balance[strtoupper($money['coin'])] * $price * $money['rate'],
                    ];
                }
            }
            if ($flag) {
                $result['value'] = json_encode($data);
                Db::name('config')->update(['value' => $result['value'], 'id' => $result['id']]);
            }
            return $data;
        }
    }

    private function updateConfig($user, $exConfig)
    {
        $config = new Config();
        $result = $config->where("name", $user)->find();
        $result['value'] = json_encode($exConfig);
        Db::name('config')->update(['value' => $result['value'], 'id' => $result['id']]);
    }

    public function jun($user)
    {
        $pairs = $this->pairs[$user];
        $config = $this->getConfig($user);
        $exchange = $this->getExchange($user);

        $junHistory = new JunHistory();
        foreach ($pairs as $coin => $money) {
            $pairConfig = $config[$coin];
            $market = $money['coin'] . '_' . $money['money'];
            $g = json_decode(Http::get('https://data.gateio.life/api2/1/orderBook/' . $money['coin'] . '_' . $money['money']), true);
            $bidPrice = $g['bids'][0]; // 买一
            $askPrice = $g['asks'][count($g['asks']) - 1]; // 卖一

            $price = $askPrice[0];

            $buyGap = abs(($pairConfig['last_price'] - $price) / $price * 100);
            $sellGap = abs(($bidPrice[0] - $pairConfig['last_price']) / $bidPrice[0] * 100);
            $gap = round(max($buyGap, $sellGap), 2);
            trace($user . ':' . $money['coin'] . '_' . $money['money'] . 'data：bid1=>' . $bidPrice[0] . ', ask1=>' . $askPrice[0] . ', last=>' . $pairConfig['last_price'] . ' ,percent=>' . $gap . '%', 'error');
            // 没有算手续费
            // 买
            if (($pairConfig['last_price'] - $price) / $price >= $money['condition']) {
                // 跌了50%，没有买的必要了
                if (abs(($price - $pairConfig['init_price']) / $price > 0.5)) {
                    return;
                }
                $totalMoney = $pairConfig[$money['coin']] * $price + $pairConfig[$money['money']] * 1;
                $halfMoney = $totalMoney / 2;
                $needBuy = ($halfMoney - $pairConfig[$money['coin']] * $price) / $price;

                if ($needBuy > $money['min']) {
                    $gateRes = $exchange->buy(strtoupper($money['coin'] . '_' . $money['money']), $price, min($money['max'], $needBuy));
                    if (!$gateRes['result']) {
                        trace($market . ' buy gate api error', 'error');
                        return;
                    }
                    $coin_before = $pairConfig[$money['coin']];
                    $money_before = $pairConfig[$money['money']];

                    $cap_before = $totalMoney;
                    // 记录last price
                    $pairConfig['last_price'] = $price;
                    $pairConfig[$money['coin']] += min($money['max'], $needBuy);
                    $pairConfig[$money['money']] -= min($money['max'], $needBuy) * $price;
                    $config[$coin] = $pairConfig;
                    $this->updateConfig($user, $config);
                    trace($user . ' ' . $coin . '买单结果：' . json_encode($gateRes), 'error');
                    $cap_after = $pairConfig[$money['coin']] * $price + $pairConfig[$money['money']] * 1;
                    $junHistory->save([
                        'user' => $user,
                        'market' => $money['coin'] . '_' . $money['money'],
                        'price' => $price,
                        'direction' => 'buy',
                        'amount' => min($money['max'], $needBuy),
                        'coin_before' => $coin_before,
                        'coin_after' => $pairConfig[$money['coin']],
                        'money_before' => $money_before,
                        'money_after' => $pairConfig[$money['money']],
                        'cap_before' => $cap_before,
                        'cap_after' => $cap_after,
                    ]);
                } else {
                    trace($market . ' buy amount min: needBuy=>' . $needBuy . ', min=>' . $money['min'], 'error');
                }
            }
            $price = $bidPrice[0];
            // 卖
            if (($price - $pairConfig['last_price']) / $price >= $money['condition']) {
                $totalMoney = $pairConfig[$money['coin']] * $price + $pairConfig[$money['money']] * 1;
                $halfMoney = $totalMoney / 2;
                $needSell = ($halfMoney - $pairConfig[$money['money']]) / $price;

                if ($needSell > $money['min']) {
                    $gateRes = $exchange->sell(strtoupper($money['coin'] . '_' . $money['money']), $price, min($money['max'], $needSell));
                    if (!$gateRes['result']) {
                        trace($market . ' sell gate api error', 'error');
                        return;
                    }
                    $coin_before = $pairConfig[$money['coin']];
                    $money_before = $pairConfig[$money['money']];

                    $cap_before = $totalMoney;

                    // 记录last price
                    $pairConfig['last_price'] = $price;
                    $pairConfig[$money['coin']] -= min($money['max'], $needSell);
                    $pairConfig[$money['money']] += min($money['max'], $needSell) * $price;
                    $config[$coin] = $pairConfig;
                    $this->updateConfig($user, $config);
                    trace($user . ' ' . $coin . '卖单结果：' . json_encode($gateRes), 'error');
                    $cap_after = $pairConfig[$money['coin']] * $price + $pairConfig[$money['money']] * 1;
                    $junHistory->save([
                        'user' => $user,
                        'market' => $money['coin'] . '_' . $money['money'],
                        'price' => $price,
                        'direction' => 'sell',
                        'amount' => min($money['max'], $needSell),
                        'coin_before' => $coin_before,
                        'coin_after' => $pairConfig[$money['coin']],
                        'money_before' => $money_before,
                        'money_after' => $pairConfig[$money['money']],
                        'cap_before' => $cap_before,
                        'cap_after' => $cap_after,
                    ]);
                } else {
                    trace($market . ' sell amount min: needBuy=>' . $needSell . ', min=>' . $money['min'], 'error');
                }
            }
            sleep(0.8);
        }
    }


    public function jun_cang()
    {
        // 一分钟8次尝试
        for ($i = 0; $i < 10; $i++) {
            $this->jun('yang');
//            $this->jun('xu');
            sleep(4);
        }
        $this->success('请求成功');
    }


    public function tong_ji()
    {
        $this->tong_yang();
//        $this->tong_xu();
    }

    public function tong_yang()
    {
        $exchange = $this->getExchange('yang');
        $balanceData = $exchange->get_balances();
        $balance = $balanceData['available'];
        $locked = $balanceData['locked'];

        $pairs = $this->pairs['yang'];
        $content = ['MONEY' => 0];
        foreach ($pairs as $coin => $money) {
            $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/' . $money['coin'] . '_' . $money['money']), true);
            $price = $priceData['last'] * 1;
            $content[strtoupper($money['coin'])] = $locked[strtoupper($money['coin'])] + $balance[strtoupper($money['coin'])];
            $content['MONEY'] += $locked[strtoupper($money['coin'])] + $balance[strtoupper($money['coin'])] * $price;
        }
        $content['USDT'] = ($locked['USDT'] + $balance['USDT']);
        $content['MONEY'] += $content['USDT'];

        $model = new JunBalance();
        $model->save([
            'username' => 'yang',
            'content' => json_encode($content),
        ]);
    }


}
