<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf + PicPay.
 *
 * @link     https://github.com/PicPay/hyperf-tracer
 * @document https://github.com/PicPay/hyperf-tracer/wiki
 * @contact  @PicPay
 * @license  https://github.com/PicPay/hyperf-tracer/blob/main/LICENSE
 */
namespace Hyperf\Tracer;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class SwitchManagerFactory
{
    public function __invoke(ContainerInterface $container): SwitchManager
    {
        $config = $container->get(ConfigInterface::class);
        $manager = new SwitchManager();
        $manager->apply($config->get('opentracing.enable', []));
        return $manager;
    }
}
