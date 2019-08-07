<?php

$router->get('/', function () use ($router) {
    return 'welcome';
    // return $router->app->version();
});

//公共路由组
$router->group(['namespace'=>'Common'], function () use ($router)
{
    //测试接口
    $router->get('test','TestController@index');
    $router->post('test','TestController@index');
    //买家端首页
    $router->post('home','HomeController@index');
    //登录
    $router->post('login','UserController@login');
    //手机验证码登陆
    $router->post('msg_login','UserController@msgLogin');
    //注册
    $router->post('register','UserController@register');
    //刷新token
    $router->post('refresh_token','UserController@refresh');
    //app版本
    $router->post('version','VersionController@app_version');
    //商品详情
    $router->post('goods_info','GoodsController@goods_info');
    //根据店铺格式化商品
    $router->post('goods_format','GoodsController@goodsFormat');
    //发送验证码
    $router->post('send_verify_code','UserController@sendVerifyCode');
    //买家端首页数据初始化接口
    $router->get('init','HomeController@init');
    //支付宝支付回调
    $router->post('alipay_notify','PayNotifyController@aliPayNotify');
    //微信支付回调
    $router->post('wechat_notify','PayNotifyController@wechatPayNotify');
    //店铺营业时间段
    $router->post('business_hours','HomeController@businessHours');
    //积分商品列表
    $router->post('integral_list','IntegralController@list');
    //积分商品分类
    $router->post('integral_cate','IntegralController@cate');
    //积分商城轮播图
    $router->get('slideshow','IntegralController@slideshow');
    //活动详情
    $router->post('activity_info','ActivityController@info');
    //分享活动
    $router->post('static_share','ActivityController@staticShare');
    //招聘
    $router->post('hiring','RecruitmentController@index');
});

//买家端不需要认证的路由组
$router->group(['namespace'=>'Buyer','prefix'=>'buyer'], function () use ($router)
{
    //根据输入内容进行搜索配送区域或校区列表
    $router->post('area_list','BuyerController@area');
    //商品名或店铺名 搜索列表
    $router->post('search','SearchController@search');
    //热门搜索词
    $router->post('hot_search','SearchController@hotSearchWord');
    //首页店铺列表
    $router->post('shop_list','BuyerController@shop_list');
    //店铺详情
    $router->post('shop_info','BuyerController@shop_info');
    //店铺评论列表
    $router->post('evaluation','BuyerController@shop_evaluation');
    //配送列表表
    $router->post('delivery','BuyerController@delivery');
});

//公共需要认证路由组
$router->group(['namespace'=>'Common','middleware' => 'auth'], function () use ($router)
{
    //获取用户id
    $router->get('user_id','UserController@userid');
    //更新用户信息
    $router->post('change_info','UserController@change_info');
    //获取用户信息
    $router->get('user_info','UserController@user_info');
    //订单详情
    $router->post('order_info','OrderController@order_info');
    //申请提现
    $router->post('withdrawal','WithdrawalController@applicant_withdrawal');
    //绑定用户手机号码
    $router->post('build_mobile','UserController@buildMobile');
    //获取支付签名
    $router->post('pay_sign','PaySignController@sign');
    //用户关于店铺信息是否首单、收藏
    $router->post('user_has_shop','UserController@userHasShop');
    // 投诉建议
    $router->post('suggest_submit','SuggestController@submit');
    //修改密码
    $router->post('change_password','UserController@changePassword');
});

