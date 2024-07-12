<?php

namespace Modera\FileRepositoryBundle\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @author Sergei Lissovski <sergei.lissovski@modera.org>
 */
trait TableTrait
{
    /**
     * @param string[] $headers
     * @param mixed[]  $rows
     */
    private function renderTable(OutputInterface $output, array $headers, array $rows): void
    {
        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render();
    }
}
