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

namespace cdigruttola\Module\PackageWeight\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="cdigruttola\Module\PackageWeight\Repository\PackageRangeWeightRepository")
 *
 * @ORM\Table()
 */
class PackageRangeWeight
{
    /**
     * @var int
     *
     * @ORM\Id
     *
     * @ORM\Column(name="id_delivery", type="integer")
     */
    private $id;

    /**
     * @var float
     *
     * @ORM\Column(name="package_weight", type="decimal", precision=20, scale=6)
     */
    private $packageWeight;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getPackageWeight(): float
    {
        return (float) $this->packageWeight;
    }

    public function setPackageWeight(float $packageWeight): self
    {
        $this->packageWeight = $packageWeight;

        return $this;
    }
}
