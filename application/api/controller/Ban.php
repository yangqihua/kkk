<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Http;

/**
 * 首页接口
 */
class Ban extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功',['name'=>'jack']);
    }

    public function coins()
    {
//        $results = json_decode(Http::get('https://data.gateio.co/api2/1/pairs'),true);

        $b_results = json_decode(Http::get("https://api.bcex.vip/api_market/getTradeLists"),true);

        $this->success('请求成功',$b_results);
    }
}
