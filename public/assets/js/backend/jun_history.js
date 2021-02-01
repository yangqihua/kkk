define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'jun_history/index' + location.search,
                    add_url: 'jun_history/add',
                    edit_url: 'jun_history/edit',
                    del_url: 'jun_history/del',
                    multi_url: 'jun_history/multi',
                    table: 'jun_history',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'market', title: __('Market')},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'direction', title: __('Direction')},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'coin_before', title: __('Coin_before'), operate:'BETWEEN'},
                        {field: 'coin_after', title: __('Coin_after'), operate:'BETWEEN'},
                        {field: 'money_before', title: __('Money_before'), operate:'BETWEEN'},
                        {field: 'money_after', title: __('Money_after'), operate:'BETWEEN'},
                        {field: 'cap_before', title: __('Cap_before'), operate:'BETWEEN'},
                        {field: 'cap_after', title: __('Cap_after'), operate:'BETWEEN'},
                        {field: 'status', title: __('Status')},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});