<?php

namespace EdgarEz\SiteBuilderBundle\Controller;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EdgarEz\SiteBuilderBundle\Command\TaskCommand;
use EdgarEz\SiteBuilderBundle\Data\Install\InstallData;
use EdgarEz\SiteBuilderBundle\Data\Mapper\InstallMapper;
use EdgarEz\SiteBuilderBundle\Entity\SiteBuilderTask;
use EdgarEz\SiteBuilderBundle\Form\ActionDispatcher\InstallDispatcher;
use EdgarEz\SiteBuilderBundle\Form\Type\InstallType;
use EdgarEz\SiteBuilderBundle\Values\Content\Install;
use eZ\Publish\Core\MVC\Symfony\Security\User;
use EzSystems\PlatformUIBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class InstallController extends Controller
{
    /** @var InstallDispatcher $actionDispatcher */
    protected $actionDispatcher;

    /** @var InstallData $data */
    protected $data;

    public function __construct(InstallDispatcher $actionDispatcher)
    {
        $this->actionDispatcher = $actionDispatcher;
    }

    public function installAction(Request $request)
    {
        $actionUrl = $this->generateUrl('edgarezsb_sb', ['tabItem' => 'dashboard']);
        $form = $this->getForm($request);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->actionDispatcher->dispatchFormAction(
                $form,
                $this->data,
                $form->getClickedButton() ? $form->getClickedButton()->getName() : null,
                array(
                    'vendorName' => $this->data->vendorName,
                    'contentLocationID' => $this->data->contentLocationID,
                    'mediaLocationID' => $this->data->mediaLocationID,
                    'userLocationID' => $this->data->userLocationID,
                )
            );

            if ($response = $this->actionDispatcher->getResponse()) {
                return $response;
            }

            $this->initTask($form);

            foreach ($form->getErrors(true) as $error) {
                $this->notifyErrorPlural(
                    $error->getMessageTemplate(),
                    $error->getMessagePluralization(),
                    $error->getMessageParameters(),
                    'edgarezsb_form_install'
                );
            }

            return $this->redirectAfterFormPost($actionUrl);
        }

        return $this->render('EdgarEzSiteBuilderBundle:sb:tab/install.html.twig', [
            'params' => array(
                'installForm' => $form->createView(),
            )
        ]);
    }

    protected function getForm(Request $request)
    {
        $install = new Install([
            'vendorName' => 'Foo',
            'contentLocationID' => 0,
            'mediaLocationID' => 0,
            'userLocationID' => 0
        ]);
        $this->data = (new InstallMapper())->mapToFormData($install);

        return $this->createForm(new InstallType(), $this->data);
    }

    protected function initTask(Form $form)
    {
        /** @var InstallData $data */
        $data = $form->getData();

        $action = array(
            'service'    => 'project',
            'command'    => 'install',
            'parameters' => array(
                'vendorName'        => $data->vendorName,
                'contentLocationID' => $data->contentLocationID,
                'mediaLocationID'   => $data->mediaLocationID,
                'userLocationID'    => $data->userLocationID,
            )
        );

        /** @var Registry $dcotrineRegistry */
        $doctrineRegistry = $this->get('doctrine');
        $doctrineManager = $doctrineRegistry->getManager();

        $task = new SiteBuilderTask();
        $this->submitTask($doctrineManager, $task, $action);
    }

    protected function submitTask(EntityManager $doctrineManager, SiteBuilderTask $task, array $action)
    {
        try {
            $task->setAction($action);
            $task->setStatus(TaskCommand::STATUS_SUBMITTED);
            $task->setPostedAt(new \DateTime());
        } catch (\Exception $e) {
            $task->setLogs('Fail to initialize task');
            $task->setStatus(TaskCommand::STATUS_FAIL);
        } finally {
            /** @var User $user */
            $user = $this->getUser();
            $task->setUserID($user->getAPIUser()->getUserId());

            $doctrineManager->persist($task);
            $doctrineManager->flush();
        }
    }
}
