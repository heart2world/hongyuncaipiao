<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/18
 * Time: 15:56
 */

namespace app\admin\controller;

use app\admin\model\LotteryInfoModel;
use app\admin\model\LotteryModel;
use app\admin\model\LotteryModeModel;
use app\admin\model\LotteryResultModel;
use app\admin\service\LotteryService;
use cmf\controller\AdminBaseController;

class LotteryController extends AdminBaseController
{
    /**
     * 鸿运法
     * @return mixed
     */
    public function index(){
        /*搜索条件*/
        $type = $this->request->param('type',1,'intval');
        $oby = $this->request->param('oby',1,'intval');
        if($type == 1){
            $order_by = 'create_time';//创建时间
        }elseif ($type == 2){
            $order_by = 'betting_money';//投注总金额
        }else{
            $order_by = 'profit_money';//目前亏损
        }
        ($oby == 1) ? $order_by.=' desc' : $order_by.=' asc';//1倒叙2正序

        $lotteryModel = new LotteryModel();
        $user_id = cmf_get_current_admin_id();
        if($user_id == 1){
            $where = array();
        }else{
            $where['user_id'] = array('eq',$user_id);
        }
        $lottery = $lotteryModel->where($where)->order($order_by)->paginate(20);
        //$lotteryInfoModel = new LotteryInfoModel();
        $lotteryResultModel = new LotteryResultModel();
        foreach ($lottery as $k=>$v){
            //投注总额
            //$bet_sum = $lotteryInfoModel->where(array('lottery_id'=>$v['id']))->sum('property_money');
            //$lottery[$k]['bet_sum'] = $bet_sum;
            //投注次数
            $bet_time = $lotteryResultModel->where(array('lottery_id'=>$v['id'],'genre'=>array('in',[2,3])))->count();
            $lottery[$k]['bet_time'] = $bet_time;
            //当前盈利
            //$profit = $lotteryInfoModel->where(array('lottery_id'=>$v['id'],'status'=>array('in',[1,2])))->sum('money');
            //$lottery[$k]['profit'] = $profit;
        }
        $lottery->appends($this->request->param());
        //分页
        $page = $lottery->render();
        $this->assign([
            "lottery" => $lottery,
            "page" => $page,
            "data" => $lottery->toArray()['data']
        ]);
        return $this->fetch();
    }

    /**
     * 新增投注
     * @return mixed
     */
    public function add(){
        if($this->request->isPost()){
            $result = $this->validate($this->request->param(), 'Lottery');
            if ($result !== true) {
                $this->error($result);
            } else {
                $type = $this->request->param('type');
                $_POST['create_time'] = time();
                $_POST['user_id'] = cmf_get_current_admin_id();
                $lotteryModel = new LotteryModel();
                $return_id = $lotteryModel->allowField(true)->insertGetId($_POST);
                if($return_id){
                    if($type == 1) {
                        $this->success("创建成功", url('Lottery/five',array('id'=>$return_id)));
                    }else{
                        $this->success("创建成功", url('Lottery/ten',array('id'=>$return_id)));
                    }
                }else{
                    $this->error("创建失败,网络错误");
                }
            }
        }
        //模式
        $lotteryModeModel = new LotteryModeModel();
        $mode = $lotteryModeModel->select();
        $this->assign([
            'mode' => $mode
        ]);
        return $this->fetch();
    }

