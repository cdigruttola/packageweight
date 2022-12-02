<?php
/**
 * 2007-2022 Carmine Di Gruttola
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
 * @copyright 2007-2022 Carmine Di Gruttola
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class Carrier extends CarrierCore
{
    protected static $package_weight_by_weight = [];

    public function getDeliveryPriceByWeight($total_weight, $id_zone)
    {
        $package_weight_module = Module::getInstanceByName('packageweight');
        if (isset($package_weight_module->active) && $package_weight_module->active) {
            $id_carrier = (int)$this->id;
            $total_weight = self::addPackingWeight($id_carrier, $total_weight);
        }
        return parent::getDeliveryPriceByWeight($total_weight, $id_zone);
    }

    public static function checkDeliveryPriceByWeight($id_carrier, $total_weight, $id_zone)
    {
        $package_weight_module = Module::getInstanceByName('packageweight');
        if (isset($package_weight_module->active) && $package_weight_module->active) {
            $total_weight = self::addPackingWeight($id_carrier, $total_weight);
        }
        return parent::checkDeliveryPriceByWeight($id_carrier, $total_weight, $id_zone);
    }

    /**
     * Adds package weight searching into range
     *
     * @param int $id_carrier Carrier ID
     * @param float $total_weight Total weight
     *
     * @return float
     */
    public static function addPackingWeight(int $id_carrier, float $total_weight): float
    {
        $cache_key = $id_carrier . '_package_weight_' . $total_weight;

        if (!isset(self::$package_weight_by_weight[$cache_key])) {
            $sql = 'SELECT w.`package_weight`
                    FROM `' . _DB_PREFIX_ . 'delivery` d
                    LEFT JOIN `' . _DB_PREFIX_ . 'range_weight` w ON (d.`id_range_weight` = w.`id_range_weight`)
                    WHERE ' . $total_weight . ' >= w.`delimiter1`
                        AND ' . $total_weight . ' < w.`delimiter2`
                        AND d.`id_carrier` = ' . $id_carrier . '
                        ' . Carrier::sqlDeliveryRangeShop('range_weight') . '
                    ORDER BY w.`delimiter1` ASC';
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
            if (!isset($result['package_weight'])) {
                self::$package_weight_by_weight[$cache_key] = self::getMaxPackageWeightByWeight($id_carrier);
            } else {
                self::$package_weight_by_weight[$cache_key] = $result['package_weight'];
            }
        }

        if (self::$package_weight_by_weight[$cache_key] > 0) {
            $total_weight += self::$package_weight_by_weight[$cache_key];
        }

        return $total_weight;
    }

    /**
     * Get maximum package weight when range weight is used.
     *
     * @param int $id_carrier Carrier ID
     *
     * @return false|string|null Maximum package weight
     */
    public static function getMaxPackageWeightByWeight(int $id_carrier)
    {
        $cache_id = 'Carrier::getMaxPackageWeightByWeight_' . $id_carrier;
        if (!Cache::isStored($cache_id)) {
            $sql = 'SELECT w.`package_weight`
                    FROM `' . _DB_PREFIX_ . 'delivery` d
                    INNER JOIN `' . _DB_PREFIX_ . 'range_weight` w ON d.`id_range_weight` = w.`id_range_weight`
                    WHERE d.`id_carrier` = ' . $id_carrier . '
                        ' . Carrier::sqlDeliveryRangeShop('range_weight') . '
                    ORDER BY w.`delimiter2` DESC';
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            Cache::store($cache_id, $result);

            return $result;
        }

        return Cache::retrieve($cache_id);
    }

}
