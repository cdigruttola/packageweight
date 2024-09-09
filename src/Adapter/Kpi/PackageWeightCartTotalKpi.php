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

namespace cdigruttola\Module\PackageWeight\Adapter\Kpi;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Kpi\KpiInterface;

/**
 * {@inheritdoc}
 */
final class PackageWeightCartTotalKpi implements KpiInterface
{
    /**
     * @var array
     */
    private $options;

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        $translator = \Context::getContext()->getTranslator();
        $cart = new \Cart($this->options['cart_id']);

        $helper = new \HelperKpi();
        $helper->id = 'box-kpi-cart';
        $helper->icon = 'scale';
        $helper->color = 'color1';
        $helper->title = $translator->trans('Total cart weight (package incl.)', [], 'Modules.Packageweight.Main');
        $helper->subtitle = $translator->trans('Cart #%ID%', ['%ID%' => $cart->id], 'Admin.Orderscustomers.Feature');

        $total_weight = \Carrier::addPackingWeight($cart->id_carrier, $cart->getTotalWeight());

        $helper->value = sprintf('%.3f %s', $total_weight, \Configuration::get('PS_WEIGHT_UNIT'));

        return $helper->generate();
    }

    /**
     * Sets options for Kpi
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }
}