    /**
     * 更新投注
     */
    public function update(){
        if($this->request->isPost()){
            $result = $this->validate($this->request->param(), 'Lottery.update');
            if ($result !== true) {
                $this->error($result);
            } else {
                $id = $this->request->param('id');
                $lotteryModel = new LotteryModel();
                $lottery = $lotteryModel->where(array('id'=>$id,'user_id'=>cmf_get_current_admin_id()))->field('id,start_amount,type,mode_id,update_time')->find();
                if(!$lottery){
                    $this->error("当前投注信息不存在或你无权操作");
                }
                $_POST['update_time'] = time();
                $return = $lotteryModel->allowField(true)->isUpdate(true)->save($_POST);
                if($return !== false){
                    $mode_id = $this->request->param('mode_id');
                    if($mode_id != $lottery['mode_id']) {//修改了模式
                        //投注次数大于0时,记录修改投注模式的信息
                        $lotteryResultModel = new LotteryResultModel();
                        $bet_time = $lotteryResultModel->where(array('lottery_id' => $id, 'genre' => array('in', [2,3])))->count();
                        if ($bet_time > 0) {
                            file_put_contents('change.txt', "时间：" . date('Y-m-d H:i:s') . ",将投注信息" . var_export($lottery->toArray(), true) . "已投注次数为" . $bet_time . "次时更改为" . var_export($_POST, true) . "\n\r", FILE_APPEND);
                        }
                    }
                    if($lottery['type'] == 1) {
                        $this->success("保存成功", url('Lottery/five', array('id' =>$id)));
                    }else{
                        $this->success("保存成功", url('Lottery/ten', array('id' =>$id)));
                    }
                }else{
                    $this->error("保存失败,网络错误");
                }
            }
        }
    }

    /**
     * 时时彩--类型五
     * @return mixed
     */
    public function five(){
        $id = $this->request->param('id','','intval');
        $lotteryModel = new LotteryModel();
        $lottery = $lotteryModel->find($id);
        if(!$lottery || !in_array($lottery['type'],[1,2])){
            $this->error("不存在该投注",url('Lottery/index'));
        }
        if($lottery['type'] != 1){//不是当前类型
            $this->redirect(url('Lottery/ten',array('id'=>$id)));
        }
        //模式
        $lotteryModeModel = new LotteryModeModel();
        $mode = $lotteryModeModel->select();
        //获取结果及投注信息
        $lotteryResultModel = new LotteryResultModel();
        $result_info = $lotteryResultModel->where('lottery_id','=',$id)->order('create_time desc')->select();
        if($result_info){//对象转数组
            $result_info = $result_info->toArray();
        }
        $lotteryInfoModel = new LotteryInfoModel();
        foreach ($result_info as $k=>$v){
            (!empty($v['result'])) ? $list = explode(',', $v['result']) : $list = array('','','','','');
            //获取投注信息
            $info = $lotteryInfoModel->where('lottery_result_id','=',$v['id'])
                ->order('seat ASC,property_type ASC')
                ->select()->toArray();
            $lists = $lotteryInfoModel->bs_sd_name($list,$info);//匹配大小单双name及数据组装
            $result_info[$k]['lists'] = $lists;
        }
        //计算每轮盈亏以及该轮投注每注的盈亏
        $result_info = $lotteryInfoModel->calculate_profit_loss($result_info,$lottery);
        //投注总额
        //$bet_sum = $lotteryInfoModel->where(array('lottery_id'=>$lottery['id']))->sum('property_money');
        //当前盈利
        //$profit = $lotteryInfoModel->where(array('lottery_id'=>$lottery['id'],'status'=>array('in',[1,2])))->sum('money');
        $last_result = $lotteryResultModel->where('lottery_id','=',$id)->order('create_time desc')->limit(1)->find();
        //输入结果按钮是否可以点击
        (!$last_result || $last_result['status'] == 2) ? $btn_result = 1 : $btn_result = 0;
        //新一轮投注按钮是否可以点击
        (!$last_result || $last_result['status'] != 1) ? $btn_betting = 0 : $btn_betting = 1;
        $this->assign([
            'lottery' => $lottery,
            'mode' => $mode,
            'result' => $result_info,
            'count' => count($result_info),
            'btn_result' => $btn_result,
            'btn_betting' => $btn_betting,
            //'bet_sum' => $bet_sum,
            //'profit' => $profit
        ]);
        return $this->fetch();
    }

