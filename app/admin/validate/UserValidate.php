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

class UserValidate extends Validate
{
    protected $rule = [
        'user_nickname' => 'require',
        'mobile' => 'require|regex:/^1[3456789][0-9]{9}$/|unique:user,mobile',
    ];
    protected $message = [
        'user_nickname.require' => '用户姓名不能为空',
        'mobile.require'  => '手机号不能为空',
        'mobile.regex' => '手机号格式不正确',
        'mobile.unique'  => '手机号已经存在',
    ];
}