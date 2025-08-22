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
use cdigruttola\Module\PackageWeight\Entity\PackageRangeWeight;
use cdigruttola\Module\PackageWeight\Form\DataConfiguration\PackageWeightConfigurationData;
use cdigruttola\Module\PackageWeight\Form\Type\PackageWeightCostsZoneType;
use cdigruttola\Module\PackageWeight\Repository\PackageRangeWeightRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

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
        $this->version = '2.0.0';
        $this->author = 'cdigruttola';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Package Weight', [], 'Modules.Packageweight.Main');
        $this->description = $this->trans('This module helps you to set a package weight for each range weight you set in carrier', [], 'Modules.Packageweight.Main');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Modules.Packageweight.Main');

        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        include dirname(__FILE__) . '/sql/install.php';

        return parent::install()
            && $this->registerHook('actionCartKpiRowModifier')
            && $this->registerHook('displayAfterCarrier')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionCarrierFormBuilderModifier')
            && $this->registerHook('actionCarrierFormDataProviderData')
            && $this->registerHook('actionAfterUpdateCarrierFormHandler');
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
        $params['kpis'][] = new WeightCartTotalKpi($this);
        $params['kpis'][] = new PackageWeightCartTotalKpi($this);
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
        $group_ids = json_decode(Configuration::get(PackageWeightConfigurationData::PACKAGE_WEIGHT_GROUPS), true) ?: [];

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

    public function hookDisplayBackOfficeHeader()
    {
        if ($this->active) {
            $this->context->controller->addJS($this->_path . 'views/js/costs-range.js');
            Media::addJsDef(
                [
                    'weight_unit' => Configuration::get('PS_WEIGHT_UNIT'),
                ]
            );
        }
    }
    public function hookActionCarrierFormBuilderModifier(array $params)
    {
        if (!$this->active) {
            return;
        }

        $formBuilder = $params['form_builder'];
        $shippingSettings = $formBuilder->get('shipping_settings');

        $shippingSettings->add('ranges_costs', CollectionType::class, [
            'prototype_name' => '__zone__',
            'entry_type' => PackageWeightCostsZoneType::class,
            'label' => null,
            'allow_add' => true,
            'allow_delete' => true,
        ]);
    }

    public function hookActionCarrierFormDataProviderData(array $params)
    {
        if (!$this->active) {
            return;
        }

        if ($params['data']['shipping_settings']['shipping_method'] !== Carrier::SHIPPING_METHOD_WEIGHT) {
            return;
        }

        $carrierId = (int) $params['id'];
        $data = &$params['data'];

        /** @var Connection $connection */
        $connection = $this->get('doctrine.dbal.default_connection');

        /** @var PackageRangeWeightRepository $repository */
        $repository = $this->get('cdigruttola.module.packageweight.repository.package_weight');

        if (!empty($data['shipping_settings']['ranges_costs'])) {
            $rangesIds = [];
            foreach ($data['shipping_settings']['ranges_costs'] as &$zone) {
                if (isset($zone['ranges'])) {
                    $idZone = $zone['zoneId'];
                    foreach ($zone['ranges'] as &$range) {
                        $rangeKey = $range['range'];
                        // Check if range already exist in database
                        if (!in_array($rangeKey, array_keys($rangesIds), true)) {
                            $qb = $connection->createQueryBuilder();
                            $qb
                                ->select('rw.id_range_weight')
                                ->from(_DB_PREFIX_ . 'range_weight', 'rw')
                                ->andWhere('rw.id_carrier = :carrierId')
                                ->andWhere('rw.delimiter1 = :delimiter1')
                                ->andWhere('rw.delimiter2 = :delimiter2')
                                ->setParameter('carrierId', $carrierId)
                                ->setParameter('delimiter1', $range['from'])
                                ->setParameter('delimiter2', $range['to']);

                            $rangeId = $qb->executeQuery()->fetchOne();
                            $rangesIds[$rangeKey] = $rangeId;
                        } else {
                            $rangeId = $rangesIds[$rangeKey];
                        }

                        $qb = $connection->createQueryBuilder();
                        $qb
                            ->select('d.id_delivery')
                            ->from(_DB_PREFIX_ . 'delivery', 'd')
                            ->andWhere('d.id_carrier = :carrierId')
                            ->andWhere('d.id_zone = :idZone')
                            ->andWhere('d.id_range_weight = :rangeId')
                            ->setParameter('carrierId', $carrierId)
                            ->setParameter('idZone', $idZone)
                            ->setParameter('rangeId', $rangeId);

                        $deliveryId = $qb->executeQuery()->fetchOne();

                        /** @var PackageRangeWeight|null $entity */
                        $entity = $repository->find($deliveryId);
                        if (null === $entity) {
                            continue;
                        }
                        $range['package_weight'] = $entity->getPackageWeight();
                    }
                }
            }
        }
    }

    public function hookActionAfterUpdateCarrierFormHandler(array $params)
    {
        if (!$this->active) {
            return;
        }

        if ($params['form_data']['shipping_settings']['shipping_method'] !== Carrier::SHIPPING_METHOD_WEIGHT) {
            return;
        }

        $carrierId = (int) $params['id'];
        $data = $params['form_data'];

        /** @var Connection $connection */
        $connection = $this->get('doctrine.dbal.default_connection');

        /** @var PackageRangeWeightRepository $repository */
        $repository = $this->get('cdigruttola.module.packageweight.repository.package_weight');

        /** @var EntityManagerInterface */
        $entityManager = $this->get('doctrine.orm.default_entity_manager');

        if (!empty($data['shipping_settings']['ranges_costs'])) {
            $rangesIds = [];
            foreach ($data['shipping_settings']['ranges_costs'] as $zone) {
                if (isset($zone['ranges'])) {
                    $idZone = $zone['zoneId'];
                    foreach ($zone['ranges'] as &$range) {
                        $rangeKey = $range['range'];
                        // Check if range already exist in database
                        if (!in_array($rangeKey, array_keys($rangesIds), true)) {
                            $qb = $connection->createQueryBuilder();
                            $qb
                                ->select('rw.id_range_weight')
                                ->from(_DB_PREFIX_ . 'range_weight', 'rw')
                                ->andWhere('rw.id_carrier = :carrierId')
                                ->andWhere('rw.delimiter1 = :delimiter1')
                                ->andWhere('rw.delimiter2 = :delimiter2')
                                ->setParameter('carrierId', $carrierId)
                                ->setParameter('delimiter1', $range['from'])
                                ->setParameter('delimiter2', $range['to']);

                            $rangeId = $qb->executeQuery()->fetchOne();
                            $rangesIds[$rangeKey] = $rangeId;
                        } else {
                            $rangeId = $rangesIds[$rangeKey];
                        }

                        $qb = $connection->createQueryBuilder();
                        $qb
                            ->select('d.id_delivery')
                            ->from(_DB_PREFIX_ . 'delivery', 'd')
                            ->andWhere('d.id_carrier = :carrierId')
                            ->andWhere('d.id_zone = :idZone')
                            ->andWhere('d.id_range_weight = :rangeId')
                            ->setParameter('carrierId', $carrierId)
                            ->setParameter('idZone', $idZone)
                            ->setParameter('rangeId', $rangeId);

                        $deliveryId = $qb->executeQuery()->fetchOne();

                        /** @var PackageRangeWeight|null $entity */
                        $entity = $repository->find($deliveryId);
                        if (null === $entity) {
                            $entity = new PackageRangeWeight();
                        }

                        $entity->setId($deliveryId);
                        $entity->setPackageWeight($range['package_weight']);

                        $entityManager->persist($entity);
                        $entityManager->flush();
                    }
                }
            }
        }

        $qb = $connection->createQueryBuilder();
        $qb->delete(_DB_PREFIX_ . 'package_range_weight')
            ->andWhere(
                'id_delivery NOT IN
                    (SELECT id_delivery FROM ' . _DB_PREFIX_ . 'delivery WHERE id_carrier = :carrierId)'
            )
            ->setParameter('carrierId', $carrierId)
            ->executeQuery();
    }
}