    /**
     * 北京赛车--类型十
     * @return mixed
     */
    public function ten(){
        $id = $this->request->param('id','','intval');
        $lotteryModel = new LotteryModel();
        $lottery = $lotteryModel->find($id);
        if(!$lottery || !in_array($lottery['type'],[1,2])){
            $this->error("不存在该投注",url('Lottery/index'));
        }
        if($lottery['type'] != 2){//不是当前类型
            $this->redirect(url('Lottery/five',array('id'=>$id)));
        }
        //模式
        $lotteryModeModel = new LotteryModeModel();
        $mode = $lotteryModeModel->select();
        //获取结果及投注信息
        $lotteryResultModel = new LotteryResultModel();
        $result_info = $lotteryResultModel->where('lottery_id','=',$id)->order('create_time desc')->select();
        if($result_info){//对象转数组
            $result_info = $result_info->toArray();
        }
        $lotteryInfoModel = new LotteryInfoModel();
        foreach ($result_info as $k=>$v){
            (!empty($v['result'])) ? $list = explode(',', $v['result']) : $list = array('','','','','','','','','','');
            //获取投注信息
            $info = $lotteryInfoModel->where('lottery_result_id','=',$v['id'])
                ->order('seat ASC,property_type ASC')
                ->select()->toArray();
            $lists = $lotteryInfoModel->bs_sd_name($list,$info);//匹配大小单双name及数据组装
            $result_info[$k]['lists'] = $lists;
        }
        //计算每轮盈亏以及该轮投注每注的盈亏
        $result_info = $lotteryInfoModel->calculate_profit_loss($result_info,$lottery);
        //投注总额
        //$bet_sum = $lotteryInfoModel->where(array('lottery_id'=>$lottery['id']))->sum('property_money');
        //当前盈利
        //$profit = $lotteryInfoModel->where(array('lottery_id'=>$lottery['id'],'status'=>array('in',[1,2])))->sum('money');
        $last_result = $lotteryResultModel->where('lottery_id','=',$id)->order('create_time desc')->limit(1)->find();
        //输入结果按钮是否可以点击
        (!$last_result || $last_result['status'] == 2) ? $btn_result = 1 : $btn_result = 0;
        //新一轮投注按钮是否可以点击
        (!$last_result || $last_result['status'] != 1) ? $btn_betting = 0 : $btn_betting = 1;
        $this->assign([
            'lottery' => $lottery,
            'mode' => $mode,
            'result' => $result_info,
            'count' => count($result_info),
            'btn_result' => $btn_result,
            'btn_betting' => $btn_betting,
            //'bet_sum' => $bet_sum,
            //'profit' => $profit
        ]);
        return $this->fetch();
    }

    /**
     * 输入结果
     */
    public function result_action(){
        if($this->request->isPost()){
            $lottery_id = $this->request->param('lottery_id',0,'intval');
            //获取投注信息
            $lotteryModel = new LotteryModel();
            $lottery = $lotteryModel->find($lottery_id);
            if(!$lottery){
                $this->error("数据错误");
            }
            if($lottery['user_id'] != cmf_get_current_admin_id()){
                $this->error("你无权进行该操作");
            }
            //类型位数长度
            ($lottery['type'] == 1) ? $type_length = 5 : $type_length = 10;
            //获取上一轮投注
            $lotteryResultModel = new LotteryResultModel();
            $last_result = $lotteryResultModel->where(array('lottery_id'=>$lottery_id))->order('create_time desc')->limit(1)->find();
            if(!$last_result || $last_result['status'] == 2){
                $info = $this->request->param('info/a');
                if(is_array($info) && count($info) == $type_length){
                    foreach ($info as $k=>$v){
                        if($type_length == 5){
                            if(!preg_match('/^[0-9]$/',$v['value'])){
                                $this->error("各位数必须为0-9的整数");
                            }
                        }else{
                            if(!preg_match('/^([0-9]|10)$/',$v['value'])){
                                $this->error("各位数必须为0-10的整数");
                            }
                        }
                    }
                    $result = array_column($info,'value');
                    if(!$last_result) {
                        $dataInfo = [
                            'lottery_id' => $lottery_id,
                            'result' => implode(',', $result),
                            'create_time' => time(),
                            'update_time' => time()
                        ];
                        $return = $lotteryResultModel->save($dataInfo);
                    }else{
                        $dataInfo = [
                            'id' => $last_result['id'],
                            'result' => implode(',', $result),
                            'status' => 1,
                            'genre' => 3,
                            'update_time' => time()
                        ];
                        $return = $lotteryResultModel->isUpdate(true)->save($dataInfo);
                        //更新投注盈亏信息
                        $lotteryInfoModel = new LotteryInfoModel();
                        $lotteryInfoModel->update_profit_loss($lottery,$last_result['id']);
                        //更新盈利金额
                        $lotteryService = new LotteryService();
                        $lotteryService->update_profit_money($lottery,$last_result['id']);
                    }
                    if($return !== false){
                        $this->success("保存成功");
                    }else{
                        $this->error("保存失败,网络错误");
                    }
                }else{
                    $this->error("参数传入错误,请检查");
                }
            }else{
                $this->error("当前步骤禁止输入结果");
            }
        }
    }

