<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Tracer\Aspect;

use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AroundInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\Redis\Redis;
use Hyperf\Tracer\SpanStarter;
use Hyperf\Tracer\SpanTagManager;
use Hyperf\Tracer\SwitchManager;
use JsonException;
use OpenTracing\Span;
use OpenTracing\Tracer;
use Throwable;

/**
 * @Aspect
 */
class RedisAspect implements AroundInterface
{
    use SpanStarter;

    public array $classes = [Redis::class . '::__call'];

    public array $annotations = [];

    private Tracer $tracer;

    private SwitchManager $switchManager;

    private SpanTagManager $spanTagManager;

    public function __construct(Tracer $tracer, SwitchManager $switchManager, SpanTagManager $spanTagManager)
    {
        $this->tracer = $tracer;
        $this->switchManager = $switchManager;
        $this->spanTagManager = $spanTagManager;
    }

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @return mixed return the value from process method of ProceedingJoinPoint, or the value that you handled
     * @throws Exception
     * @throws JsonException
     * @throws Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switchManager->isEnable('redis') === false) {
            return $proceedingJoinPoint->process();
        }

        $arguments = $proceedingJoinPoint->arguments['keys'];
        $span = $this->startSpan('Redis' . '::' . $arguments['name']);

        $span->setTag('category', 'datastore');
        $span->setTag('component', 'Redis');

        $span->setTag($this->spanTagManager->get('redis', 'arguments'), json_encode($arguments['arguments'], JSON_THROW_ON_ERROR));
        try {
            $result = $proceedingJoinPoint->process();
            $span->setTag($this->spanTagManager->get('redis', 'result'), json_encode($result, JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            $this->switchManager->isEnable('exception') && $this->appendExceptionToSpan($span, $e);
            throw $e;
        } finally {
            $span->finish();
        }
        return $result;
    }

    private function appendExceptionToSpan(Span $span, Throwable $exception): void
    {
        $span->setTag('error', true);
        $span->setTag($this->spanTagManager->get('exception', 'class'), get_class($exception));
        $span->setTag($this->spanTagManager->get('exception', 'code'), $exception->getCode());
        $span->setTag($this->spanTagManager->get('exception', 'message'), $exception->getMessage());
        $span->setTag($this->spanTagManager->get('exception', 'stack_trace'), (string) $exception);
    }
}
