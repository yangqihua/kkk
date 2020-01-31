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

    /**
     * 查看
     */
    public function test()
    {
        $size = input('size',1000);
        $period = input('period','15min');;

        $result = [];
        $kinds = [
            '火币现货'=>"https://api.huobi.pro/market/history/kline?period=".$period."&size=".$size."&symbol=btcusdt",
            '火币期货' => "https://api.hbdm.com/market/history/kline?period=" . $period . "&size=" . $size . "&symbol=BTC_CQ",
//            'OK现货'=>"",
//            'OK期货'=>"",
        ];

        foreach ($kinds as $key => $item) {
            $res = json_decode(Http::get($item),true);
            $result[$key] = $res?$res['data']:[];
        }

        $legend = array_keys($kinds);
        $date = array_keys($result['火币期货']);
        $series = [];
        foreach ($result as $key => $itemData) {
            $series[] = [
                'name' => $key,
                'type' => 'line',
                'data' => array_column($itemData,'close')
            ];
        }
        $lineData = ['legend' => $legend, 'date' => $date, 'series' => $series];

        $this->view->assign([
            'lineData' => $lineData
        ]);
        return $this->view->fetch();
    }

    public function index()
    {

        $legend = ['MONEY','USDT','ETH','BCH','BTC'];
        $series = [];
        $date = [];
        $firstDay = strtotime(date('Y-m-d', strtotime("-6 month")));
        $sql = 'SELECT from_unixtime(createtime,\'%Y-%m-%d %H:%i\') createTime,content from jun_balance 
WHERE createtime>:firstDay and username="yang";';
        $yangBalances = Db::query($sql, ['firstDay' => $firstDay]);
        $moneyData = [];
        foreach ($yangBalances as $key=>$balance){
            $content = json_decode($balance['content'],true);
            foreach ($legend as $k=>$v){
                $moneyData[$v][]=$content[$v];
            }
            $date[] = $balance['createTime'];
        }
        foreach ($legend as $k=>$v){
            $series[] = [
                'name' => $v,
                'type' => 'line',
                'data' => $moneyData[$v]
            ];
        }
        $lineData = ['legend' => $legend, 'date' => $date, 'series' => $series];

        $legend = ['MONEY','USDT','EOS','BCH','BTC'];
        $series = [];
        $date = [];
        $firstDay = strtotime(date('Y-m-d', strtotime("-6 month")));
        $sql = 'SELECT from_unixtime(createtime,\'%Y-%m-%d %H:%i\') createTime,content from jun_balance 
WHERE createtime>:firstDay and username="xu";';
        $yangBalances = Db::query($sql, ['firstDay' => $firstDay]);
        $moneyData = [];
        foreach ($yangBalances as $key=>$balance){
            $content = json_decode($balance['content'],true);
            foreach ($legend as $k=>$v){
                $moneyData[$v][]=$content[$v];
            }
            $date[] = $balance['createTime'];
        }
        foreach ($legend as $k=>$v){
            $series[] = [
                'name' => $v,
                'type' => 'line',
                'data' => $moneyData[$v]
            ];
        }
        $xuLineData = ['legend' => $legend, 'date' => $date, 'series' => $series];



        $this->view->assign([
            'lineData' => $lineData,
            'xuLineData' => $xuLineData,
        ]);
        return $this->view->fetch();
    }

}
