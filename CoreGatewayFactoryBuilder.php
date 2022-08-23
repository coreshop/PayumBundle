<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) CoreShop GmbH (https://www.coreshop.org)
 * @license    https://www.coreshop.org/license     GPLv3 and CCL
 */

declare(strict_types=1);

namespace CoreShop\Bundle\PayumBundle;

class CoreGatewayFactoryBuilder extends \Payum\Core\Bridge\Symfony\Builder\CoreGatewayFactoryBuilder
{
    public function build(array $defaultConfig): CoreGatewayFactory
    {
        $coreGatewayFactory = new CoreGatewayFactory($defaultConfig);
        $coreGatewayFactory->setContainer($this->container);

        return $coreGatewayFactory;
    }
}
