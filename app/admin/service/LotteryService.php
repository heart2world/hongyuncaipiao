<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/18
 * Time: 17:32
 */

namespace app\admin\service;


use app\admin\model\LotteryInfoModel;
use app\admin\model\LotteryModel;
use app\admin\model\LotteryModeModel;

class LotteryService
{
    /**
     * 生成第一次投注信息
     * @param $last_result  //上一次结果
     * @param $lottery //当前投注主体信息
     * @return array
     */
    public function first_betting($last_result,$lottery){
        $position = ['一','二','三','四','五','六','七','八','九','十'];
        //获取当前模式
        $lotteryModeModel = new LotteryModeModel();
        $mode = $lotteryModeModel->find($lottery['mode_id']);
        //结果信息
        $result = explode(',',$last_result['result']);
        $infoArray = array();
        foreach ($result as $k=>$v){
            $info = $this->stage_result(1,$v,$mode,$lottery['type']);
            $info['bsa'] = $lottery['start_amount'];
            $info['sda'] = $lottery['start_amount'];
            $infoArray[$k] = $info;
            $infoArray[$k]['name'] = $position[$k];
        }
        return $infoArray;
    }

    /**
     * 根据当前需要阶段、结果、模式及类型生成每个号码的投注信息
     * @param $stage  //阶段
     * @param $number  //输入的对应位数结果
     * @param $mode  //模式
     * @param $type  //投注类型 时时彩或北京赛车
     * @return array
     */
    public function stage_result($stage,$number,$mode,$type){
        if($stage == 1){
            $step = $mode['first'];
        }elseif ($stage == 2){
            $step = $mode['second'];
        }elseif ($stage == 3){
            $step = $mode['third'];
        }elseif ($stage == 4){
            $step = $mode['fourth'];
        }elseif ($stage == 5){
            $step = $mode['fifth'];
        }elseif ($stage == 6){
            $step = $mode['sixth'];
        }else{
            $step = $mode['seventh'];
        }
        $bs_sd = $this->big_small_single_double($number,$type);
        if($step == 2){//1正2反
            ($bs_sd['bs'] == 1) ? $bs_sd['bs'] = 2 : $bs_sd['bs'] = 1;
            ($bs_sd['sd'] == 1) ? $bs_sd['sd'] = 2 : $bs_sd['sd'] = 1;
        }
        return $bs_sd;
    }

    /**
     * 判断大小及单双
     * @param $number
     * @param $type
     * @return array
     */
    public function big_small_single_double($number,$type){
        // 2大双 1小单
        if($type == 1){//时时彩
            $big = array(5,6,7,8,9);
            $double = array(0,2,4,6,8);
            if(in_array($number,$big)){//大小
                $big_small = 2;
            }else{
                $big_small = 1;
            }
            if(in_array($number,$double)){//单双
                $single_double = 2;
            }else{
                $single_double = 1;
            }
        }else{
            $big = array(6,7,8,9,10,0);
            $double = array(2,4,6,8,10,0);
            if(in_array($number,$big)){//大小
                $big_small = 2;
            }else{
                $big_small = 1;
            }
            if(in_array($number,$double)){//单双
                $single_double = 2;
            }else{
                $single_double = 1;
            }
        }
        return array('bs'=>$big_small,'sd'=>$single_double);
    }

    /**
     * 根据结果及投注判断对或错
     * @param $number  //某个位置的结果
     * @param $value  //某个位置的值 1或2
     * @param $value_type   //值类型 1大小 2单双
     * @param $type  //投注类型 时时彩或北京赛车
     * @return int
     */
    public function judge_right_wrong($number,$value,$value_type,$type){
        //获取该结果的大小单双属性
        $attributes = $this->big_small_single_double($number,$type);
        if($value_type == 1){//判断大小是否相等
            if($value == $attributes['bs']){
                return 1;
            }else{
                return 0;
            }
        }else{//判断单双是否相等
            if($value == $attributes['sd']){
                return 1;
            }else{
                return 0;
            }
        }
    }

    /**
     * 判断当前应该是第几阶段
     * @param $info
     * @return int
     */
    public function judge_stage($info){
        if($info['status'] == 1 || $info['stage'] >= 7){//盈利或者已经到第7阶段--回到第一阶段
            return 1;
        }else{//亏损--阶段升级
            return $info['stage']+1;
        }
    }

