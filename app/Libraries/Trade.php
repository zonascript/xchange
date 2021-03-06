<?php

namespace App\Libraries;
use GuzzleHttp\Exception;
use Illuminate\Support\Facades\DB;
use GuzzleHttp;
use Response;

class Trade
{
    //Check ask for requested rate and coin
    public static function checkAsks($data)
    {
        
        if(isset($data['rate']) && isset($data['from_coin']) && isset($data['to_coin']))
        {
            $where = [
                'from_currency'=>$data['to_coin'], 
                'to_currency'=>$data['from_coin']
            ];

            $resp = DB::table('ask')->where($where)
            ->where('rate','<=',$data['rate'])->select(['id', 'user_id', 'volume', 'fee', 'rate'])->orderBy('rate','asc');
            
            if($resp->count())
            {
                return ['status'=>true, 'message'=>'', 'data'=>$resp->get()];
            }
            else{
                return ['status'=>true, 'message'=>'', 'data'=>[]];
            }
        }
        else{
            return ['status'=>false, 'message'=>'missing parameters', 'data'=>[]];
        }
    }

    public static function checkBids($data)
    {
        if(isset($data['rate']) && isset($data['from_coin']) && isset($data['to_coin']))
        {
            $where = [
                    'from_currency'=>$data['to_coin'], 
                    'to_currency'=>$data['from_coin']
            ];
            
            $resp = DB::table('bid')->where($where)
            ->where('rate','>=',$data['rate'])->select(['id', 'user_id', 'volume', 'fee', 'rate'])->orderBy('rate','desc');
            
            if($resp->count())
            {
                return ['status'=>true, 'message'=>'', 'data'=>$resp->get()];
            }
            else{
                return ['status'=>true, 'message'=>'', 'data'=>[]];
            }
        }
        else{
            return ['status'=>false, 'message'=>'missing parameters', 'data'=>[]];
        }
    }
	
	// Start... Get singe Zero volume issue....harikesh -dated: 09-06-2018
	
	public static function checkZeroVolumeRemove()
    {
		
		$resp = DB::table('ask')->where('volume','<=0')->select(['id']);
		
		if($resp->count()>0)
		{
			 $s = DB::table('ask')->where('volume','<=0')->delete();
		}
    }
	
	public static function checkZeroVolumeRemoveBuy()
    {
		$resp = DB::table('bid')->where('volume','<=0')->select(['id']);
		
		if($resp->count()>0)
		{
			 $s = DB::table('bid')->where('volume','<=0')->delete();
		}
    }	
	// END by harikesh -dated: 09-06-2018
	
    
    //Insert bid into bid table
    public static function bid($data)
    {
        $data['bid_at'] = date('Y-m-d H:i:s');
        $resp =  DB::table('bid')->insert($data);
        return ['status'=>true, 'message'=>'insert bid into bid table', 'data'=>[]];
    }

    //Insert ask into bid table
    public static function ask($data)
    {
        $data['ask_at'] = date('Y-m-d H:i:s');
        $resp =  DB::table('ask')->insert($data);
        return ['status'=>true, 'message'=>'insert ask into ask table', 'data'=>[]];
    }

    //Insert trade into trade table
    public static function tradeEvent($data)
    {
        $resp =  DB::table('trade')->insert($data);
        return ['status'=>true, 'message'=>'insert trade into trade table', 'data'=>[]];
    }

    

    //Update bid into bid table
    public static function deductBidVolume($d)
    {
        if($d['mode']=='delete') // Delete bid row from bid table
        {
            $s = DB::table('bid')->where(['id'=>$d['id']])->delete();
        }
        else       // Update bid row
        {
            $s = DB::table('bid')->where(['id'=>$d['id']])->update([
                'volume' => DB::raw('volume - '.$d['volume'])]);
        }
    }

    //Update ask into ask table
    public static function deductAskVolume($d)
    {
        if($d['mode']=='delete') // Delete ask row from ask table
        {
            $s = DB::table('ask')->where(['id'=>$d['id']])->delete();
        }
        else       // Update ask row
        {
            $s = DB::table('ask')->where(['id'=>$d['id']])->update([
                'volume' => DB::raw('volume - '.$d['volume'])]);
        }
    }

    //Get row details 
    public static function getRow($data)
    {
        return $s = DB::table($data['table'])->where($data['where'])->select($data['select'])->first();
    }

    public static function getAvailableCoins(){
        return ['xrp','eth','btc','bch', 'inr','ltc','gix'];
    }

    public static function getPairCoinCoins(){
        
        $resp = DB::table('ticker')->where(['status'=>1])->select(['pair_coin', 'parent_coin','rate', 'last_24_rate', 'buy_commission', 'sell_commission', 'from_coin_decimals','to_coin_decimals']);    
        
        $pairs = [];
        if($resp->count())
        {
            foreach($resp->get() as $k=>$v){
                $pairs[$v->parent_coin][] = $v;
            }
        }

        return $pairs;
    }

    public static function getTrades($d)
    {
        
        $where = " ((from_currency='".$d['from_currency']."' and to_currency='".$d['to_currency']."') || (from_currency='".$d['to_currency']."' and to_currency='".$d['from_currency']."')) ";
        
        if(isset($d['user_id']))
        {
            $where .= " AND (seller_id='".$d['user_id']."' || buyer_id='".$d['user_id']."') ";
        }
        
        $query = "select from_currency, to_currency, volume, trade_type, trade_at, seller_rate, buyer_rate, seller_id, buyer_id, buy_fee, sell_fee from trade where $where order by trade_at desc limit 0,12";
        
        return $s = DB::select($query);
    }

    public static function ticker($parent_coin="")
    {      	  
	  if($parent_coin!=""){		
		return $s = DB::table('ticker')->where(array('parent_coin'=>$parent_coin))->select(['pair_coin', 'rate'])->get();	
	  }else{
		return $s = DB::table('ticker')->select(['pair_coin', 'rate'])->get();
	  }
	  
    }

    public static function updateTiker($data){
        
        $s = DB::table('ticker')->where($data['where'])->select('id');
        if($s->count()==0)
        {
            $resp =  DB::table('ticker')->insert($data['data']);
        }
        else{
            $resp =  DB::table('ticker')->where($data['where'])->update($data['data']);
        }
    }

    public static function transactoin_history($user_id){
        return $s = DB::table('user_txns')->where(['user_id'=>$user_id])->select(
            ['txn_type', 'txn_id', 'currency', 'amount', 'createdTime', 'state']
        )->get();
    }
	
	// Added by harikesh - dated: 11-06-2018 - Insert notification for trade customer
	//Insert trade into trade table
    public static function InsertTradeNotification($data)
    {
        $resp =  DB::table('notifications')->insert($data);
    }
	// END
	
}