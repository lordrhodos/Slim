<?php
/**
 * Slim Framework (https://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2018 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/4.x/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Slim\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

class ErrorMiddleware
{
    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @var bool
     */
    protected $displayErrorDetails;

    /**
     * @var bool
     */
    protected $logErrors;

    /**
     * @var bool
     */
    protected $logErrorDetails;

    /**
     * @var array
     */
    protected $handlers = [];

    /**
     * @var ErrorHandlerInterface|callable|null
     */
    protected $defaultErrorHandler;

    /**
     * ErrorMiddleware constructor.
     * @param CallableResolverInterface $callableResolver
     * @param ResponseFactoryInterface $responseFactory
     * @param bool $displayErrorDetails
     * @param bool $logErrors
     * @param bool $logErrorDetails
     */
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) {
        $this->callableResolver = $callableResolver;
        $this->responseFactory = $responseFactory;
        $this->displayErrorDetails = $displayErrorDetails;
        $this->logErrors = $logErrors;
        $this->logErrorDetails = $logErrorDetails;
    }

    /**
     * Invoke error handler
     *
     * @param ServerRequestInterface $request   The most recent Request object
     * @param ResponseInterface      $response  The most recent Response object
     * @param callable $next
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): ResponseInterface {
        try {
            return $next($request, $response);
        } catch (Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param Throwable $exception
     * @return mixed
     */
    public function handleException(ServerRequestInterface $request, Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $request = $exception->getRequest();
        }

        $exceptionType = get_class($exception);
        $handler = $this->getErrorHandler($exceptionType);
        $response = $this->responseFactory->createResponse($exception->getCode());
        $params = [
            $request,
            $response,
            $exception,
            $this->displayErrorDetails,
            $this->logErrors,
            $this->logErrorDetails,
        ];

        return call_user_func_array($handler, $params);
    }

    /**
     * Get callable to handle scenarios where an error
     * occurs when processing the current request.
     *
     * @param string $type Exception/Throwable name. ie: RuntimeException::class
     * @return callable|ErrorHandler
     */
    public function getErrorHandler(string $type)
    {
        if (isset($this->handlers[$type])) {
            return $this->callableResolver->resolve($this->handlers[$type]);
        }

        return $this->getDefaultErrorHandler();
    }

    /**
     * Set callable to handle scenarios where an error
     * occurs when processing the current request.
     *
     * This service MUST return a callable that accepts
     * three arguments optionally four arguments.
     *
     * 1. Instance of \Psr\Http\Message\ServerRequestInterface
     * 2. Instance of \Psr\Http\Message\ResponseInterface
     * 3. Instance of \Exception
     * 4. Boolean displayErrorDetails
     * 5. Boolean logErrors
     * 6. Boolean logErrorDetails
     *
     * The callable MUST return an instance of
     * \Psr\Http\Message\ResponseInterface.
     *
     * @param string $type Exception/Throwable name. ie: RuntimeException::class
     * @param callable|ErrorHandlerInterface $handler
     */
    public function setErrorHandler(string $type, $handler)
    {
        $this->handlers[$type] = $handler;
    }

    /**
     * Set callable as the default Slim application error handler.
     *
     * This service MUST return a callable that accepts
     * three arguments optionally four arguments.
     *
     * 1. Instance of \Psr\Http\Message\ServerRequestInterface
     * 2. Instance of \Psr\Http\Message\ResponseInterface
     * 3. Instance of \Exception
     * 4. Boolean displayErrorDetails
     * 5. Boolean logErrors
     * 6. Boolean logErrorDetails
     *
     * The callable MUST return an instance of
     * \Psr\Http\Message\ResponseInterface.
     *
     * @param callable|ErrorHandler $handler
     */
    public function setDefaultErrorHandler($handler)
    {
        $this->defaultErrorHandler = $handler;
    }

    /**
     * Get default error handler
     *
     * @return ErrorHandler|callable
     */
    public function getDefaultErrorHandler()
    {
        if ($this->defaultErrorHandler !== null) {
            return $this->callableResolver->resolve($this->defaultErrorHandler);
        }

        return new ErrorHandler($this->responseFactory);
    }
}
