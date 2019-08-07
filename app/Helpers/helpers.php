<?php 
/**
 * [respond api接口返回格式]
 * @param  [type] $status [状态]
 * @param  string $msg    [消息]
 * @param  string $data   [数据]
 * @return [type]         [description]
 */
function respond($status,$msg='',$data = '')
{
    return response([
         'status'  => $status,
         'message' => $msg,
         'data'    => $data
    ],200);
}

/**
 * [curl_request curl请求]
 * @param  [string]  $url          [访问的URL]
 * @param  string  $post         [数据(不填则为GET)]
 * @param  string  $cookie       [提交的$cookies]
 * @param  integer $returnCookie [是否返回]
 * @return [type]                [description]
 */
function curl_request($url,$post='',$cookie='', $returnCookie=0)
{
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_REFERER, "http://XXX");
        if($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        if($returnCookie){
            list($header, $body) = explode("\r\n\r\n", $data, 2);
            preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
            $info['cookie']  = substr($matches[1][0], 1);
            $info['content'] = $body;
            return $info;
        }else{
            return json_decode($data,true);
        }
}

/**
 * 添加规格计算商品的笛卡儿积
 * @param  [type] $arr [description]
 * @return [type]      [description]
 */
function cartesian_product($arr)
{

    if(count($arr) >= 2)
    {
        $list = [];
        $arr1 = array_shift($arr);
        $arr2 = array_shift($arr);

        if(!empty($arr1['spec_item']))
        {
            foreach($arr2['spec_item'] as $k=>$v)
            {
                foreach($arr1['spec_item'] as $key=>$value)
                {
                    $list[] = [
                                'key_name' => $arr1['name'].':'.$value['item'].' '.$arr2['name'].':'.$v['item'],
                                'key'      => $value['id'].'_'.$v['id'],
                                'price'    => $value['price']+$v['price']
                    ];
                }
            }
            
        }else{

            foreach($arr2['spec_item'] as $k=>$v)
            {
                foreach($arr1 as $key=>$value)
                {
                    $list[] = [
                                'key_name' => $value['key_name'].' '.$arr2['name'].':'.$v['item'],
                                'key'      => $value['key'].'_'.$v['id'],
                                'price'    => $value['price']+$v['price']
                    ];
                }
            }
        }

        array_unshift($arr, $list);
        //递归
        $arr = cartesian_product($arr);
    }

    return $arr;
}

/**
 * 计算某个经纬度的周围某段距离的正方形的四个点
 *
 * @param radius 地球半径 平均6371km
 * @param lng float 经度
 * @param lat float 纬度
 * @param distance float 该点所在圆的半径，该圆与此正方形内切，默认值为1千米
 * @return array 正方形的四个点的经纬度坐标
 */
function returnSquarePoint( $lng, $lat, $distance = 1, $radius = 6371)
{
    $dlng = 2 * asin(sin($distance / (2 * $radius)) / cos(deg2rad($lat)));
    $dlng = rad2deg($dlng);
    $dlat = $distance / $radius;
    $dlat = rad2deg($dlat);

    return array(
        'left_top' => array(
            'lat'  => $lat + $dlat,
            'lng'  => $lng - $dlng
        ),
        'right_top' => array(
            'lat' => $lat + $dlat,
            'lng' => $lng + $dlng
        ),
        'left_bottom' => array(
            'lat' => $lat - $dlat,
            'lng' => $lng - $dlng
        ),
        'right_bottom' => array(
            'lat' => $lat - $dlat,
            'lng' => $lng + $dlng
        )
    );
}

/**
 * 根据经纬度两者之间的距离
 * @param  [type] $lat1 [纬度]
 * @param  [type] $lng1 [经度]
 * @param  [type] $lat2 [纬度]
 * @param  [type] $lng2 [经度]
 * @return [type]       [description]
 */
