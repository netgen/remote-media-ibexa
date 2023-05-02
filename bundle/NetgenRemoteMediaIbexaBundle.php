<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle;

use Netgen\Bundle\RemoteMediaIbexaBundle\DependencyInjection\Security\PolicyProvider\RemoteMediaPolicyProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NetgenRemoteMediaIbexaBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var \Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension $ibexaCoreExtension */
        $ibexaCoreExtension = $container->getExtension('ibexa');
        $ibexaCoreExtension->addPolicyProvider(new RemoteMediaPolicyProvider());
    }
}
