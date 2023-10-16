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
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrierWizardController extends AdminCarrierWizardControllerCore
{
    protected $package_weight_module;

    public function __construct()
    {
        parent::__construct();
        $this->package_weight_module = Module::getInstanceByName('packageweight');
    }

    public function processRanges($id_carrier)
    {
        if (isset($this->package_weight_module->active) && $this->package_weight_module->active) {
            if (!$this->access('edit') || !$this->access('add')) {
                $this->errors[] = $this->trans('You do not have permission to use this wizard.', [], 'Admin.Shipping.Notification');

                return false;
            }

            $carrier = new Carrier((int) $id_carrier);
            if (!Validate::isLoadedObject($carrier)) {
                return false;
            }

            $range_inf = Tools::getValue('range_inf');
            $range_sup = Tools::getValue('range_sup');
            $range_type = Tools::getValue('shipping_method');
            if ($range_type == Carrier::SHIPPING_METHOD_WEIGHT) {
                $package_weight = Tools::getValue('package_weight');
            }

            $fees = Tools::getValue('fees');

            $carrier->deleteDeliveryPrice($carrier->getRangeTable());
            if ($range_type != Carrier::SHIPPING_METHOD_FREE) {
                foreach ($range_inf as $key => $delimiter1) {
                    if (!isset($range_sup[$key])) {
                        continue;
                    }
                    $range = $carrier->getRangeObject((int) $range_type);
                    $range->id_carrier = (int) $carrier->id;
                    $range->delimiter1 = (float) $delimiter1;
                    $range->delimiter2 = (float) $range_sup[$key];
                    $range->save();
                    if (isset($package_weight)) {
                        $package = new PackageRangeWeight();
                        $package->id_range_weight = $range->id;
                        $package->package_weight = (float) $package_weight[$key];
                        $package->save();
                    }

                    if (!Validate::isLoadedObject($range)) {
                        return false;
                    }
                    $price_list = [];
                    if (is_array($fees) && count($fees)) {
                        foreach ($fees as $id_zone => $fee) {
                            $price_list[] = [
                                'id_range_price' => ($range_type == Carrier::SHIPPING_METHOD_PRICE ? (int) $range->id : null),
                                'id_range_weight' => ($range_type == Carrier::SHIPPING_METHOD_WEIGHT ? (int) $range->id : null),
                                'id_carrier' => (int) $carrier->id,
                                'id_zone' => (int) $id_zone,
                                'price' => isset($fee[$key]) ? (float) str_replace(',', '.', $fee[$key]) : 0,
                            ];
                        }
                    }

                    if (count($price_list) && !$carrier->addDeliveryPrice($price_list, true)) {
                        return false;
                    }
                }
            }

            return true;
        } else {
            return parent::processRanges($id_carrier);
        }
    }

    /**
     * @param Carrier $carrier
     * @param array $tpl_vars
     * @param array $fields_value
     */
    protected function getTplRangesVarsAndValues($carrier, &$tpl_vars, &$fields_value)
    {
        parent::getTplRangesVarsAndValues($carrier, $tpl_vars, $fields_value);
        if (isset($this->package_weight_module->active) && $this->package_weight_module->active) {
            $shipping_method = $carrier->getShippingMethod();
            if ($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT) {
                $range_table = $carrier->getRangeTable();
                $tmp_range = RangeWeight::getRanges((int) $carrier->id);
                $tpl_vars['package_weight_by_range'] = [];
                if ($shipping_method != Carrier::SHIPPING_METHOD_FREE) {
                    foreach ($tmp_range as $id => $range) {
                        $package = new PackageRangeWeight($range['id_' . $range_table]);
                        $tpl_vars['package_weight_by_range'][$range['id_' . $range_table]] = $package->package_weight;
                        unset($package);
                    }
                }
            }
        }
    }

    public function renderGenericForm($fields_form, $fields_value, $tpl_vars = [])
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = [];
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->tpl_vars = array_merge([
            'fields_value' => $fields_value,
            'languages' => $this->getLanguages(),
            'id_language' => $this->context->language->id,
        ], $tpl_vars);
        $helper->override_folder = 'carrier_wizard/';
        if (isset($this->package_weight_module->active) && $this->package_weight_module->active) {
            $helper->module = $this->package_weight_module;
        }

        return $helper->generateForm($fields_form);
    }

    public function ajaxProcessChangeRanges()
    {
        if (isset($this->package_weight_module->active) && $this->package_weight_module->active) {
            if ((Validate::isLoadedObject($this->object) && !$this->access('edit')) || !$this->access('add')) {
                $this->errors[] = $this->trans('You do not have permission to use this wizard.', [], 'Admin.Shipping.Notification');

                return;
            }
            if ((!(int) $shipping_method = Tools::getValue('shipping_method')) || !in_array($shipping_method, [Carrier::SHIPPING_METHOD_PRICE, Carrier::SHIPPING_METHOD_WEIGHT])) {
                return;
            }

            $carrier = $this->loadObject(true);
            $carrier->shipping_method = $shipping_method;

            $tpl_vars = [];
            $fields_value = $this->getStepThreeFieldsValues($carrier);
            $this->getTplRangesVarsAndValues($carrier, $tpl_vars, $fields_value);
            $template = $this->context->smarty->createTemplate('module:packageweight/views/templates/admin/_configure/carrier_wizard/helpers/form/form_ranges.tpl');
            $template->assign($tpl_vars);
            $template->assign('change_ranges', 1);

            $template->assign('fields_value', $fields_value);
            $template->assign('input', ['type' => 'zone', 'name' => 'zones']);

            $currency = $this->getActualCurrency();

            $template->assign('currency_sign', $currency->sign);
            $template->assign('PS_WEIGHT_UNIT', Configuration::get('PS_WEIGHT_UNIT'));

            exit($template->fetch());
        } else {
            parent::ajaxProcessChangeRanges();
        }
    }

    public function setMedia($isNewTheme = false)
    {
        if (isset($this->package_weight_module->active) && $this->package_weight_module->active) {
            parent::setMedia($isNewTheme);
            $this->addJs(_MODULE_DIR_ . 'packageweight/views/js/back.js');
        }
    }
}