function getDistance($lat1, $lng1, $lat2, $lng2)
{ 
    $earthRadius = 6367000; //approximate radius of earth in meters 
    $lat1 = ($lat1 * pi() ) / 180; 
    $lng1 = ($lng1 * pi() ) / 180; 
    $lat2 = ($lat2 * pi() ) / 180; 
    $lng2 = ($lng2 * pi() ) / 180; 
    $calcLongitude = $lng2 - $lng1; 
    $calcLatitude = $lat2 - $lat1; 
    $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2); 
    $stepTwo = 2 * asin(min(1, sqrt($stepOne))); 
    $calculatedDistance = $earthRadius * $stepTwo; 
    return round($calculatedDistance); 
}

/**
 * [rsa_encrypt 公钥加密]
 * @param  [type] $data     [需要加密的数据]
 * @param  [type] $key_name [加密的公钥文件名]
 * @return [type]           [description]
 */
function rsa_encrypt($data,$key_name)
{
    $rsa_public_key  = file_get_contents(storage_path($key_name.'.pem'));
    $pu_key          = openssl_get_publickey($rsa_public_key);
    $encrypted       = '';
    openssl_public_encrypt($data, $encrypted, $pu_key);//公钥加密
    return base64_encode($encrypted);
}

/**
 * [rsa_decrypt 私钥解密]
 * @param  [type] $encrypted [需要解密的字符串]
 * @param  [type] $key_name  [加密的私钥文件名]
 * @return [type]            [description]
 */
function rsa_decrypt($encrypted,$key_name)
{

    $decrypted        = '';
    $rsa_private_key  = file_get_contents(storage_path($key_name.'.pem'));
    $pi_key           = openssl_get_privatekey($rsa_private_key);
    openssl_private_decrypt(base64_decode($encrypted), $decrypted, $pi_key);//私钥解密
    return  $decrypted;
}

/**
 * [joint_date 拼接时间]
 * @param  [type] $str [字符串]
 * @return [type]      [description]
 */
function joint_date($str)
{
    return strtotime(date("Y-m-d {$str}"));
}

/**
 * 激光推送
 * @param  [string] $push_id 推送id
 * @param  [string] $data    推送数据
 * @return [Boolean]         [description]
 */
function push_for_jiguang($push_id,$data,$cate = 1)
{
    if(empty($push_id) || empty($data)) return false;

    $result = curl_request(env('PUSH_URL'), [
                'push_id' => $push_id,
                'data'    => $data,
                'type'    => 0,
                'cate'    => $cate
        ]);
    if($result == '200')
        return true;
    else
        return false;
}

/**
 * [write_log 写打印日志]
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function write_log($str)
{
    \Illuminate\Support\Facades\Log::channel('console')->info($str);
}

/**
 * 获取随机字符串
 * @param int $randLength  长度
 * @param int $addtime  是否加入当前时间戳
 * @param int $includenumber   是否包含数字
 * @return string
 */
function get_rand_str($randLength=6,$addtime=1,$includenumber=0){
    if ($includenumber){
        $chars='abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQEST123456789';
    }else {
        $chars='abcdefghijklmnopqrstuvwxyz';
    }
    $len=strlen($chars);
    $randStr='';
    for ($i=0;$i<$randLength;$i++){
        $randStr.=$chars[rand(0,$len-1)];
    }
    $tokenvalue=$randStr;
    if ($addtime){
        $tokenvalue=$randStr.time();
    }
    return $tokenvalue;
}

/**
 * [middle_str_replace 字符串中间替换*]
 * @param  [type]  $str    [需要替换的字符串]
 * @param  integer $start  [字符串的何处开始]
 * @param  integer $length [字符串的何处结束]
 * @return [string]        [替换后的字符串]
 */
function middle_str_replace($str, $start = 4, $length = -4 ) {
    $substr = substr($str, $start,$length);
    return  str_replace($substr,str_repeat('*',strlen($substr)),$str);
}