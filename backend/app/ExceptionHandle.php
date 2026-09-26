<?php
namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 框架自身响应流程（如 redirect/response 抛出）交给系统处理
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }
        // 参数校验异常 → 422
        if ($e instanceof ValidateException) {
            return resp_fail((string) $e->getError(), 422);
        }
        // HTTP 异常（404 路由不存在等）→ 对应状态码
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            return resp_fail($e->getMessage() ?: '请求错误', $status, null, $status);
        }
        // 其余异常 → 500，非调试模式不泄露内部细节
        $message = $this->app->isDebug() ? $e->getMessage() : '服务器内部错误';
        return resp_fail($message, 500, null, 500);
    }
}