//买家端需要认证的路由组
$router->group(['namespace'=>'Buyer','prefix'=>'buyer','middleware' =>'auth'], function () use ($router)
{
    //编辑用户收货地址
    $router->post('edit_address','UserAddressController@edit');
    //删除用户收货地址
    $router->delete('del_address','UserAddressController@destroy');
    $router->post('del_address','UserAddressController@destroy');
    //设置默认收货地址
    $router->post('default_address','UserAddressController@set_default_address');
    //用户收货地址列表
    $router->post('list_address','UserAddressController@list');
    //用户投诉
    $router->post('user_complaint','ComplaintController@complaint');
    //获取能够投诉的订单列表
    $router->get('complaint/order_list','ComplaintController@orderList');
    //我的投诉列表
    $router->get('complaint_list','ComplaintController@list');
    $router->post('complaint_list','ComplaintController@list');
    //用户收藏店铺和取消收藏
    $router->post('collect_shop','BuyerController@collect_shop');
    //用户收藏店铺列表
    $router->get('collect_list','BuyerController@collect_list');
    $router->post('collect_list','BuyerController@collectForArea');
    //确认订单
    $router->post('confirm_order','OrderController@confirm_order');
    //提交订单
    $router->post('submit_order','OrderController@submit_order');
    //取消订单
    $router->post('cancel_order','OrderController@cancel_order');
    //订单列表
    $router->post('latest_order','OrderController@latest_order');
    //历史订单列表
    $router->post('history_order','OrderController@history_order');
    //订单评价
    $router->post('submit_evaluation','OrderController@submit_evaluation');
    //优惠卷列表
    $router->post('coupon_list','BuyerController@coupon_list');
    //推荐商品
    $router->post('recommend','BuyerController@my_recommend');
    //用户日志列表
    $router->post('account_log','BuyerController@account_log');
    //确认收货
    $router->post('confirm_receiving','OrderController@confirmReceiving');
    //订单列表
    $router->post('order_list','OrderController@orderList');
    //是否弹出活动
    $router->post('is_alert_activity','RedEnvelopeController@isAlertActivity');
    //提交订单
    $router->post('tijiaodingdan','OrderController@submitOrder');
    //积分商品兑换记录列表
    $router->post('exchange_list','ExchangeController@exchangeList');
    //积分商品兑换
    $router->post('exchange','ExchangeController@exchange');
    //领取店铺专享红包
    $router->post('red_packet/acquisition','RedPacketController@acquisition');
    //通用红包兑换店铺专享红包
    $router->post('red_packet/exchange','RedPacketController@exchange');
    //红包列表
    $router->post('red_packet/list','RedPacketController@list');
    //获取店铺可以领取和兑换的红包
    $router->post('red_packet/usable','RedPacketController@usable');
    //取消红包兑换
    $router->post('red_packet/cancel','RedPacketController@cancelExchange');
    
    //是否开通会员 和 会员红包总金额
    $router->post('red_packet/member','RedPacketController@member');

});

