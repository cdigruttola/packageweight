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
use PrestaShop\PrestaShop\Core\Form\Handler;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PackageWeightController extends PrestaShopAdminController
{

    public function index(
        #[Autowire(service: 'cdigruttola.packageweight.form.configuration_type.form_handler')]
        Handler $configurationFormHandler,
    ): Response {
        $configurationForm = $configurationFormHandler->getForm();

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
    public function saveConfiguration(
        Request $request,
        #[Autowire(service: 'cdigruttola.packageweight.form.configuration_type.form_handler')]
        Handler $configurationFormHandler,
    ): Response {
        $redirectResponse = $this->redirectToRoute('package_weight_controller');

        $form = $configurationFormHandler->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $redirectResponse;
        }

        if ($form->isValid()) {
            $data = $form->getData();
            $saveErrors = $configurationFormHandler->save($data);

            if (0 === count($saveErrors)) {
                $this->addFlash('success', $this->trans('Successful update.', [], 'Admin.Notifications.Success'));

                return $redirectResponse;
            }
        }

        $formErrors = [];

        foreach ($form->getErrors(true) as $error) {
            $formErrors[] = $error->getMessage();
        }

        $this->addFlashErrors($formErrors);

        return $redirectResponse;
    }

}
