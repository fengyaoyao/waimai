<?php

namespace App\Http\Controllers\Common;







/**

 * explain:微信支付数据处理

 * data:2018-9-15

 * author:小李子

 */



class WxpayData

{

	





	/**

	 * [数组转xml]

	 * @param  array  $arr 需要处理的数组

	 * @return xml    $str 返回xml数据

	 */

	public static function arrToXml($arr)

    {

        

        $str='<xml>';



        foreach($arr as $k=>$v) {



          $str.='<'.$k.'>'.$v.'</'.$k.'>';



        }



        $str.='</xml>'; 



        return $str;

    }







    /**

     * [xml转数组]

     * @param  xml   $xml [需要处理的xml]

     * @return array $arr [返回数组]

     */

    public  static function xmlToArr($xml)

    {   



        if(!$xml){

           return  api(200,'xml数据错误');

        }



        //将XML转为array

        //禁止引用外部xml实体

        libxml_disable_entity_loader(true);



        $arr  = 	json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);



        return $arr;

    }







    /**

     * [创建签名]

     * @param  array  $data  [需要参与请求的数据]

     * @param  string $key   [商户秘钥]

     * @return string $sign  [签名]

     */

    public static function makeSign($data,$key)

    {



        //签名步骤一：按字典序排序参数

        ksort($data);



        $str = "";



        foreach ($data as $k => $v)

        {

            if($k != "sign" && $v != "" && !is_array($v)){

                $str .= $k .

                 "=" . $v . "&";

            }

        }

        

        $str 	  = 	trim($str,"&");



        $string   = 	$str."&key=".$key;



        $string   = 	md5($string);



        $sign 	  = 	strtoupper($string);



        return $sign;

    }







    /**

     * 生成随机字符串

     * @return  string $str [随机生成字符串]

     */

    public static function randStr($length=32)

    {

            $chars  = "ABCDEFGHIJKLMNOPQRSTUVWXYabcdefghijklmnopqrstuvwxyz0123456789";  



            $str    = "";



            for($i=0;$i<$length;$i++){  



              $str.= substr($chars, mt_rand(0, strlen($chars)-1),1); 



            }  



            return $str;

    }









    /**

     * [发起curl请求 ]

     * 

     * @param  xml      $xml     [发起请求的xml数据]

     * @param  stirng   $url     [请求微信地址]

     * @param  integer  $type    [请求类型：1公众号,2app]

     * @param  integer  $second  [执行时间]

     * @return xml    	$data    [返回xm数据]

     */

    public static function postXmlCurl($xml, $url,$type='', $second = 30)

    {       

            $ch = curl_init();



            //设置超时

            curl_setopt($ch, CURLOPT_TIMEOUT, $second);

            curl_setopt($ch,CURLOPT_URL, $url);

            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);

            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验

            //设置header

            curl_setopt($ch, CURLOPT_HEADER, FALSE);

            //要求结果为字符串且输出到屏幕上

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        

            //设置证书

            //使用证书：cert 与 key 分别属于两个.pem文件

            //证书文件请放入服务器的非web目录下
            // $params['SSLCERT_PATH'] = storage_path('/cert-app/apiclient_cert.pem');
        // $params['SSLKEY_PATH'] = storage_path('/cert-app/apiclient_key.pem');

            $sslCertPath = storage_path('/cert-app/apiclient_cert.pem');

            $sslKeyPath  = storage_path('/cert-app/apiclient_key.pem');

            



            //$config->GetSSLCertPath($sslCertPath, $sslKeyPath);

            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');

            curl_setopt($ch,CURLOPT_SSLCERT, $sslCertPath);

            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');

            curl_setopt($ch,CURLOPT_SSLKEY, $sslKeyPath);



            //post提交方式

            curl_setopt($ch, CURLOPT_POST, TRUE);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

            //运行curl

            $data = curl_exec($ch);

            //返回结果

            if($data){

                curl_close($ch);

                return $data;

            } else { 

                $error = curl_errno($ch);

                curl_close($ch);

            }





    }









     public static function curlPostSsl($url,$xmldata,$second = 30,$header = array())

    {



             $isdir = $_SERVER['DOCUMENT_ROOT']."/plugins/payment/weixin/cert/";//证书位置;绝对路径



             $ch = curl_init();//初始化curl



             curl_setopt($ch, CURLOPT_TIMEOUT, $second);//设置执行最长秒数

             curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上

             curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页

             curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证

             curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//

             curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型

             curl_setopt($ch, CURLOPT_SSLCERT, $isdir . 'apiclient_cert.pem');//证书位置

             curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型

             curl_setopt($ch, CURLOPT_SSLKEY, $isdir . 'apiclient_key.pem');//证书位置

             curl_setopt($ch, CURLOPT_CAINFO, 'PEM');

             curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');

             if (count($header) >= 1) {

              curl_setopt($ch, CURLOPT_HTTPHEADER, $header);//设置头部

             }

             curl_setopt($ch, CURLOPT_POST, 1);//post提交方式

             curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);//全部数据使用HTTP协议中的"POST"操作来发送



             $data = curl_exec($ch);//执行回话

             if ($data) {

                curl_close($ch);

                return $data;

             } else {

                $error = curl_errno($ch);

                echo "call faild, errorCode:$error\n";

                curl_close($ch);

                return false;

            }



    }







}