//店铺路由组
$router->group(['namespace'=>'Shop','prefix'=>'shop','middleware' =>['auth','check_shop']], function () use ($router)
{
    //获取店铺信息
    $router->post('info','ShopController@info');
    //店铺首页
    $router->get('home','ShopController@shop_home');
    //获取当前用户所有的店铺
    $router->get('shop_list','ShopController@shop_list');
    //编辑店铺商品
    $router->post('edit_goods','GoodsController@edit_goods');
    //删除店铺商品
    $router->delete('del_goods','GoodsController@destroy');
    //根据分类获取商品
    $router->post('search_goods','GoodsController@search_goods');
    //获取店铺商品分类
    $router->post('cate','GoodsCateController@cate');
    //编辑店铺商品分类
    $router->post('edit_cate','GoodsCateController@edit');
    //删除店铺商品分类
    $router->delete('del_cate','GoodsCateController@destroy');
    //编辑店铺商品属性
    $router->post('edit_spec','SpecsController@edit');
    //编辑店铺商品属性
    $router->delete('del_spec','SpecsController@destroy');
    //编辑店铺商品规格
    $router->post('edit_item','ItemController@edit');
    //删除店铺商品规格
    $router->delete('del_item','ItemController@destroy');
    //post店铺活动
    $router->post('edit_prom','ShopController@edit_prom');
    //post店铺活动
    $router->put('edit_prom','ShopController@edit_prom');
    //删除店铺活动
    $router->post('del_prom','ShopController@del_prom');
    //改变店铺状态
    $router->post('change_status','ShopController@change_status');
    //获取店铺活动
    $router->post('shop_prom','ShopController@shop_prom');
    //店铺订单
    $router->post('shop_order','ShopController@shop_order');
    //店铺确认订单
    $router->post('receiving_order','ShopController@receiving_order');
    //店铺订单出餐确认
    $router->post('appeared_meal','ShopController@appeared_meal');
    //店铺取消订单
    $router->post('cancel_order','ShopController@cancel_order');
    //店铺历史订单
    $router->post('history_order','ShopController@history_order');
     //店铺商品统计
    $router->post('goods_statistics','ShopController@goods_statistics');
    $router->post('goods_count','ShopController@goodsCount');
    //店铺一周收益统计
    $router->post('week_statistics','ShopController@week_statistics');
    //店铺收益统计
    $router->post('earnings_statistics','ShopController@earnings_statistics');
    $router->post('earnings_count','ShopController@earningsCount');
    //店铺评论列表
    $router->post('evaluation','ShopController@evaluation_list');
    //店铺评论详情
    $router->post('evaluation_info','ShopController@evaluation_info');
    //回复评论
    $router->post('reply_message','ShopController@reply_message');
    //删除回复评论
    $router->post('reply_destroy','ShopController@destroy_reply_message');
    //商家自主配送
    $router->post('take_delivery','ShopController@take_delivery');
    //检查店铺是否绑定打印机
    $router->post('exists_printr','ShopController@exists_printr');
    //设置住店铺
    $router->post('main_shop','ShopController@main_shop');
    //商户提现列表
    $router->post('withdrawal_list','ShopController@withdrawal_list');
    //商户收支记录列表
    $router->post('mer_bill','ShopController@merBill');
    //商家公告
    $router->get('notice','ShopController@notice');
    //确认收货
    $router->post('confirm_receiving','ShopController@confirmReceiving');
    //编辑店铺商品分类排序
    $router->post('cate_sort','GoodsCateController@goodsCateSort');
    //商户提现页面所需数据
    $router->get('withdrawal_need_data','ShopController@withdrawalNeedData');
    //编辑店铺证件照
    $router->post('edit_identification_photo','ShopController@editIdentificationPhoto');
    //编辑店铺营业时间
    $router->post('opening_time','ShopController@shopOpeningTime');
    //店铺营业时间列表
    $router->post('opening_list','ShopController@shopOpeningTimeList');
    //删除营业时间段
    $router->post('del_opening','ShopController@shopOpeningTimeDelete');
    //提现申请
    $router->post('withdrawal_apply','WithdrawalController@apply');
    //统计详情
    $router->post('statistics_info','ShopController@statisticsInfo');
    //统计详情
    $router->post('count_info','ShopController@countInfo');
    //商家售后退款
    $router->post('agree_refuse_refund','AfterSaleController@agreeOrRefuseRefund');
    // 红包列表
    $router->post('red_packet/list','ShopRedPacketController@list');
    // 新增、修改红包
    $router->post('red_packet/save','ShopRedPacketController@save');
    // 删除红包
    $router->post('red_packet/destroy','ShopRedPacketController@destroy');
    // 兑换红包配置
    $router->post('red_packet/exchange_config','ShopRedPacketController@exchangeConfig');
});