    /**
     * 获取新一轮投注信息
     */
    public function new_betting(){
        if($this->request->isPost()){
            $lottery_id = $this->request->param('lottery_id',0,'intval');
            //获取投注信息
            $lotteryModel = new LotteryModel();
            $lottery = $lotteryModel->find($lottery_id);
            if(!$lottery){
                $this->error("数据错误");
            }
            if($lottery['user_id'] != cmf_get_current_admin_id()){
                $this->error("你无权进行该操作");
            }
            //是否可以新一轮投注
            $lotteryResultModel = new LotteryResultModel();
            $last_result = $lotteryResultModel
                ->where('lottery_id','=',$lottery_id)
                ->order('create_time desc')
                ->limit(1)
                ->find();
            if(!$last_result || $last_result['status'] != 1){
                $this->error("当前步骤禁止新一轮投注");
            }
            //查询是否有上一轮投注
            $lotteryInfoModel = new LotteryInfoModel();
            $last_info = $lotteryInfoModel
                ->where(array('lottery_id'=>$lottery_id,'lottery_result_id'=>$last_result['id']))
                ->select();
            $lotteryService = new LotteryService();
            if(count($last_info) < 1){//获取计算第一次投注信息
                $betting = $lotteryService->first_betting($last_result,$lottery);
                $this->success("获取成功",'',$betting);
            }else{//获取计算本轮需要的投注信息
                $betting = $lotteryService->nth_time_betting($last_result,$lottery,$last_info);
                $this->success("获取成功",'',$betting);
            }
        }
    }

