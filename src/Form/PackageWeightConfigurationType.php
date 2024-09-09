<?php
/**
 * Copyright since 2007 Carmine Di Gruttola
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

namespace cdigruttola\Module\PackageWeight\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Form\Admin\Type\Material\MaterialChoiceTableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class PackageWeightConfigurationType extends TranslatorAwareType
{
    /**
     * @var array
     */
    private $customerGroupChoices;

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     * @param array $customerGroupChoices
     */
    public function __construct(
        TranslatorInterface $translator,
        array $locales,
        array $customerGroupChoices,
    ) {
        parent::__construct($translator, $locales);

        $this->customerGroupChoices = $customerGroupChoices;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('package_weight_group_association', MaterialChoiceTableType::class, [
                'choices' => $this->customerGroupChoices,
                'label' => $this->trans('Choose the user group(s) who can view the cart weight', 'Modules.Packageweight.Main'),
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->trans('This field cannot be empty.', 'Admin.Notifications.Error'),
                    ]),
                ],
            ]);
    }
}
