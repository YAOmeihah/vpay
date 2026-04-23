<?php
namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Request;
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
        if ($this->shouldRenderAdminApiJson($request)) {
            return $this->renderAdminApiException($e);
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }

    private function shouldRenderAdminApiJson(Request $request): bool
    {
        $path = ltrim($request->pathinfo(), '/');

        if ($path === 'login') {
            return true;
        }

        return str_starts_with($path, 'admin/index');
    }

    private function renderAdminApiException(Throwable $e): Response
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

        return json([
            'code' => 0,
            'msg' => $this->resolveAdminApiMessage($e, $status),
            'data' => null,
        ], $status);
    }

    private function resolveAdminApiMessage(Throwable $e, int $status): string
    {
        $message = trim($e->getMessage());

        if ($message !== '' && ($this->app->isDebug() || $e instanceof \RuntimeException || $e instanceof ValidateException)) {
            return $message;
        }

        if ($status >= 500) {
            return (string) $this->app->config->get('app.error_message', '页面错误！请稍后再试～');
        }

        return $message !== '' ? $message : '请求失败';
    }
}
