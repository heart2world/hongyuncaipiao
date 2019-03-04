<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\validate;

use think\Validate;

class LotteryValidate extends Validate
{
    protected $rule = [
        'start_amount' => 'require|regex:/^\d+$/|gt:0',
        'odds' => 'require|number|gt:0',
        'type' => 'require|in:1,2',
        'mode_id' => 'require'
    ];
    protected $message = [
        'start_amount.require' => '起投金额不能为空',
        'start_amount.regex'  => '起投金额只能为整数',
        'start_amount.gt' => '起投金额须大于0',
        'odds.require'  => '赔率不能为空',
        'odds.number'  => '赔率必须为数字',
        'odds.gt'  => '赔率须大于0',
        'type.require'  => '投注类型不能为空',
        'type.in'  => '投注类型数据错误',
        'mode_id.require'  => '初始模式不能为空',
    ];

    protected $scene = [
        'update' => ['start_amount','mode_id'],
    ];
}