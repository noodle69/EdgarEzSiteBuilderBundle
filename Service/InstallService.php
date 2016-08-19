<?php

namespace EdgarEz\SiteBuilderBundle\Service;

use EdgarEz\ToolsBundle\Service\Content;
use EdgarEz\ToolsBundle\Service\ContentType;
use EdgarEz\ToolsBundle\Service\ContentTypeGroup;
use EdgarEz\ToolsBundle\Service\Role;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\RoleService;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\API\Repository\Values\User\Limitation\LocationLimitation;
use eZ\Publish\Core\Repository\Values\User\Policy;
use eZ\Publish\Core\Repository\Values\User\PolicyDraft;
use eZ\Publish\Core\Repository\Values\User\PolicyUpdateStruct;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Yaml;

/**
 * Class InstallService
 * @package EdgarEz\SiteBuilderBundle\Service
 */
class InstallService
{
    /** @var Kernel $kernel symfony kernel interface */
    private $kernel;

    /** @var ContentTypeService $contentTypeService eZ ContentType Service */
    private $contentTypeService;

    /** @var ContentTypeGroup $contentTypeGroup EdgarEz content type group service */
    private $contentTypeGroup;

    /** @var RoleService eZ Role Service */
    private $roleService;

    /** @var LocationService $locationService eZ Location Service */
    private $locationService;

    /** @var UserService $userService User Service */
    private $userService;

    /** @var ContentType $contentType EdgarEz ContentType Service */
    private $contentType;

    /** @var Content $content EdgarEz Content Service */
    private $content;

    /** @var Role $role EdgarEz Role Service */
    private $role;

    /**
     * InstallService constructor.
     *
     * @param Kernel $kernel symfony kernel interface
     * @param ContentTypeService $contentTypeService eZ ContentType Service
     * @param RoleService $roleService eZ Role Service
     * @param LocationService $locationService eZ Location Service
     * @param UserService $userService eZ User Service
     * @param ContentTypeGroup $contentTypeGroup EdgarEz ContentTypeGroup service
     * @param ContentType $contentType EdgarEz ContentType Service
     * @param Content $content EdgarEz Content Service
     * @param Role $role EdgarEz Role Service
     */
    public function __construct(
        Kernel $kernel,
        ContentTypeService $contentTypeService,
        RoleService $roleService,
        LocationService $locationService,
        UserService $userService,
        ContentTypeGroup $contentTypeGroup,
        ContentType $contentType,
        Content $content,
        Role $role
    )
    {
        $this->kernel = $kernel;
        $this->contentTypeService = $contentTypeService;
        $this->roleService = $roleService;
        $this->locationService = $locationService;
        $this->userService = $userService;

        $this->contentTypeGroup = $contentTypeGroup;
        $this->contentType = $contentType;
        $this->content = $content;
        $this->role = $role;
    }

    /**
     * Create ContentType Group
     *
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup
     */
    public function createContentTypeGroup()
    {
        return $this->contentTypeGroup->add('SiteBuilder');
    }

    /**
     * Create Content Types
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $contentTypeGroup
     * @return array
     */
    public function createContentTypes(\eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $contentTypeGroup)
    {
        $contentTypes = array();
        $identifiers = array('customer', 'customersroot', 'model', 'modelsroot', 'user');

        foreach ($identifiers as $identifier) {
            $contentTypeDefinition = $this->kernel->locateResource('@EdgarEzSiteBuilderBundle/Resources/datas/contenttype_' . $identifier . '.yml');
            $contentTypeDefinition = Yaml::parse(file_get_contents($contentTypeDefinition));
            $contentTypeDefinition['contentTypeGroup'] = $contentTypeGroup;
            $this->contentType->add($contentTypeDefinition);
        }

        return $contentTypes;
    }

    /**
     * Create Media ContentTypes
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $contentTypeGroup
     * @return array
     */
    public function createMediaContentTypes(\eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $contentTypeGroup)
    {
        $contentTypes = array();
        $identifiers = array('customer', 'customersroot', 'model', 'modelsroot');

        foreach ($identifiers as $identifier) {
            $contentTypeDefinition = $this->kernel->locateResource('@EdgarEzSiteBuilderBundle/Resources/datas/mediacontenttype_' . $identifier . '.yml');
            $contentTypeDefinition = Yaml::parse(file_get_contents($contentTypeDefinition));
            $contentTypeDefinition['contentTypeGroup'] = $contentTypeGroup;
            $this->contentType->add($contentTypeDefinition);
        }

        return $contentTypes;
    }

    /**
     * Create contents
     *
     * @param $parentLocationID
     * @return array
     */
    public function createContents($parentLocationID)
    {
        $contents = array();
        $identifiers = array('customersroot', 'modelsroot');

        foreach ($identifiers as $identifier) {
            $contentDefinition = $this->kernel->locateResource('@EdgarEzSiteBuilderBundle/Resources/datas/content_' . $identifier . '.yml');
            $contentDefinition = Yaml::parse(file_get_contents($contentDefinition));
            $contentDefinition['parentLocationID'] = $parentLocationID;
            $contentAdded = $this->content->add($contentDefinition);
            $contents[] = $contentAdded;
        }

        $modelsLocationID = false;
        $customersLocationID = false;
        foreach ($contents as $content) {
            /** @var \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType */
            $contentType = $this->contentTypeService->loadContentType($content->contentInfo->contentTypeId);
            switch ($contentType->identifier) {
                case 'edgar_ez_sb_modelsroot':
                    $modelsLocationID = $content->contentInfo->mainLocationId;
                    break;
                case 'edgar_ez_sb_customersroot':
                    $customersLocationID = $content->contentInfo->mainLocationId;
                    break;
                default:
                    break;
            }
        }

        return array(
            'contents' => $contents,
            'modelsLocationID' => $modelsLocationID,
            'customersLocationID' => $customersLocationID
        );
    }

