<?php

namespace Modera\FileRepositoryBundle\Contributions;

use Modera\ExpanderBundle\Ext\ContributorInterface;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2015 Modera Foundation
 */
class RoutingResourcesProvider implements ContributorInterface
{
    public function getItems(): array
    {
        return [
            '@ModeraFileRepositoryBundle/Resources/config/routing.yml',
        ];
    }
}