    /**
     * 生成第N次投注信息
     * @param $last_result
     * @param $lottery
     * @param $last_info
     * @return array
     */
    public function nth_time_betting($last_result,$lottery,$last_info){
        $lotteryInfoModel = new LotteryInfoModel();
        $lotteryModeModel = new LotteryModeModel();
        $resultArray = explode(',',$last_result['result']);
        $position = ['一','二','三','四','五','六','七','八','九','十'];
        $infoArray = array();
        foreach ($resultArray as $k=>$v){//循环结果
            $the_info = array();
            foreach ($last_info as $x=>$y){//循环上一轮投注信息
                if($y['seat'] == $k+1){//当前对应位数大小单双信息
                    $stage = $this->judge_stage($y);
                    if($stage > 1){//阶段升级--继续使用上一阶段的模式和起投金额
                        $mode_id = $y['mode_id'];
                        $start_amount = $y['start_amount'];
                        //获取目前连续亏损金额
                        $loss = 0;
                        $lossInfo = $lotteryInfoModel
                            ->where(array('lottery_id'=>$lottery['id'],'seat'=>$y['seat'],'property_type'=>$y['property_type']))
                            ->order('create_time desc')
                            ->limit($stage-1)
                            ->select()->toArray();
                        foreach($lossInfo as $m=>$n){
                            $loss+=$n['money'];
                        }
                        ($loss > 0) ? $loss = 0 : $loss = abs($loss);
                        //下期建议投注金额=（目前亏损额+起投金额X（当前连负数+1））/本轮赔率
                        $property_money = round(($loss+$start_amount*$stage)/$lottery['odds']);
                    }else{//回到第一阶段--则重新使用投注主体信息里面的模式id、起投金额和投注金额
                        $mode_id = $lottery['mode_id'];
                        $start_amount = $lottery['start_amount'];
                        $property_money = $lottery['start_amount'];
                    }
                    $mode = $lotteryModeModel->find($mode_id);
                    //获取投注信息
                    $info = $this->stage_result($stage,$v,$mode,$lottery['type']);
                    if($y['property_type'] == 1){//大小信息
                        $the_info['bs'] = $info['bs'];
                        $the_info['bsa'] = $property_money;
                        $the_info['bsa_amount'] = $start_amount;
                        $the_info['bsa_mode'] = $mode_id;
                        $the_info['bsa_stage'] = $stage;
                    }else{//单双信息
                        $the_info['sd'] = $info['sd'];
                        $the_info['sda'] = $property_money;
                        $the_info['sda_amount'] = $start_amount;
                        $the_info['sda_mode'] = $mode_id;
                        $the_info['sda_stage'] = $stage;
                    }
                    $the_info['name'] = $position[$k];
                }
            }
            $infoArray[] = $the_info;
        }
        return $infoArray;
    }

    /**
     * 更新投注总金额
     * @param $lottery
     * @param $result_id
     * @param int $original_sum
     * @return bool
     */
    public function update_betting_money($lottery,$result_id,$original_sum = 0){
        $lotteryInfoModel = new LotteryInfoModel();
        //获取当前投注总金额
        $new_sum = $lotteryInfoModel->where('lottery_result_id','=',$result_id)->sum('property_money');
        $dataInfo = [
            'id' => $lottery['id'],
            'betting_money' => $lottery['betting_money']-$original_sum+$new_sum
        ];
        $lotteryModel = new LotteryModel();
        $lotteryModel->isUpdate(true)->save($dataInfo);
        return true;
    }

    /**
     * 更新盈利金额
     * @param $lottery
     * @param $result_id
     * @param int $original_sum
     * @return bool
     */
    public function update_profit_money($lottery,$result_id,$original_sum = 0){
        $lotteryInfoModel = new LotteryInfoModel();
        //获取当前投注盈利金额
        $new_profit = $lotteryInfoModel->where('lottery_result_id','=',$result_id)->sum('money');
        $dataInfo = [
            'id' => $lottery['id'],
            'profit_money' => $lottery['profit_money']-$original_sum+$new_profit
        ];
        $lotteryModel = new LotteryModel();
        $lotteryModel->isUpdate(true)->save($dataInfo);
        return true;
    }
}