//配送路由组
$router->group(['namespace'=>'Delivery','prefix'=>'delivery','middleware'=>['auth','check_delivery']], function () use ($router)
{
    //配送端首页
    $router->get('index','DeliveryController@index');
    //抢单
    $router->post('scramble','DeliveryController@scramble');
    //取单、确认用户已收货
    $router->post('take_delivery','DeliveryController@take_delivery');
    //订单明细
    $router->post('order_detail','DeliveryController@order_detail');
    //配送统计
    $router->get('statistics','DeliveryController@statistics');
    //配送统计
    $router->get('statistics','DeliveryController@statistics');
    //历史订单
    $router->post('history_order','DeliveryController@history_order');
    //骑手提现列表
    $router->post('withdrawal_list','DeliveryController@withdrawal_list');
    //骑手收支记录列表
    $router->post('user_bill','DeliveryController@userBill');
    //骑手打卡签到
    $router->get('check_in','DeliveryController@checkIn');
    //骑手公告
    $router->get('notice','DeliveryController@notice');
    //取消订单
    $router->post('cancel_order','DeliveryController@cancelOrder');
    //订单列表
    $router->post('order_list','RelayController@orderList');
    //接力扫码交接订单
    $router->post('scan_code','RelayController@scanCodeForOrder');
    //接力抢单
    $router->post('scramble_order','RelayController@scrambleForOrder');
    //确认交接
    $router->post('confirm_connect','RelayController@confirmConnect');
    //取消交接
    $router->post('cancel_connect','RelayController@cancelConnect');
    //奖励主页
    $router->get('award','AwardController@awardMain');
    //领取奖励
    $router->get('receive','AwardController@receive');
    //领取奖励
    $router->get('award_visible','AwardController@visibleAward');
    //获取当前校区是否存在值班
    $router->get('zhi_ban','DeliveryController@zhiBan');
    //骑手所在区域的分组
    $router->get('group','DeliveryController@deliveryGroupFindShop');
    // 聚合抢单
    $router->post('fight_order','AggregationController@fightOrder');
    // 聚合订单列表
    $router->post('gathering_order','AggregationController@orderList');
});
// 管理端需要认证的路由
$router->group(['namespace'=>'Manage','prefix'=>'manage','middleware' =>'auth_token'], function () use ($router)
{
    //首页
    $router->get('home','AdminController@home');
    //订单管理列表
    $router->post('order_list','AdminController@order_list');
    //订单备注
    $router->post('admin_note','AdminController@admin_note');
    //指定配送人员
    $router->post('appoint','AdminController@appoint');
    //配送人员列表
    $router->post('appoint_list','AdminController@appoint_list');
    //店铺列表
    $router->post('shop_list','AdminController@shop_list');
    //申请入驻列表
    $router->post('proposer_list','AdminController@proposer_list');
    //用户列表
    $router->post('user_list','AdminController@user_list');
    //提现列表
    $router->post('withdrawal_list','AdminController@withdrawal_list');
    //配送人员管理列表
    $router->post('horseman_list','AdminController@horseman_list');
    //区域列表
    $router->get('area_list','AdminController@area_list');
    //配送人员金额列表
    $router->post('delivery_list','AdminController@delivery_amount_list');
    //订单详情
    $router->post('order_info','AdminController@order_info');
    //区域数据统计
    $router->post('statistics','AdminController@statistics');
    //店铺评论
    $router->post('evaluation_list','AdminController@evaluation_list');
    //评论详情
    $router->post('evaluation_info','AdminController@evaluation_info');
    //申请提现
    $router->post('withdrawal','WithdrawalController@applicant_withdrawal'); //待废除
    $router->post('withdrawal_apply','WithdrawalController@apply');
    //管理端提现页面所需数据
    $router->get('withdrawal_need_data','WithdrawalController@withdrawalNeedData');
    //管理端信息
    $router->get('admin_info','AdminController@admin_info');
    $router->post('shop_find_user','AdminController@userPushIds');
    //统计详情
    $router->post('statistics_info','AdminController@statisticsInfo');
    //统计详情
    $router->post('count_info','AdminController@countInfo');
});

// 管理端不需要认证的路由
$router->group(['namespace'=>'Manage','prefix'=>'manage'], function () use ($router)
{
    //登陆
    $router->post('login','CommonController@login');
});

//服务器交互路由组
$router->group(['namespace'=>'Common','middleware'=>'server_ip_auth'], function () use ($router)
{
    //服务器交互查找用户
    $router->post('find_user','ServerInteractionController@find_user');
    //服务器交互加密字符串
    $router->post('encrypt_str','ServerInteractionController@encrypt_str');
    //确认订单
    $router->post('confirm_order','ServerInteractionController@confirmOrder');
    // 添加规格和属性
    $router->post('attribute_pecifications','ServerInteractionController@batchAddAttributeSpecifications');
    $router->get('goods_json/{shop_id:[0-9]+}','ServerInteractionController@goodsJson');
    //确认订单送达
    $router->get('auto_order_delivery','ServerInteractionController@confirmOrderDelivery');
    //取消未支付的订单
    $router->get('auto_cancel_order','ServerInteractionController@CancelOrderForAll');
});