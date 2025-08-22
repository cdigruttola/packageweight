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

    public function isUsingNewTranslationSystem(): bool
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
            && $this->registerHook('actionAfterCreateCarrierFormHandler')
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

    public function hookDisplayAfterCarrier(array $params)
    {
        $cart = $params['cart'] ?? null;
        if (!$cart || !$cart->id_address_delivery || !$cart->id_customer) {
            return '';
        }

        $id_group = Customer::getDefaultGroupId((int) $cart->id_customer);
        $allowedGroups = json_decode(Configuration::get(PackageWeightConfigurationData::PACKAGE_WEIGHT_GROUPS), true) ?: [];

        if (!in_array($id_group, $allowedGroups)) {
            return '';
        }

        $idCarrier = $cart->id_carrier ?: preg_replace('/[^0-9]/', '', current($cart->getDeliveryOption(null, false, false)));

        $totalWeight = Carrier::addPackingWeight($idCarrier, $cart->getTotalWeight());
        $this->smarty->assign([
            'weight' => sprintf('%.3f %s', $totalWeight, Configuration::get('PS_WEIGHT_UNIT')),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/display-after-carrier.tpl');
    }

    public function hookDisplayBackOfficeHeader()
    {
        if ($this->active) {
            $this->context->controller->addJS($this->_path . 'views/js/costs-range.js');
            Media::addJsDef(['weight_unit' => Configuration::get('PS_WEIGHT_UNIT')]);
        }
    }

    public function hookActionCarrierFormBuilderModifier(array $params)
    {
        if (!$this->active) {
            return;
        }

        $params['form_builder']->get('shipping_settings')->add('ranges_costs', CollectionType::class, [
            'prototype_name' => '__zone__',
            'entry_type' => PackageWeightCostsZoneType::class,
            'label' => null,
            'allow_add' => true,
            'allow_delete' => true,
        ]);
    }

    public function hookActionCarrierFormDataProviderData(array $params)
    {
        if (!$this->active || $params['data']['shipping_settings']['shipping_method'] !== Carrier::SHIPPING_METHOD_WEIGHT) {
            return;
        }

        $this->hydratePackageWeights((int) $params['id'], $params['data']);
    }

    public function hookActionAfterCreateCarrierFormHandler(array $params)
    {
        if ($this->isCarrierWeightMethodInactive($params)) {
            return;
        }

        $this->persistPackageWeights((int) $params['id'], $params['form_data']);
    }

    public function hookActionAfterUpdateCarrierFormHandler(array $params)
    {
        if ($this->isCarrierWeightMethodInactive($params)) {
            return;
        }

        $carrierId = (int) $params['id'];
        $this->persistPackageWeights($carrierId, $params['form_data']);
        $this->cleanupObsoleteWeights($carrierId);
    }

    private function isCarrierWeightMethodInactive(array $params): bool
    {
        return !$this->active || $params['form_data']['shipping_settings']['shipping_method'] !== Carrier::SHIPPING_METHOD_WEIGHT;
    }

    private function hydratePackageWeights(int $carrierId, array &$data): void
    {
        if (empty($data['shipping_settings']['ranges_costs'])) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->get('doctrine.dbal.default_connection');
        /** @var PackageRangeWeightRepository $repository */
        $repository = $this->get('cdigruttola.module.packageweight.repository.package_weight');

        $rangesIds = [];
        foreach ($data['shipping_settings']['ranges_costs'] as &$zone) {
            if (!isset($zone['ranges'])) {
                continue;
            }

            foreach ($zone['ranges'] as &$range) {
                $rangeId = $this->getRangeId($connection, $carrierId, $range, $rangesIds);
                $deliveryId = $this->getDeliveryId($connection, $carrierId, $zone['zoneId'], $rangeId);

                if ($entity = $repository->find($deliveryId)) {
                    $range['package_weight'] = $entity->getPackageWeight();
                }
            }
        }
    }

    private function persistPackageWeights(int $carrierId, array $data): void
    {
        if (empty($data['shipping_settings']['ranges_costs'])) {
            return;
        }

        $connection = $this->get('doctrine.dbal.default_connection');
        /** @var PackageRangeWeightRepository $repository */
        $repository = $this->get('cdigruttola.module.packageweight.repository.package_weight');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->get('doctrine.orm.entity_manager');

        $rangesIds = [];
        foreach ($data['shipping_settings']['ranges_costs'] as $zone) {
            if (!isset($zone['ranges'])) {
                continue;
            }

            foreach ($zone['ranges'] as $range) {
                $rangeId = $this->getRangeId($connection, $carrierId, $range, $rangesIds);
                $deliveryId = $this->getDeliveryId($connection, $carrierId, $zone['zoneId'], $rangeId);

                $entity = $repository->find($deliveryId) ?? new PackageRangeWeight();
                $entity->setId($deliveryId);
                $entity->setPackageWeight($range['package_weight']);

                $entityManager->persist($entity);
                $entityManager->flush();
            }
        }
    }

    private function cleanupObsoleteWeights(int $carrierId): void
    {
        $connection = $this->get('doctrine.dbal.default_connection');
        $connection->createQueryBuilder()
            ->delete(_DB_PREFIX_ . 'package_range_weight')
            ->andWhere('id_delivery NOT IN (SELECT id_delivery FROM ' . _DB_PREFIX_ . 'delivery WHERE id_carrier = :carrierId)')
            ->setParameter('carrierId', $carrierId)
            ->executeQuery();
    }

    private function getRangeId(Connection $connection, int $carrierId, array $range, array &$rangesIds): int
    {
        $rangeKey = $range['range'];
        if (!isset($rangesIds[$rangeKey])) {
            $rangesIds[$rangeKey] = $connection->createQueryBuilder()
                ->select('rw.id_range_weight')
                ->from(_DB_PREFIX_ . 'range_weight', 'rw')
                ->andWhere('rw.id_carrier = :carrierId')
                ->andWhere('rw.delimiter1 = :from')
                ->andWhere('rw.delimiter2 = :to')
                ->setParameter('carrierId', $carrierId)
                ->setParameter('from', $range['from'])
                ->setParameter('to', $range['to'])
                ->executeQuery()
                ->fetchOne();
        }

        return (int) $rangesIds[$rangeKey];
    }

    private function getDeliveryId(Connection $connection, int $carrierId, int $zoneId, int $rangeId): int
    {
        return (int) $connection->createQueryBuilder()
            ->select('d.id_delivery')
            ->from(_DB_PREFIX_ . 'delivery', 'd')
            ->andWhere('d.id_carrier = :carrierId')
            ->andWhere('d.id_zone = :zoneId')
            ->andWhere('d.id_range_weight = :rangeId')
            ->setParameter('carrierId', $carrierId)
            ->setParameter('zoneId', $zoneId)
            ->setParameter('rangeId', $rangeId)
            ->executeQuery()
            ->fetchOne();
    }
}
