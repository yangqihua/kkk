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
    private $yangGateLib;
    private $yangConfigData;

    private $xuGateLib;
    private $xuConfigData;
    // 杠杆
    private $balanceRate = 4;


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
        if (abs(($price - $this->yangConfigData['eth_last_price']) / $price) >= 0.01) {
            $totalMoney = $this->yangConfigData['ETH'] * $price + $this->yangConfigData['USDT'] * 1;
            $halfMoney = $totalMoney / 2;
            $needBuy = ($halfMoney - $this->yangConfigData['ETH'] * $price) / $price;
            $needSell = ($halfMoney - $this->yangConfigData['USDT']) / $price;
            $g = json_decode(Http::get('https://data.gateio.life/api2/1/orderBook/ETH_USDT'), true);
            $bidPrice = $g['bids'][0]; // 买一
            $askPrice = $g['asks'][count($g['asks']) - 1]; // 卖一
            if ($needBuy > 0.04) {
                $gateRes = $this->yangGateLib->buy('ETH_USDT', $askPrice[0], min(1.2, $needBuy));
                // 记录last price
                $this->yangConfigData['eth_last_price'] = $askPrice[0];
                $this->yangConfigData['ETH'] += min(1.2, $needBuy);
                $this->yangConfigData['USDT'] -= min(1.2, $needBuy) * $askPrice[0];
                $this->updateYangExConfig($this->yangConfigData);
                trace('jun_eth_usdt买单结果：' . json_encode($gateRes), 'error');
            } elseif ($needSell > 0.04) {
                $gateRes = $this->yangGateLib->sell('ETH_USDT', $bidPrice[0], min(1.2, $needSell));
                // 记录last price
                $this->yangConfigData['eth_last_price'] = $bidPrice[0];
                $this->yangConfigData['ETH'] -= min(1.2, $needSell);
                $this->yangConfigData['USDT'] += min(1.2, $needSell) * $bidPrice[0];
                $this->updateYangExConfig($this->yangConfigData);
                trace('jun_eth_usdt卖单结果：' . json_encode($gateRes), 'error');
            }
        }
    }

    public function jun_bch_btc()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/bch_btc'), true);
        $price = $priceData['last'] * 1;
        if (abs(($price - $this->yangConfigData['bch_last_price']) / $price) >= 0.01) {
            $totalMoney = $this->yangConfigData['BCH'] * $price + $this->yangConfigData['BTC'] * 1;
            $halfMoney = $totalMoney / 2;
            $needBuy = ($halfMoney - $this->yangConfigData['BCH'] * $price) / $price;
            $needSell = ($halfMoney - $this->yangConfigData['BTC']) / $price;
            $g = json_decode(Http::get('https://data.gateio.life/api2/1/orderBook/BCH_BTC'), true);
            $bidPrice = $g['bids'][0]; // 买一
            $askPrice = $g['asks'][count($g['asks']) - 1]; // 卖一
            if ($needBuy > 0.035) {
                $gateRes = $this->yangGateLib->buy('BCH_BTC', $askPrice[0], min(1, $needBuy));
                // 记录last price
                $this->yangConfigData['bch_last_price'] = $askPrice[0];
                $this->yangConfigData['BCH'] += min(1, $needBuy);
                $this->yangConfigData['BTC'] -= min(1, $needBuy) * $askPrice[0];
                $this->updateYangExConfig($this->yangConfigData);
                trace('jun_bch_btc买单结果：' . json_encode($gateRes), 'error');
            } elseif ($needSell > 0.035) {
                $gateRes = $this->yangGateLib->sell('BCH_BTC', $bidPrice[0], min(1, $needSell));
                // 记录last price
                $this->yangConfigData['bch_last_price'] = $bidPrice[0];
                $this->yangConfigData['BCH'] -= min(1, $needSell);
                $this->yangConfigData['BTC'] += min(1, $needSell) * $bidPrice[0];
                $this->updateYangExConfig($this->yangConfigData);
                trace('jun_bch_btc卖单结果：' . json_encode($gateRes), 'error');
            }
        }
    }

    private function getYangExConfig()
    {
        $config = new Config();
        $result = $config->where("name", "ex_config")->find();
        if (!$result) {
            $balanceData = $this->yangGateLib->get_balances();
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

    private function updateYangExConfig($exConfig)
    {
        $config = new Config();
        $result = $config->where("name", "ex_config")->find();
        $result['value'] = json_encode($exConfig);
        Db::name('config')->update(['value' => $result['value'], 'id' => $result['id']]);
    }

    // xu start
    public function jun_xu_cang()
    {
        $this->jun_xu_eos_usdt();
        $this->jun_xu_bch_btc();
        sleep(25);
        $this->jun_xu_eos_usdt();
        $this->jun_xu_bch_btc();

        $this->success('请求成功');
    }

    public function jun_xu_eos_usdt()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/eos_usdt'), true);
        $price = $priceData['last'] * 1;
        if (abs(($price - $this->xuConfigData['eos_last_price']) / $price) >= 0.01) {
            $totalMoney = $this->xuConfigData['EOS'] * $price + $this->xuConfigData['USDT'] * 1;
            $halfMoney = $totalMoney / 2;
            $needBuy = ($halfMoney - $this->xuConfigData['EOS'] * $price) / $price;
            $needSell = ($halfMoney - $this->xuConfigData['USDT']) / $price;
            $g = json_decode(Http::get('https://data.gateio.life/api2/1/orderBook/EOS_USDT'), true);
            $bidPrice = $g['bids'][0]; // 买一
            $askPrice = $g['asks'][count($g['asks']) - 1]; // 卖一
            if ($needBuy > 0.5) {
                $gateRes = $this->xuGateLib->buy('EOS_USDT', $askPrice[0], min(20, $needBuy));
                // 记录last price
                $this->xuConfigData['eos_last_price'] = $askPrice[0];
                $this->xuConfigData['EOS'] += min(1.2, $needBuy);
                $this->xuConfigData['USDT'] -= min(1.2, $needBuy) * $askPrice[0];
                $this->updateXuExConfig($this->xuConfigData);
                trace('jun_xu_eos_usdt 买单结果：' . json_encode($gateRes), 'error');
            } elseif ($needSell > 0.5) {
                $gateRes = $this->xuGateLib->sell('EOS_USDT', $bidPrice[0], min(20, $needSell));
                // 记录last price
                $this->xuConfigData['eos_last_price'] = $bidPrice[0];
                $this->xuConfigData['EOS'] -= min(1.2, $needSell);
                $this->xuConfigData['USDT'] += min(1.2, $needSell) * $bidPrice[0];
                $this->updateXuExConfig($this->xuConfigData);
                trace('jun_xu_eos_usdt 卖单结果：' . json_encode($gateRes), 'error');
            }
        }
    }

    public function jun_xu_bch_btc()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/bch_btc'), true);
        $price = $priceData['last'] * 1;
        if (abs(($price - $this->xuConfigData['bch_last_price']) / $price) >= 0.01) {
            $totalMoney = $this->xuConfigData['BCH'] * $price + $this->xuConfigData['BTC'] * 1;
            $halfMoney = $totalMoney / 2;
            $needBuy = ($halfMoney - $this->xuConfigData['BCH'] * $price) / $price;
            $needSell = ($halfMoney - $this->xuConfigData['BTC']) / $price;
            $g = json_decode(Http::get('https://data.gateio.life/api2/1/orderBook/BCH_BTC'), true);
            $bidPrice = $g['bids'][0]; // 买一
            $askPrice = $g['asks'][count($g['asks']) - 1]; // 卖一
            if ($needBuy > 0.035) {
                $gateRes = $this->xuGateLib->buy('BCH_BTC', $askPrice[0], min(1, $needBuy));
                // 记录last price
                $this->xuConfigData['bch_last_price'] = $askPrice[0];
                $this->xuConfigData['BCH'] += min(1, $needBuy);
                $this->xuConfigData['BTC'] -= min(1, $needBuy) * $askPrice[0];
                $this->updateXuExConfig($this->xuConfigData);
                trace('jun_xu_bch_btc 买单结果：' . json_encode($gateRes), 'error');
            } elseif ($needSell > 0.035) {
                $gateRes = $this->xuGateLib->sell('BCH_BTC', $bidPrice[0], min(1, $needSell));
                // 记录last price
                $this->xuConfigData['bch_last_price'] = $bidPrice[0];
                $this->xuConfigData['BCH'] -= min(1, $needSell);
                $this->xuConfigData['BTC'] += min(1, $needSell) * $bidPrice[0];
                $this->updateXuExConfig($this->xuConfigData);
                trace('jun_xu_bch_btc 卖单结果：' . json_encode($gateRes), 'error');
            }
        }
    }

    private function getXuExConfig()
    {
        $config = new Config();
        $result = $config->where("name", "ex_xu_config")->find();
        if (!$result) {
            $balanceData = $this->xuGateLib->get_balances();
            $balance = $balanceData['available'];
            $data = [
                'eos_last_price' => 1,
                'bch_last_price' => 1,
                'EOS' => $balance['EOS'] * $this->balanceRate,
                'USDT' => $balance['USDT'] * $this->balanceRate,
                'BTC' => $balance['BTC'] * $this->balanceRate,
                'BCH' => $balance['BCH'] * $this->balanceRate,
            ];
            Db::name('config')->insert([
                'name' => 'ex_xu_config',
                'group' => 'dictionary',
                'type' => 'string',
                'content' => json_encode($data),
                'value' => json_encode($data)
            ]);
            return $data;
        }
        return json_decode($result['value'], true);
    }

    private function updateXuExConfig($exConfig)
    {
        $config = new Config();
        $result = $config->where("name", "ex_xu_config")->find();
        $result['value'] = json_encode($exConfig);
        Db::name('config')->update(['value' => $result['value'], 'id' => $result['id']]);
    }

    public function __construct()
    {
        parent::__construct();
        $this->yangGateLib = new GateLib();
        $this->yangConfigData = $this->getYangExConfig();

        $key = \config('xu_gate.key');
        $secret = \config('xu_gate.secret');
        $this->xuGateLib = new GateLib($key, $secret);
        $this->xuConfigData = $this->getXuExConfig();
    }

    public function test_xu_balance()
    {
        $key = \config('xu_gate.key');
        $secret = \config('xu_gate.secret');
        $this->xuGateLib = new GateLib($key, $secret);
        $balance = $this->xuGateLib->get_balances();
        $this->success('请求成功', $balance['available']);
    }


    public function test_config()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/eth_usdt'), true);
        $ethPrice = $priceData['last'] * 1;

        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/bch_btc'), true);
        $bchPrice = $priceData['last'] * 1;

        $config = $this->getYangExConfig();
        $config['eth_now_price'] = $ethPrice . '(' . (abs($config['eth_last_price'] - $ethPrice) / $ethPrice) * 100.0 . '%)';
        $config['bch_now_price'] = $bchPrice . '(' . (abs($config['bch_last_price'] - $bchPrice) / $bchPrice) * 100.0 . '%)';
        $config['ETH_RATE'] = (($config['USDT'] - $config['ETH'] * $ethPrice) / $ethPrice) . ' ETH';
        $config['ETH'] = $config['ETH'] . '(' . $config['ETH'] * $ethPrice . ' USDT)';
        $config['BCH_RATE'] = (($config['BTC'] - $config['BCH'] * $bchPrice) / $bchPrice) . ' BCH';
        $config['BCH'] = $config['BCH'] . '(' . $config['BCH'] * $bchPrice . ' BTC)';

        $balanceData = $this->yangGateLib->get_balances();
        $balance = $balanceData['available'];

        $b = ['ETH' => $balance['ETH'], 'USDT' => $balance['USDT'], 'BCH' => $balance['BCH'], 'BTC' => $balance['BTC']];
        $b['ETH_RATE'] = (($b['USDT'] - $b['ETH'] * $ethPrice) / $ethPrice) . ' ETH';
        $b['ETH'] = $b['ETH'] . '(' . $b['ETH'] * $ethPrice . ' USDT)';
        $b['BCH_RATE'] = (($b['BTC'] - $b['BCH'] * $bchPrice) / $bchPrice) . ' BCH';
        $b['BCH'] = $b['BCH'] . '(' . $b['BCH'] * $bchPrice . ' BTC)';
        $this->success('请求成功', ['account1' => $config, 'account2' => $b]);
    }

    public function test_xu_config()
    {
        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/eos_usdt'), true);
        $ethPrice = $priceData['last'] * 1;

        $priceData = json_decode(Http::get('https://data.gateio.life/api2/1/ticker/bch_btc'), true);
        $bchPrice = $priceData['last'] * 1;

        $config = $this->getXuExConfig();
        $config['eos_now_price'] = $ethPrice . '(' . (abs($config['eos_last_price'] - $ethPrice) / $ethPrice) * 100.0 . '%)';
        $config['bch_now_price'] = $bchPrice . '(' . (abs($config['bch_last_price'] - $bchPrice) / $bchPrice) * 100.0 . '%)';
        $config['EOS_RATE'] = (($config['USDT'] - $config['EOS'] * $ethPrice) / $ethPrice) . ' EOS';
        $config['EOS'] = $config['EOS'] . '(' . $config['EOS'] * $ethPrice . ' USDT)';
        $config['BCH_RATE'] = (($config['BTC'] - $config['BCH'] * $bchPrice) / $bchPrice) . ' BCH';
        $config['BCH'] = $config['BCH'] . '(' . $config['BCH'] * $bchPrice . ' BTC)';

        $balanceData = $this->xuGateLib->get_balances();
        $balance = $balanceData['available'];

        $b = ['EOS' => $balance['EOS'], 'USDT' => $balance['USDT'], 'BCH' => $balance['BCH'], 'BTC' => $balance['BTC']];
        $b['EOS_RATE'] = (($b['USDT'] - $b['EOS'] * $ethPrice) / $ethPrice) . ' EOS';
        $b['EOS'] = $b['EOS'] . '(' . $b['EOS'] * $ethPrice . ' USDT)';
        $b['BCH_RATE'] = (($b['BTC'] - $b['BCH'] * $bchPrice) / $bchPrice) . ' BCH';
        $b['BCH'] = $b['BCH'] . '(' . $b['BCH'] * $bchPrice . ' BTC)';
        $this->success('请求成功', ['account1' => $config, 'account2' => $b]);
    }


}
