<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\DependencyInjection\Security\PolicyProvider;

use Ibexa\Bundle\Core\DependencyInjection\Security\PolicyProvider\YamlPolicyProvider;

final class RemoteMediaPolicyProvider extends YamlPolicyProvider
{
    /**
     * @return string[]
     */
    public function getFiles(): array
    {
        return [
            __DIR__ . '/../../../Resources/config/security/policies.yaml',
        ];
    }
}
