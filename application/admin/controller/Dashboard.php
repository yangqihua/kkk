<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use fast\Http;
use think\Config;
use think\Db;

/**
 * 控制台
 *
 * @icon fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function index()
    {
        $chartList = [];
        $markets = ['doge_usdt', 'bch_usdt', 'crv_usdt', 'btc_usdt', '1inch_usdt', 'api3_usdt', 'badger_usdt'];
        foreach ($markets as $k => $market) {
            $sql = 'SELECT * from jun_history WHERE market=:market order by id asc limit 1;';
            $histories = Db::query($sql, ['market' => $market]);
            $initMoney = $histories[0]['money_before'];
            $initCoin = $histories[0]['coin_before'];
            $initCa = $initMoney + $initCoin * $histories[0]['price'];

            $firstDay = strtotime(date('Y-m-d', strtotime("-3 month")));
            $sql = 'SELECT from_unixtime(createtime,\'%Y-%m-%d %H:%i\') createTime,createtime,price,cap_after from jun_history 
WHERE createtime>:firstDay and market=:market;';
            $histories = Db::query($sql, ['firstDay' => $firstDay, 'market' => $market]);
            $initCap = [];
            $quantCap = [];
            $date = [];
            $series = [];
            $liData = [];
            $prices = [];
            foreach ($histories as $key => $history) {
                $initCap[] = round($initMoney + $initCoin * $history['price'], 2);
                $quantCap[] = round($history['cap_after'], 2);
                $date[] = $history['createTime'];
                $prices[] = $history['price'];
            }
            $liData['持币不动市值'] = $initCap;
            $liData['网格市值'] = $quantCap;
            $liData['价格'] = $prices;
            $legend = ['持币不动市值', '网格市值', '价格'];
            foreach ($legend as $tKey => $tValue) {
                $series[] = [
                    'name' => $tValue,
                    'type' => 'line',
                    'data' => $liData[$tValue]
                ];
            }
            $timeGap = $histories[count($histories) - 1]['createtime'] - $histories[0]['createtime'];
            $year = 3600 * 24 * 365;
            $percent = round(($quantCap[count($quantCap) - 1] - $initCap[count($initCap) - 1]) * $year / $timeGap / $initCa * 100, 2);
            $get = round($quantCap[count($quantCap) - 1] - $initCa, 2);
            $chartList[$market] = ['get' => $get, 'percent' => $percent, 'legend' => $legend, 'date' => $date, 'series' => $series];
        }
        $this->view->assign([
            'chartList' => $chartList,
        ]);
        return $this->view->fetch();

//        $legend = ['MONEY', 'USDT', 'XTZ3L', 'BSV3L'];
//        $series = [];
//        $date = [];
//        $firstDay = strtotime(date('Y-m-d', strtotime("-6 month")));
//        $sql = 'SELECT from_unixtime(createtime,\'%Y-%m-%d %H:%i\') createTime,content from jun_balance
//WHERE id>8973 and createtime>:firstDay and username="yang";';
//        $yangBalances = Db::query($sql, ['firstDay' => $firstDay]);
//        $moneyData = [];
//        foreach ($yangBalances as $key => $balance) {
//            $content = json_decode($balance['content'], true);
//            foreach ($legend as $k => $v) {
//                $moneyData[$v][] = $content[$v];
//            }
//            $date[] = $balance['createTime'];
//        }
//        foreach ($legend as $k => $v) {
//            $series[] = [
//                'name' => $v,
//                'type' => 'line',
//                'data' => $moneyData[$v]
//            ];
//        }
//        $lineData = ['legend' => $legend, 'date' => $date, 'series' => $series];

//        $legend = ['MONEY','USDT','EOS','BCH','BTC'];
//        $series = [];
//        $date = [];
//        $firstDay = strtotime(date('Y-m-d', strtotime("-6 month")));
//        $sql = 'SELECT from_unixtime(createtime,\'%Y-%m-%d %H:%i\') createTime,content from jun_balance
//WHERE createtime>:firstDay and username="xu";';
//        $yangBalances = Db::query($sql, ['firstDay' => $firstDay]);
//        $moneyData = [];
//        foreach ($yangBalances as $key=>$balance){
//            $content = json_decode($balance['content'],true);
//            foreach ($legend as $k=>$v){
//                $moneyData[$v][]=$content[$v];
//            }
//            $date[] = $balance['createTime'];
//        }
//        foreach ($legend as $k=>$v){
//            $series[] = [
//                'name' => $v,
//                'type' => 'line',
//                'data' => $moneyData[$v]
//            ];
//        }
//        $xuLineData = ['legend' => $legend, 'date' => [], 'series' => $series];
//
//
//        $this->view->assign([
//            'lineData' => $lineData,
//            'xuLineData' => $xuLineData,
//        ]);
//        return $this->view->fetch();
    }


    /**
     * 查看
     */
    public function test()
    {
        $size = input('size', 1000);
        $period = input('period', '15min');;

        $result = [];
        $kinds = [
            '火币现货' => "https://api.huobi.pro/market/history/kline?period=" . $period . "&size=" . $size . "&symbol=btcusdt",
            '火币期货' => "https://api.hbdm.com/market/history/kline?period=" . $period . "&size=" . $size . "&symbol=BTC_CQ",
//            'OK现货'=>"",
//            'OK期货'=>"",
        ];

        foreach ($kinds as $key => $item) {
            $res = json_decode(Http::get($item), true);
            $result[$key] = $res ? $res['data'] : [];
        }

        $legend = array_keys($kinds);
        $date = array_keys($result['火币期货']);
        $series = [];
        foreach ($result as $key => $itemData) {
            $series[] = [
                'name' => $key,
                'type' => 'line',
                'data' => array_column($itemData, 'close')
            ];
        }
        $lineData = ['legend' => $legend, 'date' => $date, 'series' => $series];

        $this->view->assign([
            'lineData' => $lineData
        ]);
        return $this->view->fetch();
    }

}
