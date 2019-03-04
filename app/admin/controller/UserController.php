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
namespace app\admin\controller;

use app\admin\model\RoleUserModel;
use app\admin\model\UserModel;
use cmf\controller\AdminBaseController;

/**
 * Class UserController
 * @package app\admin\controller
 * @adminMenuRoot(
 *     'name'   => '管理组',
 *     'action' => 'default',
 *     'parent' => 'user/AdminIndex/default',
 *     'display'=> true,
 *     'order'  => 10000,
 *     'icon'   => '',
 *     'remark' => '管理组'
 * )
 */
class UserController extends AdminBaseController
{

    /**
     * 管理员列表
     * @adminMenu(
     *     'name'   => '管理员',
     *     'parent' => 'default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员管理',
     *     'param'  => ''
     * )
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $content = hook_one('admin_user_index_view');
        if (!empty($content)) {
            return $content;
        }
        /**搜索条件**/
        $where = array();
        $keyword = $this->request->param('keyword','');
        if($keyword){
            $where['user_nickname|mobile'] = array('like',"%$keyword%");
        }
        $status = $this->request->param('status','');
        if($status){
            ($status == 1) ? $where['user_status'] = array('eq',$status) : $where['user_status'] = array('eq',0);
        }
        $userModel = new UserModel();
        $users = $userModel->where('user_type', 1)
            ->where($where)
            ->order("create_time DESC")
            ->paginate(20);
        $users->appends($this->request->param());
        // 获取分页显示
        $page = $users->render();

        $this->assign("page", $page);
        $this->assign("users", $users);
        $this->assign("data",$users->toArray()['data']);
        return $this->fetch();
    }

    /**
     * 管理员添加提交
     * @adminMenu(
     *     'name'   => '管理员添加提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员添加提交',
     *     'param'  => ''
     * )
     */
    public function add_post()
    {
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'User');
            if ($result !== true) {
                $this->error($result);
            } else {
                //设置用户信息
                $_POST['create_time'] = time();
                $_POST['user_login'] = $this->request->param('mobile');
                $_POST['user_pass'] = cmf_password('123456');
                $userModel = new UserModel();
                $uid = $userModel->allowField(true)->insertGetId($_POST);
                if ($uid !== false) {
                    //绑定角色
                    $roleUserModel = new RoleUserModel();
                    $roleUserModel->insert(["role_id" => 2, "user_id" => $uid]);
                    $this->success("新增成功");
                } else {
                    $this->error("新增失败");
                }
            }
        }
    }

    /**
     * 管理员编辑提交
     * @adminMenu(
     *     'name'   => '管理员编辑提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员编辑提交',
     *     'param'  => ''
     * )
     */
    public function edit_post()
    {
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'User');
            if ($result !== true) {
                $this->error($result);
            } else {
                $userModel = new UserModel();
                $uid = $this->request->param('id');
                $mobile = $this->request->param('mobile');
                $userInfo = $userModel->find($uid);
                //设置用户信息
                $_POST['user_login'] = $mobile;
                if($mobile != $userInfo['mobile']) {
                    $_POST['user_pass'] = cmf_password('123456');
                }
                $return = $userModel->allowField(true)->isUpdate(true)->save($_POST);
                if ($return !== false) {
                    $this->success("编辑成功");
                } else {
                    $this->error("编辑失败");
                }
            }
        }
    }

    /**
     * 重置密码
     */
    public function reset_pass(){
        $id = $this->request->param('id', 0, 'intval');
        $userModel = new UserModel();
        $result = $userModel->where(array('id'=>$id,'user_type'=>1))->setField('user_pass',cmf_password('123456'));
        if($result !== false){
            $this->success("重置密码成功");
        }else{
            $this->error("重置密码失败,网络错误");
        }
    }

    /**
     * 启用、停用管理员
     * @adminMenu(
     *     'name'   => '启用、停用管理员',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '启用、停用管理员',
     *     'param'  => ''
     * )
     */
    public function user_status()
    {
        $id = $this->request->param('id', 0, 'intval');
        if($id == 1){
            $this->error("超级管理员账户禁止该操作");
        }
        $userModel = new UserModel();
        $userInfo = $userModel->find($id);
        if($userInfo['user_status'] == 1) {
            $result = $userModel->where(["id" => $id, "user_type" => 1])->setField('user_status', 0);
            $msg = "停用成功";
        }else{
            $result = $userModel->where(["id" => $id, "user_type" => 1])->setField('user_status', 1);
            $msg = "启用成功";
        }
        if ($result !== false) {
            $this->success($msg);
        } else {
            $this->error('操作失败,网络错误');
        }
    }
}