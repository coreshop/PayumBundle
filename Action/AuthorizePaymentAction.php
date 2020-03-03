<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2019 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Bundle\PayumBundle\Action;

use CoreShop\Bundle\PayumBundle\Request\GetStatus;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\Payment as PayumPayment;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Convert;
use CoreShop\Component\Core\Model\PaymentInterface as CoreShopPaymentInterface;

final class AuthorizePaymentAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

     /**
     * @var int
     */
    protected $decimalFactor;

    public function __construct(int $decimalFactor)
    {
        $this->decimalFactor = $decimalFactor;
    }

    /**
     * {@inheritdoc}
     *
     * @param Authorize $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);
        /** @var CoreShopPaymentInterface $payment */
        $payment = $request->getModel();

        $this->gateway->execute($status = new GetStatus($payment));
        if ($status->isNew()) {
            try {
                $this->gateway->execute($convert = new Convert($payment, 'array', $request->getToken()));
                $payment->setDetails($convert->getResult());
            } catch (RequestNotSupportedException $e) {
                $payumPayment = new PayumPayment();
                $payumPayment->setNumber($payment->getNumber());
                //Payum Payment works with ints with a precision of 2
                $payumPayment->setTotalAmount(100 * round($payment->getTotalAmount() / $this->decimalFactor, 2));
                $payumPayment->setCurrencyCode($payment->getCurrency()->getIsoCode());
                $payumPayment->setDescription($payment->getDescription());
                $payumPayment->setDetails($payment->getDetails());
                $this->gateway->execute($convert = new Convert($payumPayment, 'array', $request->getToken()));
                $payment->setDetails($convert->getResult());
            }
        }
        $details = ArrayObject::ensureArrayObject($payment->getDetails());

        try {
            $request->setModel($details);
            $this->gateway->execute($request);
        } finally {
            $payment->setDetails((array) $details);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request): bool
    {
        return
            $request instanceof Authorize &&
            $request->getModel() instanceof CoreShopPaymentInterface;
    }
}
