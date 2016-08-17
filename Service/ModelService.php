<?php

namespace EdgarEz\SiteBuilderBundle\Service;

use EdgarEz\ToolsBundle\Service\Content;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\RoleService;
use eZ\Publish\API\Repository\URLAliasService;
use eZ\Publish\API\Repository\Values\User\Limitation;
use eZ\Publish\API\Repository\Values\User\Policy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

class ModelService
{
    private $urlAliasService;
    private $locationService;
    private $roleService;
    private $content;

    private $role;
    private $siteaccessGroups;
    private $container;

    public function __construct(
        URLAliasService $urlAliasService,
        LocationService $locationService,
        RoleService $roleService,
        Content $content,
        \EdgarEz\ToolsBundle\Service\Role $role,
        array $siteaccessGroups,
        ContainerInterface $container
    )
    {
        $this->urlAliasService = $urlAliasService;
        $this->locationService = $locationService;
        $this->roleService = $roleService;
        $this->content = $content;
        $this->siteaccessGroups = $siteaccessGroups;
        $this->role = $role;
        $this->container = $container;
    }

    public function createModelContent($modelsLocationID, $modelName)
    {
        $returnValue = array();

        $contentDefinition = Yaml::parse(file_get_contents(__DIR__ . '/../Resources/datas/modelcontent.yml'));
        $contentDefinition['parentLocationID'] = $modelsLocationID;
        $contentDefinition['fields']['title']['value'] = $modelName;
        $contentAdded = $this->content->add($contentDefinition);

        $contentLocation = $this->locationService->loadLocation($contentAdded->contentInfo->mainLocationId);
        $contentPath = $this->urlAliasService->reverseLookup($contentLocation, $contentAdded->contentInfo->mainLanguageCode)->path;
        $returnValue['excludeUriPrefixes'] = trim($contentPath, '/') . '/';
        $returnValue['modelLocationID'] = $contentAdded->contentInfo->mainLocationId;

        return $returnValue;
    }

    public function createMediaModelContent($mediaModelsLocationID, $modelName)
    {
        $contentDefinition = Yaml::parse(file_get_contents(__DIR__ . '/../Resources/datas/mediamodelcontent.yml'));
        $contentDefinition['parentLocationID'] = $mediaModelsLocationID;
        $contentDefinition['fields']['title']['value'] = $modelName;
        $contentAdded = $this->content->add($contentDefinition);

        return $contentAdded->contentInfo->mainLocationId;
    }

    public function addSiteaccessLimitation($modelName)
    {
        $customers = array();

        $siteaccessGroups = array_keys($this->siteaccessGroups);
        foreach ($siteaccessGroups as $sg) {
            if (strpos($sg, 'edgarezsb_customer_') === 0) {
                $customers[] = substr($sg, strlen('edgarezsb_customer_'));
            }
        }

        $rolesCreator = array();
        foreach ($customers as $customer) {
            $parameter = 'edgarez_sb.customer.customers_' . $customer . '_sites.default.customer_user_creator_role_id';
            $roleCreatorID = $this->container->getParameter($parameter);
            $rolesCreator[] = $this->roleService->loadRole($roleCreatorID);
        }

        foreach ($rolesCreator as $roleCreator) {
            $siteaccess = array();

            /** @var Policy[] $policies */
            $policies = $roleCreator->getPolicies();
            foreach ($policies as $policy) {
                if ($policy->module == 'user' && $policy->function == 'login') {
                    $limitations = $policy->getLimitations();
                    foreach ($limitations as $limitation) {
                        if ($limitation->getIdentifier() == Limitation::SITEACCESS) {
                            $siteaccess = $limitation->limitationValues;
                            $siteaccess[] = sprintf('%u', crc32($modelName));
                        }
                    }
                }
            }
            $this->role->addSiteaccessLimitation($roleCreator, $siteaccess);
        }
    }
}
