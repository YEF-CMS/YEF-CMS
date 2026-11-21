<?php

declare(strict_types=1);

namespace Yef\Console;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\ModuleInstall\ModuleOffer;

use function sprintf;

/**
 * Shows a downloaded module on the console.
 */
final class ModuleOfferPrinter
{
    public static function print(OutputInterface $output, ModuleOffer $offer): void
    {
        $rows = [
            ['Module', $offer->info->title],
            ['Name', $offer->info->name->value],
            ['Release', $offer->release->version->value],
            ['Repository', $offer->release->repository->address()],
            ['Works with Yef', $offer->info->coreConstraint],
        ];
        if ($offer->installed instanceof \Yef\Domain\Module\Install\InstalledModule) {
            $rows[] = ['Installed now', $offer->installed->version->value];
        }

        new Table($output)->setRows($rows)->render();

        foreach ($offer->problems as $problem) {
            $output->writeln(sprintf('<error>[PROBLEM]</error> %s%s', $problem->kind->value, $problem->detail === '' ? '' : ': ' . $problem->detail));
        }
    }
}
