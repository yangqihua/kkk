define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            var markets = ['doge_usdt', 'bch_usdt', 'crv_usdt', 'btc_usdt', '1inch_usdt', 'api3_usdt', 'badger_usdt'];
            for (var i = 0; i < markets.length; i++) {
                var market = markets[i];
                var lineData = chartList[market];
                var percent = chartList[market]['percent'];
                var inventoryChart = Echarts.init(document.getElementById(market));
                var coin = market.split("_")[0];
                var inventoryOption = {
                    title: {
                        text: coin + '年化:' + percent + '%',
                        textStyle: {
                            color: '#27C24C',
                            fontSize: '16'
                        },
                    },
                    tooltip: {
                        trigger: 'axis'
                    },
                    legend: {
                        data: lineData['legend']
                    },
                    grid: {
                        "bottom": 100,
                    },
                    toolbox: {
                        feature: {
                            saveAsImage: {}
                        }
                    },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: lineData['date']
                        // data: []
                    },
                    yAxis: {
                        type: 'value',
                        boundaryGap: [0, '100%'],
                        scale: true,
                    },
                    dataZoom: [
                        {
                            "xAxisIndex": [
                                0
                            ],
                            bottom: 30,
                            "start": 0,
                            "end": 100,
                            handleIcon: 'path://M306.1,413c0,2.2-1.8,4-4,4h-59.8c-2.2,0-4-1.8-4-4V200.8c0-2.2,1.8-4,4-4h59.8c2.2,0,4,1.8,4,4V413z',
                            handleSize: '110%',
                            handleStyle: {
                                color: "#aaa",
                            },
                            textStyle: {
                                color: "#27C24C"
                            },
                            borderColor: "#aaa"
                        },
                        {
                            "type": "inside",
                            "show": true,
                            // "height": 15,
                            "start": 30,
                            "end": 100
                        }
                    ],
                    series: lineData['series']
                };
                inventoryChart.setOption(inventoryOption);
            }
        }
    };

    return Controller;
});