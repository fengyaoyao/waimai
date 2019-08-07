<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use League\OAuth2\Server\Exception\OAuthServerException;
use \Swift_TransportException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        OAuthServerException::class,
        Swift_TransportException::class,
        MyException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        //自定义异常
        if ($e instanceof MyException) {
            return response(['status'=>422,'message'=>$e->getMessage(),'data'=>'']);
        }

        //验证异常
        if ($e instanceof ValidationException) {
            return response(['status'=>422,'message'=>$e->validator->errors()->first(),'data'=>'']);
        }

        //请求异常
        if ($e instanceof NotFoundHttpException) {
            return response(['status'=>404,'message'=>'Not Found!','data'=>'']);
        }

        return response(['status'=>500,'message' =>'服务器异常！','data'=>'']);
        // return parent::render($request, $e);
    }
}