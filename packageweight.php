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

use cdigruttola\Module\PackageWeight\Adapter\Kpi\PackageWeightCartTotalKpi;
use cdigruttola\Module\PackageWeight\Adapter\Kpi\WeightCartTotalKpi;
use cdigruttola\Module\PackageWeight\Form\DataConfiguration\PackageWeightConfigurationData;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class Packageweight extends Module
{
    public function __construct()
    {
        $this->name = 'packageweight';
        $this->tab = 'shipping_logistics';
        $this->version = '1.1.1';
        $this->author = 'cdigruttola';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Package Weight', [], 'Modules.Packageweight.Main');
        $this->description = $this->trans('This module helps you to set a package weight for each range weight you set in carrier', [], 'Modules.Packageweight.Main');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Modules.Packageweight.Main');

        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        include dirname(__FILE__) . '/sql/install.php';

        return $this->registerHook('actionCartKpiRowModifier')
            && $this->registerHook('displayAfterCarrier')
            && parent::install();
    }

    public function uninstall()
    {
        include dirname(__FILE__) . '/sql/uninstall.php';

        return parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin(SymfonyContainer::getInstance()->get('router')->generate('package_weight_controller'));
    }

    public function hookActionCartKpiRowModifier($params)
    {
        $params['kpis'][] = new WeightCartTotalKpi();
        $params['kpis'][] = new PackageWeightCartTotalKpi();
    }

    /**
     * Inject some fixed metadata in the template used by all service point-based carriers.
     *
     * @param array $params
     *
     * @return false|string
     */
    public function hookDisplayAfterCarrier(array $params)
    {
        $cart = $params['cart'] ?? null;
        if ($cart === null || !$cart->id_address_delivery || !$cart->id_customer) {
            return '';
        }

        $id_group = Customer::getDefaultGroupId((int) $cart->id_customer);
        $group_ids = json_decode(Configuration::get(PackageWeightConfigurationData::PACKAGE_WEIGHT_GROUPS) ?? '', true);

        if (!in_array($id_group, $group_ids)) {
            return '';
        }

        $idCarrier = $cart->id_carrier;
        if (!$idCarrier) {
            $idCarrier = preg_replace('/[^0-9]/', '', current($cart->getDeliveryOption(null, false, false)));
        }

        $total_weight = Carrier::addPackingWeight($idCarrier, $cart->getTotalWeight());
        $this->smarty->assign([
            'weight' => sprintf('%.3f %s', $total_weight, Configuration::get('PS_WEIGHT_UNIT')),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/display-after-carrier.tpl');
    }
}
