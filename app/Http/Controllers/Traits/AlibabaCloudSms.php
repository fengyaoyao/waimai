<?php
namespace App\Http\Controllers\Traits;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use Illuminate\Support\Facades\Log;

trait AlibabaCloudSms
{
    public function sendSms($data)
    {
        if (empty($data['PhoneNumbers']) || empty($data['TemplateCode']) || empty($data['TemplateParam'])) {
            throw new \Exception("Parameter cannot be null!");
        }
 
        $alidayu_config = config('common.alidayu');

        if (empty($alidayu_config)) {
            throw new \Exception("Configuration Parameter cannot be null!");
        }

        AlibabaCloud::accessKeyClient($alidayu_config['accessKeyId'], $alidayu_config['accessSecret'])
                    ->regionId('cn-hangzhou')
                    ->asGlobalClient();
                    
        $options = [
            'query' => [
                'PhoneNumbers' => $data['PhoneNumbers'],
                'SignName' => '迪速帮',
                'TemplateCode' => $alidayu_config['templateCode'][$data['TemplateCode']],
                'TemplateParam' => json_encode($data['TemplateParam'],JSON_UNESCAPED_UNICODE)
            ]
        ];

        try {

            $result = AlibabaCloud::rpcRequest()
                                    ->product('Dysmsapi')
                                    // ->scheme('https') // https | http
                                    ->version('2017-05-25')
                                    ->action('SendSms')
                                    ->method('POST')
                                    ->options($options)
                                    ->request();

            Log::channel('sms')->info('',array_merge($options['query'],$result->toArray()));

            if ($result->Code == 'OK') {
                return true;
            }else{
                throw new \Exception($result->Message);
            }

        } catch (ClientException $e) {
            Log::channel('sms')->info('PhoneNumbers:'.$data['PhoneNumbers'] . ' errorMessage:'.$e->getErrorMessage());
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            Log::channel('sms')->info('PhoneNumbers:'.$data['PhoneNumbers'] . ' errorMessage:'.$e->getErrorMessage());
            throw new \Exception($e->getErrorMessage());
        }

    }
}