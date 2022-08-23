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

namespace CoreShop\Bundle\PayumBundle\Action\Offline;

use CoreShop\Bundle\PayumBundle\Request\ResolveNextRoute;
use CoreShop\Component\Core\Model\OrderInterface;
use CoreShop\Component\Core\Model\PaymentInterface;
use Payum\Core\Action\ActionInterface;

final class ResolveNextRouteAction implements ActionInterface
{
    /**
     * @inheritdoc
     *
     * @param ResolveNextRoute $request
     */
    public function execute($request): void
    {
        $payment = $request->getFirstModel();
        $order = $payment->getOrder();

        if ($order instanceof OrderInterface) {
            $request->setRouteName('coreshop_checkout_thank_you');
            $request->setRouteParameters([
                '_locale' => $order->getLocaleCode(),
                'token' => $order->getToken(),
            ]);
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof ResolveNextRoute &&
            $request->getFirstModel() instanceof PaymentInterface;
    }
}
