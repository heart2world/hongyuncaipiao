<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\admin\model;

use app\admin\service\LotteryService;
use think\Model;

class LotteryInfoModel extends Model
{
    /**
     * 匹配大小单双name及数据组装
     * @param $list
     * @param $info
     * @return array
     */
    public function bs_sd_name($list,$info){
        $lists = array();//转二维数组
        $colors = array('grey','green','blue','purple','yellow','orange','red');
        foreach ($list as $x=>$y){
            $lists[$x]['value'] = $y;
            $lists[$x]['sd'] = array();
            $lists[$x]['bs'] = array();
            foreach ($info as $k=>$v){
                //颜色赋予
                $v['color'] = $colors[$v['stage']-1];
                //匹配赋值
                if($v['seat'] == $x+1){
                    if($v['property_type'] == 2){//单双
                        ($v['property'] == 1) ? $name = '单' : $name = '双';
                        $v['name'] = $name;
                        $lists[$x]['sd'] = $v;
                    }else{//大小
                        ($v['property'] == 1) ? $name = '小' : $name = '大';
                        $v['name'] = $name;
                        $lists[$x]['bs'] = $v;
                    }
                }
            }
        }
        return $lists;
    }

    /**
     * 计算每轮盈亏以及该轮投注每注的盈亏
     * @param $result_info
     * @param $lottery
     * @return mixed
     */
    public function calculate_profit_loss($result_info,$lottery){
        foreach ($result_info as $k=>$v){
            if($v['genre'] == 1){//只有结果---即第一次输入结果
                $result_info[$k]['profit_loss'] = '';
                $result_info[$k]['total_money'] = '';
            }
            if($v['genre'] == 2){//只有投注---即投注了还未输入新的结果
                $profit_loss = 0;
                foreach ($v['lists'] as $x=>$y){
                    $profit_loss+=$y['bs']['property_money'];
                    $profit_loss+=$y['sd']['property_money'];
                }
                $result_info[$k]['profit_loss'] = $profit_loss;
                $result_info[$k]['total_money'] = $profit_loss;
            }
            if($v['genre'] == 3){//投注并输入了结果
                $lotteryService = new LotteryService();
                $profit_loss = 0;
                $total_money = 0;
                foreach ($v['lists'] as $x=>$y){
                    $bsInfo = $y['bs'];
                    $total_money+=$bsInfo['property_money'];
                    //大小判断
                    $bs_res = $lotteryService->judge_right_wrong($y['value'],$bsInfo['property'],$bsInfo['property_type'],$lottery['type']);
                    if($bs_res == 1){//对
                        $each_bs = round($bsInfo['property_money']*$lottery['odds']);
                        $bsInfo['profit_loss'] = "<span class='right'>+$each_bs</span>";
                        $bsInfo['name'] = "<span class='right'>".$bsInfo['name']."</span>";
                    }else{//错
                        $each_bs = -intval($bsInfo['property_money']);
                        $bsInfo['profit_loss'] = "<span class='error'>$each_bs</span>";
                        $bsInfo['name'] = "<span class='error'>".$bsInfo['name']."</span>";
                    }
                    $bsInfo['money'] = $each_bs;
                    $result_info[$k]['lists'][$x]['bs'] = $bsInfo;
                    $profit_loss+=$each_bs;

                    $sdInfo = $y['sd'];
                    $total_money+=$sdInfo['property_money'];
                    //单双判断
                    $sd_res = $lotteryService->judge_right_wrong($y['value'],$sdInfo['property'],$sdInfo['property_type'],$lottery['type']);
                    if($sd_res == 1){//对
                        $each_sd = round($sdInfo['property_money']*$lottery['odds']);
                        $sdInfo['profit_loss'] = "<span class='right'>+$each_sd</span>";
                        $sdInfo['name'] = "<span class='right'>".$sdInfo['name']."</span>";
                    }else{//错
                        $each_sd = -intval($sdInfo['property_money']);
                        $sdInfo['profit_loss'] = "<span class='error'>$each_sd</span>";
                        $sdInfo['name'] = "<span class='error'>".$sdInfo['name']."</span>";
                    }
                    $sdInfo['money'] = $each_sd;
                    $result_info[$k]['lists'][$x]['sd'] = $sdInfo;
                    $profit_loss+=$each_sd;
                }
                if($profit_loss > 0){
                    $result_info[$k]['profit_loss'] = "<span class='right'>+$profit_loss</span>";
                }else{
                    $result_info[$k]['profit_loss'] = "<span class='error'>$profit_loss</span>";
                }
                $result_info[$k]['total_money'] = $total_money;
            }
        }
        return $result_info;
    }

    /**
     * 输入或修改结果后,更新其对应投注信息盈亏
     * @param $lottery
     * @param $result_id
     * @return bool
     */
    public function update_profit_loss($lottery,$result_id){
        //获取结果及投注信息
        $lotteryResultModel = new LotteryResultModel();
        $result_info = $lotteryResultModel->where('id','=',$result_id)->select();
        if(!$result_info || $result_info[0]['genre'] != 3){
            return false;
        }
        if($result_info){//对象转数组
            $result_info = $result_info->toArray();
        }
        foreach ($result_info as $k=>$v){
            (!empty($v['result'])) ? $list = explode(',', $v['result']) : $list = array('','','','','');
            //获取投注信息
            $info = self::where('lottery_result_id','=',$v['id'])
                ->order('seat ASC,property_type ASC')
                ->select()->toArray();
            $lists = self::bs_sd_name($list,$info);//匹配大小单双name及数据组装
            $result_info[$k]['lists'] = $lists;
        }
        //计算每轮盈亏以及该轮投注每注的盈亏
        $result_info = self::calculate_profit_loss($result_info,$lottery);
        $dataInfo = array();
        foreach ($result_info as $x=>$y){
            foreach ($y['lists'] as $m=>$n){
                //大小
                ($n['bs']['money'] > 0) ? $bs_status = 1 : $bs_status = 2;
                $dataInfo[] = ['id'=>$n['bs']['id'],'money'=>$n['bs']['money'],'status'=>$bs_status];
                //单双
                ($n['sd']['money'] > 0) ? $sd_status = 1 : $sd_status = 2;
                $dataInfo[] = ['id'=>$n['sd']['id'],'money'=>$n['sd']['money'],'status'=>$sd_status];
            }
        }
        self::saveAll($dataInfo);
        return true;
    }
}