    /**
     * 创建投注
     */
    public function betting_action(){
        if($this->request->isPost()){
            $lottery_id = $this->request->param('lottery_id',0,'intval');
            //获取投注信息
            $lotteryModel = new LotteryModel();
            $lottery = $lotteryModel->find($lottery_id);
            if(!$lottery){
                $this->error("数据错误");
            }
            if($lottery['user_id'] != cmf_get_current_admin_id()){
                $this->error("你无权进行该操作");
            }
            //类型位数长度
            ($lottery['type'] == 1) ? $type_length = 5 : $type_length = 10;
            //是否可以新一轮投注
            $lotteryResultModel = new LotteryResultModel();
            $last_result = $lotteryResultModel
                ->where('lottery_id','=',$lottery_id)
                ->order('create_time desc')
                ->limit(1)
                ->find();
            if(!$last_result || $last_result['status'] != 1){
                $this->error("当前步骤禁止新一轮投注");
            }
            //获取上传的投注信息
            $betting = $this->request->param('betting/a');
            if(!is_array($betting) || count($betting) != $type_length){
                $this->error("参数传入错误,请检查");
            }
            foreach ($betting as $k=>$v){
                if(!in_array($v['bs'],array(1,2)) || !in_array($v['sd'],array(1,2))){
                    $this->error("参数传入错误,请检查");
                }
                if(!preg_match('/^[1-9]\d*$/',$v['sda']) || !preg_match('/^[1-9]\d*$/',$v['bsa'])){
                    $this->error("投注金额必须为正整数");
                }
            }
            //查询是否有上一轮投注
            $lotteryInfoModel = new LotteryInfoModel();
            $last_info = $lotteryInfoModel
                ->where(array('lottery_id'=>$lottery_id,'lottery_result_id'=>$last_result['id']))
                ->select();
            $lotteryInfo = array();
            //创建空结果
            $emptyData = ['lottery_id' => $lottery_id,'status' => 2,'genre' => 2,'create_time'=>time()];
            $result_id = $lotteryResultModel->insertGetId($emptyData);
            if(!$result_id){
                $this->error("投注失败,网络错误");
            }
            $lotteryService = new LotteryService();
            if(count($last_info) < 1){//创建第一次投注
                //匹配投注信息
                foreach ($betting as $k=>$v){
                    //单双
                    $lotteryInfo[] = [
                        'lottery_id' => $lottery_id,
                        'lottery_result_id' => $result_id,
                        'seat' => $k+1,
                        'property' => $v['sd'],
                        'property_type' => 2,
                        'property_money' => $v['sda'],
                        'start_amount' => $lottery['start_amount'],
                        'mode_id' => $lottery['mode_id'],
                        'stage' => 1,
                        'create_time' => time()
                    ];
                    //大小
                    $lotteryInfo[] = [
                        'lottery_id' => $lottery_id,
                        'lottery_result_id' => $result_id,
                        'seat' => $k+1,
                        'property' => $v['bs'],
                        'property_type' => 1,
                        'property_money' => $v['bsa'],
                        'start_amount' => $lottery['start_amount'],
                        'mode_id' => $lottery['mode_id'],
                        'stage' => 1,
                        'create_time' => time()
                    ];
                }
            }else{//创建第N次投注
                //获取计算本轮需要的投注信息
                $betting_service = $lotteryService->nth_time_betting($last_result,$lottery,$last_info);
                //匹配投注信息
                foreach ($betting as $k=>$v){
                    //单双
                    $lotteryInfo[] = [
                        'lottery_id' => $lottery_id,
                        'lottery_result_id' => $result_id,
                        'seat' => $k+1,
                        'property' => $v['sd'],
                        'property_type' => 2,
                        'property_money' => $v['sda'],
                        'start_amount' => $betting_service[$k]['sda_amount'],
                        'mode_id' => $betting_service[$k]['sda_mode'],
                        'stage' => $betting_service[$k]['sda_stage'],
                        'create_time' => time()
                    ];
                    //大小
                    $lotteryInfo[] = [
                        'lottery_id' => $lottery_id,
                        'lottery_result_id' => $result_id,
                        'seat' => $k+1,
                        'property' => $v['bs'],
                        'property_type' => 1,
                        'property_money' => $v['bsa'],
                        'start_amount' => $betting_service[$k]['bsa_amount'],
                        'mode_id' => $betting_service[$k]['bsa_mode'],
                        'stage' => $betting_service[$k]['bsa_stage'],
                        'create_time' => time()
                    ];
                }
            }
            $lotteryInfoModel->saveAll($lotteryInfo);
            //更新投注总金额
            $lotteryService->update_betting_money($lottery,$result_id);
            $this->success("投注成功");
        }
    }

