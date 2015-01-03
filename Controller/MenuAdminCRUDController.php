<?php

namespace Meisa\MenuBundle\Controller;


use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Symfony\Component\HttpFoundation\Request;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Admin\BaseFieldDescription;
use Sonata\AdminBundle\Util\AdminObjectAclData;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Meisa\MenuBundle\Entity\MenuItem;


class MenuAdminCRUDController extends CRUDController
{

    public function createAction()
    {
        // the key used to lookup the template
        $templateKey = 'edit';

        if (false === $this->admin->isGranted('CREATE')) {
            throw new AccessDeniedException();
        }

        $object = $this->admin->getNewInstance();

        $this->admin->setSubject($object);

        /** @var $form \Symfony\Component\Form\Form */
        $form = $this->admin->getForm();
        $form->setData($object);

        if ($this->getRestMethod() == 'POST') {
            $form->submit($this->get('request'));

            $isFormValid = $form->isValid();

            // persist if the form was valid and if in preview mode the preview was approved
            if ($isFormValid && (!$this->isInPreviewMode() || $this->isPreviewApproved())) {

                if (false === $this->admin->isGranted('CREATE', $object)) {
                    throw new AccessDeniedException();
                }

                try {
                    $object = $this->admin->create($object);

                    if ($this->isXmlHttpRequest()) {
                        return $this->renderJson(array(
                            'result' => 'ok',
                            'objectId' => $this->admin->getNormalizedIdentifier($object)
                        ));
                    }

                    $this->addFlash('sonata_flash_success', $this->admin->trans('flash_create_success', array('%name%' => $this->admin->toString($object)), 'SonataAdminBundle'));
                    //save new menu items
                    $this->saveMenuItems($object->getId(), $this->get('request'));
                    // redirect to edit mode
                    return $this->redirectTo($object);

                } catch (ModelManagerException $e) {

                    $isFormValid = false;
                }
            }

            // show an error message if the form failed validation
            if (!$isFormValid) {
                if (!$this->isXmlHttpRequest()) {
                    $this->addFlash('sonata_flash_error', $this->admin->trans('flash_create_error', array('%name%' => $this->admin->toString($object)), 'SonataAdminBundle'));
                }
            } elseif ($this->isPreviewRequested()) {
                // pick the preview template if the form was valid and preview was requested
                $templateKey = 'preview';
                $this->admin->getShow();
            }
        }

        $view = $form->createView();

        // set the theme for the current Admin Form
        $this->get('twig')->getExtension('form')->renderer->setTheme($view, $this->admin->getFormTheme());

        return $this->render($this->admin->getTemplate($templateKey), array(
            'action' => 'create',
            'form' => $view,
            'object' => $object,
        ));
    }


    public function editAction($id = null)
    {
        if ($this->getRestMethod() == 'POST') {
            $request = $this->container->get('request');
            $this->saveMenuItems($id, $request);
        }
        return parent::editAction($id); // TODO: Change the autogenerated stub
    }

    public function saveMenuItems($id, $request)
    {
        $parameters = $request->request->all();
        $links = array();
        foreach ($parameters as $paramKey => $param) {
            if (strpos($paramKey, 'new_link_') !== false) {
                $index = substr($paramKey, '9', strlen($paramKey));
                if ($request->get('is_link_selected_' . $index) == 'on') {
                    $links[] = [$param, $request->get('link_display_' . $index)];
                }
            }
        }
        foreach ($links as $link) {
            $menuItemObject = new MenuItem();
            $menuItemObject->setLink($link[0]);
            $menuItemObject->setTitle($link[1]);
            $menuItemObject->setMenu($this->admin->getObject($id));
            $em = $this->getDoctrine()->getManager();
            $em->persist($menuItemObject);
            $em->flush();
        }
    }

}