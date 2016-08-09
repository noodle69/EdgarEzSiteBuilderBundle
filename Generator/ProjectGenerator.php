<?php

namespace EdgarEz\SiteBuilderBundle\Generator;


use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class ProjectGenerator
 *
 * @package EdgarEz\SiteBuilderBundle\Generator
 */
class ProjectGenerator extends Generator
{
    const MAIN = 'Project';
    const BUNDLE = 'ProjectBundle';
    const MODELS = 'Models';

    /**
     * @var Filesystem $filesystem
     */
    private $filesystem;

    /**
     * @var Kernel $kernel
     */
    private $kernel;

    /**
     * ProjectGenerator constructor.
     *
     * @param Filesystem $filesystem
     * @param Kernel     $kernel
     */
    public function __construct(Filesystem $filesystem, Kernel $kernel)
    {
        $this->filesystem = $filesystem;
        $this->kernel = $kernel;
    }

    /**
     * Generate SiteBuilder project bundle
     *
     * @param int $modelsLocationID models content location ID
     * @param int $customersLocationID sites content location ID
     * @param int $userCreatorsLocationID user creators group content location ID
     * @param int $userEditorsLocationID user editors group content location ID
     * @param string $vendorName project vendor name
     * @param string $targetDir filesystem directory where bundle would be generated
     */
    public function generate(
        $modelsLocationID,
        $customersLocationID,
        $userCreatorsLocationID,
        $userEditorsLocationID,
        $vendorName,
        $targetDir
    )
    {
        $namespace = $vendorName . '\\' . self::BUNDLE;

        $dir = $targetDir . '/' . strtr($namespace, '\\', '/');
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" exists but is a file.', realpath($dir)));
            }
            $files = scandir($dir);
            if ($files != array('.', '..')) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not empty.', realpath($dir)));
            }
            if (!is_writable($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not writable.', realpath($dir)));
            }
        }

        $basename = substr($vendorName . self::BUNDLE, 0, -6);
        $parameters = array(
            'namespace' => $namespace,
            'bundle'    => self::BUNDLE,
            'format'    => 'yml',
            'bundle_basename' => $basename,
            'extension_alias' => Container::underscore($basename),
            'settings' => array(
                'modelsLocationID' => $modelsLocationID,
                'customersLocationID' => $customersLocationID,
                'userCreatorsLocationID' => $userCreatorsLocationID,
                'userEditorsLocationID' => $userEditorsLocationID,
                'namespace' => $namespace
            )
        );

        $this->setSkeletonDirs(array($this->kernel->locateResource('@EdgarEzSiteBuilderBundle/Resources/skeleton')));
        $this->renderFile('project/Bundle.php.twig', $dir . '/' . $basename . 'Bundle.php', $parameters);
        $this->renderFile('project/Extension.php.twig', $dir . '/DependencyInjection/' . $basename . 'Extension.php', $parameters);
        $this->renderFile('project/Configuration.php.twig', $dir . '/DependencyInjection/Configuration.php', $parameters);
        $this->renderFile('project/default_settings.yml.twig', $dir . '/Resources/config/default_settings.yml', $parameters);

        $this->filesystem->mkdir($targetDir . '/' . $vendorName . '/' . self::MODELS);
    }
}