    /**
     * 修改结果
     */
    public function change_result(){
        if($this->request->isPost()){
            $lottery_id = $this->request->param('lottery_id',0,'intval');
            //获取投注信息
            $lotteryModel = new LotteryModel();
            $lottery = $lotteryModel->find($lottery_id);
            if(!$lottery){
                $this->error("数据错误");
            }
            if($lottery['user_id'] != cmf_get_current_admin_id()){
                $this->error("你无权进行该操作");
            }
            $return = false;
            $results_id = '';
            $info_id = $this->request->param('fid',0,'intval');
            $value = $this->request->param('value',0,'intval');
            $lotteryInfoModel = new LotteryInfoModel();
            $original_sum = 0;
            $original_profit = 0;
            if($info_id){//修改投注信息
                $type = $this->request->param('type',1,'intval');
                //获取原信息
                $info = $lotteryInfoModel->find($info_id);
                if(!$info){
                    $this->error("参数传入错误");
                }
                if($type == 1){//修改大小单双
                    if(!in_array($value,array(1,2))){
                        $this->error("参数传入错误");
                    }
                    $changeData = ['id'=>$info_id,'property'=>$value];
                }else{//修改投注金额
                    if(!preg_match('/^[1-9]\d*$/',$value)){
                        $this->error("投注金额必须为正整数");
                    }
                    $changeData = ['id'=>$info_id,'property_money'=>$value];
                }
                $results_id = $info['lottery_result_id'];
                //获取该轮投注原投注金额
                $original_sum = $lotteryInfoModel->where('lottery_result_id','=',$results_id)->sum('property_money');
                //获取该轮投注原盈利金额
                $original_profit = $lotteryInfoModel->where('lottery_result_id','=',$results_id)->sum('money');
                $return = $lotteryInfoModel->isUpdate(true)->save($changeData);
            }
            $result_id = $this->request->param('rid',0,'intval');
            if($result_id){//修改输入结果
                $results_id = $result_id;
                //获取原结果
                $lotteryResultModel = new LotteryResultModel();
                $result = $lotteryResultModel->find($result_id);
                if(!$result || empty($result['result'])){
                    $this->error("参数传入错误");
                }
                $result_info = explode(',',$result['result']);
                $position = $this->request->param('position',0,'intval');
                if($lottery['type'] == 1){
                    if(!preg_match('/^[0-9]$/',$value)){
                        $this->error("此位数必须为0-9的整数");
                    }
                    if(!in_array($position,[0,1,2,3,4])){
                        $this->error("参数传入错误");
                    }
                }else{
                    if(!preg_match('/^([0-9]|10)$/',$value)){
                        $this->error("此位数必须为0-10的整数");
                    }
                    if(!in_array($position,[0,1,2,3,4,5,6,7,8,9])){
                        $this->error("参数传入错误");
                    }
                }
                //获取该轮投注原投注金额
                $original_sum = $lotteryInfoModel->where('lottery_result_id','=',$result_id)->sum('property_money');
                //获取该轮投注原盈利金额
                $original_profit = $lotteryInfoModel->where('lottery_result_id','=',$result_id)->sum('money');
                $result_info[$position] = $value;
                $changeData = ['id'=>$result_id,'result'=>implode(',',$result_info)];
                $return = $lotteryResultModel->isUpdate(true)->save($changeData);
            }
            if($return !== false){
                //更新投注盈亏信息
                $lotteryInfoModel->update_profit_loss($lottery,$results_id);
                $lotteryService = new LotteryService();
                //更新投注总金额
                $lotteryService->update_betting_money($lottery,$results_id,$original_sum);
                //更新盈利金额
                $lotteryService->update_profit_money($lottery,$results_id,$original_profit);
                $this->success("编辑成功");
            }else{
                $this->error("编辑失败,网络错误");
            }
        }
    }

    /**
     * 删除投注信息
     */
    public function delete(){
        $id = $this->request->param('id',0,'intval');
        $lotteryModel = new LotteryModel();
        $lottery = $lotteryModel->where(array('id'=>$id,'user_id'=>cmf_get_current_admin_id()))->find();
        if(!$lottery){
            $this->error("当前投注信息不存在或你无权操作");
        }
        $result = $lotteryModel->where('id','=',$id)->delete();
        if($result !== false){
            //删除输入结果
            $lotteryResultModel = new LotteryResultModel();
            $lotteryResultModel->where('lottery_id','=',$id)->delete();
            //删除相关轮投注
            $lotteryInfoModel = new LotteryInfoModel();
            $lotteryInfoModel->where('lottery_id','=',$id)->delete();
            $this->success("删除成功");
        }else{
            $this->error("删除失败,网络错误");
        }
    }
}