    /**
     * Create media contents
     *
     * @param $parentLocationID
     * @return array
     */
    public function createMediaContents($parentLocationID)
    {
        $contents = array();
        $identifiers = array('customersroot', 'modelsroot');

        foreach ($identifiers as $identifier) {
            $contentDefinition = $this->kernel->locateResource('@EdgarEzSiteBuilderBundle/Resources/datas/mediacontent_' . $identifier . '.yml');
            $contentDefinition = Yaml::parse(file_get_contents($contentDefinition));
            $contentDefinition['parentLocationID'] = $parentLocationID;
            $contentAdded = $this->content->add($contentDefinition);
            $contents[] = $contentAdded;
        }

        $mediaModelsLocationID = false;
        $mediaCustomersLocationID = false;
        foreach ($contents as $content) {
            /** @var \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType */
            $contentType = $this->contentTypeService->loadContentType($content->contentInfo->contentTypeId);
            switch ($contentType->identifier) {
                case 'edgar_ez_sb_mediamodelsroot':
                    $mediaModelsLocationID = $content->contentInfo->mainLocationId;
                    break;
                case 'edgar_ez_sb_mediacustomersroot':
                    $mediaCustomersLocationID = $content->contentInfo->mainLocationId;
                    break;
                default:
                    break;
            }
        }

        return array(
            'contents' => $contents,
            'modelsLocationID' => $mediaModelsLocationID,
            'customersLocationID' => $mediaCustomersLocationID
        );
    }

    /**
     * Create user groups
     *
     * @param $parentLocationID
     * @return array
     */
    public function createUserGroups($parentLocationID)
    {
        $userGroupDefinition = Yaml::parse(file_get_contents($this->kernel->locateResource('@EdgarEzSiteBuilderBundle/Resources/datas/usergrouproot.yml')));
        $userGroupDefinition['parentLocationID'] = $parentLocationID;
        /** @var \eZ\Publish\API\Repository\Values\Content\Content $userGroup */
        $userGroup = $this->content->add($userGroupDefinition);

        $contents = array();
        $userGroupParenttLocationID = $userGroup->contentInfo->mainLocationId;
        $identifiers = array('creator', 'editor');

        foreach ($identifiers as $identifier) {
            $userGroupDefinition = $this->kernel->locateResource('@EdgarEzSiteBuilderBundle/Resources/datas/usergroup_' . $identifier . '.yml');
            $userGroupDefinition = Yaml::parse(file_get_contents($userGroupDefinition));
            $userGroupDefinition['parentLocationID'] = $userGroupParenttLocationID;
            /** @var \eZ\Publish\Core\REST\Client\Values\Content\Content $contentAdded */
            $contentAdded = $this->content->add($userGroupDefinition);
            $contents[] = $contentAdded;
        }

        $userCreatorsLocationID = false;
        $userEditorsLocationID = false;
        foreach ($contents as $content) {
            /** @var \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType */
            $contentType = $this->contentTypeService->loadContentType($content->contentInfo->contentTypeId);
            switch ($contentType->identifier) {
                case 'user_group':
                    if ($content->contentInfo->name == 'Creators') {
                        $userCreatorsLocationID = $content->contentInfo->mainLocationId;
                    } else {
                        $userEditorsLocationID = $content->contentInfo->mainLocationId;
                    }
                    break;
                default:
                    break;
            }
        }

        return array(
            'userGroupParenttLocationID' => $userGroupParenttLocationID,
            'userCreatorsLocationID' => $userCreatorsLocationID,
            'userEditorsLocationID' => $userEditorsLocationID
        );
    }

    /**
     * Create Role
     *
     * @param $userGroupLocationID
     * @param array $locationIDs
     */
    public function createRole($userGroupLocationID, array $locationIDs)
    {
        /** @var \eZ\Publish\API\Repository\Values\User\Role $roleCreator */
        $role = $this->role->add('SiteBuilder');

        $userGroupLocation = $this->locationService->loadLocation($userGroupLocationID);
        $userGroup = $this->userService->loadUserGroup($userGroupLocation->contentId);

        $this->role->addPolicy($role->id, 'content', 'read');

        $roleDraft = $this->roleService->createRoleDraft($role);

        /** @var Policy[] $policies */
        $policies = $roleDraft->policies;
        foreach ($policies as $policy) {
            if ($policy->module == 'content' && $policy->function == 'read') {
                $locationLimitation = new LocationLimitation(
                    array(
                        'limitationValues' => $locationIDs
                    )
                );

                $policyUpdateStruct = new PolicyUpdateStruct();
                $policyUpdateStruct->addLimitation($locationLimitation);
                $policyDraft = new PolicyDraft(['innerPolicy' => new Policy(['id' => $policy->id, 'module' => 'content', 'function' => 'read', 'roleId' => $roleDraft->id])]);

                $this->roleService->updatePolicyByRoleDraft(
                    $roleDraft,
                    $policyDraft,
                    $policyUpdateStruct
                );
                $this->roleService->publishRoleDraft($roleDraft);
            }
        }

        $this->roleService->assignRoleToUserGroup(
            $role,
            $userGroup
        );
    }
}
