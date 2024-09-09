<?php
/**
 * Copyright since 2024 Carmine Di Gruttola
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    cdigruttola <c.digruttola@hotmail.it>
 * @copyright Copyright since 2007 Carmine Di Gruttola
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace cdigruttola\Module\PackageWeight\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

use cdigruttola\Module\VariableShipping\Entity\CartVariableShipping;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PackageWeightController extends FrameworkBundleAdminController
{
    /**
     * @var array
     */
    private $languages;

    public function __construct($languages)
    {
        parent::__construct();
        $this->languages = $languages;
    }

    public function index(): Response
    {
        $configurationForm = $this->get('cdigruttola.packageweight.form.configuration_type.form_handler')->getForm();

        return $this->render('@Modules/packageweight/views/templates/admin/index.html.twig', [
            'form' => $configurationForm->createView(),
            'help_link' => false,
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function saveConfiguration(Request $request): Response
    {
        $redirectResponse = $this->redirectToRoute('package_weight_controller');

        $form = $this->get('cdigruttola.packageweight.form.configuration_type.form_handler')->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $redirectResponse;
        }

        if ($form->isValid()) {
            $data = $form->getData();
            $saveErrors = $this->get('cdigruttola.packageweight.form.configuration_type.form_handler')->save($data);

            if (0 === count($saveErrors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $redirectResponse;
            }
        }

        $formErrors = [];

        foreach ($form->getErrors(true) as $error) {
            $formErrors[] = $error->getMessage();
        }

        $this->flashErrors($formErrors);

        return $redirectResponse;
    }

    public function customPrice(Request $request)
    {
        $cartId = (int) \Tools::getValue('cartId');
        $custom_price = (float) \Tools::getValue('custom_price');

        $entityManager = $this->get('doctrine.orm.entity_manager');

        /** @var CartVariableShipping $entity */
        $entity = $this->getDoctrine()
            ->getRepository(CartVariableShipping::class)
            ->find($cartId);

        if (!empty($entity)) {
            $entity->setCustomPrice($custom_price);
        } else {
            $entity = new CartVariableShipping();
            $entity->setCustomPrice($custom_price);
            $entity->setIdCart($cartId);
        }
        $entityManager->persist($entity);
        $entityManager->flush();

        return $this->json(['message' => $this->trans('Successful update.', 'Admin.Notifications.Success')], Response::HTTP_OK);
    }
}
