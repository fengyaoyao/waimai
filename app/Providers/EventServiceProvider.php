<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        
        // 'Laravel\Passport\Events\RefreshTokenCreated' => [
        //     'App\Listeners\PruneOldTokens',
        // ],
        // 'App\Events\SomeEvent' => [
        //     'App\Listeners\EventListener',
        // ],
        // 抢单
        'App\Events\Scramble' => [
            'App\Listeners\ScrambleListener',
        ],
        'App\Events\CalculateBrokerage' => [
            //商户确认订单
            'App\Listeners\CalculateBrokerageListener',
            //更新商家出单号
            'App\Listeners\ShopDayNumListener',
            //推送订单通知给骑手
            'App\Listeners\DistributionPushMessageListener',
        
        ],
        //取消订单
        'App\Events\CancelOrder' => [
            'App\Listeners\CancelOrderListener',
        ],
        //店铺
        'App\Events\Shop' => [
            // 自动营业
            'App\Listeners\ShopBusinessTimeListener',
        ],
        // 提现
        'App\Events\Withdrawal' => [
            'App\Listeners\WithdrawalListener',
        ],
        'App\Events\SaveAccessToken'=>[
            'App\Listeners\SaveAccessTokenListener',
        ],
        //订单完成结算账户
        'App\Events\ClearingAccount'=>[
            'App\Listeners\ClearingAccountListener',
        ],

        //订单结算触发事件
        'App\Events\OtherTrigger' => [

            //商品销量
            'App\Listeners\GoodsSoldListener',

            //店铺销量
            'App\Listeners\ShopSoldListener',

            //订单超时状况记录
            'App\Listeners\OvertimeListener',

            //赠送积分
            'App\Listeners\PresentedExpListener',

            //邀请有奖
            'App\Listeners\InviteListener',

            //结算代理收益
            'App\Listeners\OrderAgencyListener',
        ],

        // 消息推送
        'App\Events\PushMessage' =>[
            //订单状态微信公众号推送
            'App\Listeners\OrderStatusPushMessage',
        ],
    ];
}
