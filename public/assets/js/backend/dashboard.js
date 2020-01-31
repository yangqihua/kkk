define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var inventoryChart = Echarts.init(document.getElementById('k_chart'));
            var inventoryOption = {
                title: {
                    text: '余额趋势',
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

            var xuInventoryChart = Echarts.init(document.getElementById('xu_chart'));
            var xuInventoryOption = {
                title: {
                    text: '余额趋势',
                    textStyle: {
                        color: '#27C24C',
                        fontSize: '16'
                    },
                },
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    data: xuLineData['legend']
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
                    data: xuLineData['date']
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
                series: xuLineData['series']
            };
            xuInventoryChart.setOption(xuInventoryOption);
        }
    };

    return Controller;
});