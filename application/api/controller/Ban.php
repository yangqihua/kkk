<?php

namespace app\api\controller;

use app\admin\model\ban\Bg;
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
        $usdts = $b_results['main']['USDT'];
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
                if ($b_rate > $this->rate) {
                    $b_data[$value]['status'] = 1;
                    $b_data[$value]['remark'] = 'BCEX 买一('.$b_data[$value]['b_bid'][0].')比 Gate卖一('.$b_data[$value]['g_ask'][0].') 大' . $b_rate . '数量为：' . min($b_data[$value]['b_bid'][1], $b_data[$value]['g_ask'][1]);
                }
                if ($g_rate > $this->rate) {
                    $b_data[$value]['status'] = 2;
                    $b_data[$value]['remark'] = 'Gate 买一('.$b_data[$value]['g_bid'][0].')比 BCEX卖一('.$b_data[$value]['b_ask'][0].') 大' . $g_rate . '数量为：' . min($b_data[$value]['g_bid'][1], $b_data[$value]['b_ask'][1]);
                }
                $model = new Bg();
                $model->save([
                    'token'=>$value,
                    'g_ask'=>$b_data[$value]['g_ask'][0].'-'.$b_data[$value]['g_ask'][1],
                    'g_bid'=>$b_data[$value]['g_bid'][0].'-'.$b_data[$value]['g_bid'][1],
                    'b_ask'=>$b_data[$value]['b_ask'][0].'-'.$b_data[$value]['b_ask'][1],
                    'b_bid'=>$b_data[$value]['b_bid'][0].'-'.$b_data[$value]['b_bid'][1],
                    'status'=>$b_data[$value]['status'],
                    'remark'=>$b_data[$value]['remark'],
                    'record'=>'暂无成交',
                ]);
            }
//            if (count($b_data) > 3) {
//                break;
//            }
        }
        $this->success('请求成功', $b_data);
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
        $data = json_decode($results, true);
        return $data['data'];
    }
}
