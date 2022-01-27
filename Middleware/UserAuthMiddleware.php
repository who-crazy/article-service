<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Utils\Context;
use Phper666\JwtAuth\Jwt;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserAuthMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var HttpResponse
     */
    protected $response;

    /**
     * @Inject()
     * @var Jwt
     */
    protected $jwt;

    protected $prefix = 'Bearer';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $isValidToken = false;
            // 根据具体业务判断逻辑走向，这里假设用户携带的token有效
            $token = $request->getHeader('Authorization')[0] ?? '';

            if (strlen($token) > 0) {
                $token = ucfirst($token);
                $arr = explode($this->prefix . ' ', $token);
                $token = $arr[1] ?? '';
                if (strlen($token) > 0 && $this->jwt->checkToken()) {
                    $isValidToken = true;
                }
            } else {
                return $this->response->json(['code' => 401, 'message' => '请登陆！', 'data' => []]);
            }
            if ($isValidToken) {
                $request = $request->withAttribute('uid', $this->jwt->getParserData()['user_id']);
                Context::set(ServerRequestInterface::class, $request);
                return $handler->handle($request);
            }
            return $this->response->json(['code' => 401, 'message' => '登陆失败！', 'data' => []]);
        } catch (\Exception $e) {
            return $this->response->json(['code' => 1010, 'message' => $e->getMessage(), 'data' => []]);
        }
    }
}