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
        $markets = ['doge_usdt'];
        foreach ($markets as $k => $market) {
            $sql = 'SELECT * from jun_history WHERE market=:market order by id asc limit 1;';
            $histories = Db::query($sql, ['market' => $market]);
            $initMoney = $histories[0]['money_before'];
            $initCoin = $histories[0]['coin_before'];

            $firstDay = strtotime(date('Y-m-d', strtotime("-3 month")));
            $sql = 'SELECT from_unixtime(createtime,\'%Y-%m-%d %H:%i\') createTime,price,cap_after from jun_history 
WHERE createtime>:firstDay and market=:market;';
            $histories = Db::query($sql, ['firstDay' => $firstDay, 'market' => $market]);
            $initCap = [];
            $quantCap = [];
            $date = [];
            $series = [];
            $liData = [];
            foreach ($histories as $key => $history) {
                $initCap[] = $initMoney + $initCoin * $history['price'];
                $quantCap[] = $history['cap_after'];
                $date = $history['createTime'];
            }
            $liData['原市值'] = $initCap;
            $liData['网格市值'] = $quantCap;
            $legend = ['原市值', '网格市值'];
            foreach ($legend as $tKey => $tValue) {
                $series[] = [
                    'name' => $tValue,
                    'type' => 'line',
                    'data' => $liData[$tValue]
                ];
            }
            $chartList[$market] = ['legend' => $legend, 'date' => $date, 'series' => $series];
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
