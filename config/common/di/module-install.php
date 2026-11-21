<?php

declare(strict_types=1);

use Yef\Domain\Module\CoreVersion;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ModuleArchive;
use Yef\Domain\Module\Install\ModuleFiles;
use Yef\Domain\Module\Install\ModuleInspector;
use Yef\Domain\Module\Install\ModuleSource;
use Yef\Module\ManifestModuleInspector;
use Yef\Module\ManifestReader;
use Yef\Platform\GitHubModuleSource;
use Yef\Platform\Http\CurlTransport;
use Yef\Platform\Http\HttpFetcher;
use Yef\Platform\Http\HttpTransport;
use Yef\Platform\Http\StreamTransport;
use Yef\Platform\ZipModuleArchive;
use Yef\Storage\FileInstalledModules;
use Yef\Storage\FileModuleFiles;
use Yiisoft\Aliases\Aliases;

/** @var array $params */

$modules = $params['yef.modules'];

return [
    // curl when the host has it, PHP streams otherwise.
    HttpTransport::class => static fn(): HttpTransport => extension_loaded('curl')
        ? new CurlTransport($modules['install']['timeoutSeconds'])
        : new StreamTransport($modules['install']['timeoutSeconds']),
    HttpFetcher::class => static fn(HttpTransport $transport): HttpFetcher => new HttpFetcher(
        $transport,
        $modules['github']['hosts'],
        $modules['github']['allowHttp'],
    ),
    ModuleSource::class => static fn(HttpFetcher $http): GitHubModuleSource => new GitHubModuleSource(
        $http,
        $modules['github']['api'],
        $modules['install']['maxArchiveBytes'],
    ),
    ModuleArchive::class => static fn(): ZipModuleArchive => new ZipModuleArchive(
        $modules['install']['maxFiles'],
        $modules['install']['maxUnpackedBytes'],
    ),
    ModuleInspector::class => static fn(Aliases $aliases): ManifestModuleInspector => new ManifestModuleInspector(
        new ManifestReader(),
        $aliases->get('@modules'),
        CoreVersion::CURRENT,
    ),
    ModuleFiles::class => static fn(Aliases $aliases): FileModuleFiles => new FileModuleFiles(
        $aliases->get('@modules'),
        $aliases->get('@runtime') . '/modules-incoming',
        $modules['install']['stagingLifetime'],
    ),
    InstalledModules::class => static fn(Aliases $aliases): FileInstalledModules => new FileInstalledModules(
        $aliases->get('@data') . '/modules.lock.json',
        $aliases->get('@runtime') . '/storage/modules-installed.lock',
    ),
];
