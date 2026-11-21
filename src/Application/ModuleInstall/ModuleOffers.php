<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ModuleFiles;
use Yef\Domain\Module\Install\ModuleInfo;
use Yef\Domain\Module\Install\ModuleInspector;
use Yef\Domain\Module\Install\StagedOffer;
use Yef\Domain\Module\Install\StagingArea;

use function str_starts_with;

/**
 * Judges a downloaded module: can it be installed, or can it replace what is installed?
 */
final readonly class ModuleOffers
{
    private const string CORE_NAMESPACE = 'Yef\\';

    public function __construct(
        private ModuleFiles $files,
        private ModuleInspector $inspector,
        private InstalledModules $installed,
    ) {}

    public function build(StagingArea $area, StagedOffer $offer, ModuleInfo $info): ModuleOffer
    {
        $problems = [];
        if (!$info->fitsCore) {
            $problems[] = OfferProblem::of(OfferProblemKind::IncompatibleCore, $info->coreConstraint);
        }

        if (!$offer->replaces instanceof \Yef\Domain\Module\ModuleName) {
            if ($this->files->isInstalled($info->name) || $this->installed->find($info->name) instanceof \Yef\Domain\Module\Install\InstalledModule) {
                $problems[] = OfferProblem::of(OfferProblemKind::AlreadyInstalled);
            }
        } elseif (!$offer->replaces->equals($info->name)) {
            $problems[] = OfferProblem::of(OfferProblemKind::NameChanged, $info->name->value);
        }

        $clash = $this->namespaceClash($info);
        if ($clash !== null) {
            $problems[] = OfferProblem::of(OfferProblemKind::NamespaceTaken, $clash);
        }

        if (!$this->files->canChange()) {
            $problems[] = OfferProblem::of(OfferProblemKind::FolderNotWritable);
        }

        return new ModuleOffer(
            $area->token,
            $offer->release,
            $info,
            $offer->replaces instanceof \Yef\Domain\Module\ModuleName ? $this->installed->find($offer->replaces) : null,
            $problems,
        );
    }

    /**
     * @return ?string What the namespace of the module overlaps with: "core" or the name of another module.
     */
    private function namespaceClash(ModuleInfo $info): ?string
    {
        if (str_starts_with($info->namespace, self::CORE_NAMESPACE)) {
            return 'core';
        }

        foreach ($this->inspector->installed() as $other) {
            if ($other->name->equals($info->name)) {
                continue;
            }

            if (str_starts_with($info->namespace, $other->namespace) || str_starts_with($other->namespace, $info->namespace)) {
                return $other->name->value;
            }
        }

        return null;
    }
}
