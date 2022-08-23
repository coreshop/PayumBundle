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

namespace CoreShop\Bundle\PayumBundle\Controller;

use CoreShop\Bundle\PayumBundle\Factory\ConfirmOrderFactoryInterface;
use CoreShop\Bundle\PayumBundle\Factory\GetStatusFactoryInterface;
use CoreShop\Bundle\PayumBundle\Factory\ResolveNextRouteFactoryInterface;
use CoreShop\Component\Core\Model\PaymentInterface;
use CoreShop\Component\Core\Model\PaymentProviderInterface;
use CoreShop\Component\Order\Model\OrderInterface;
use CoreShop\Component\Order\Payment\OrderPaymentProviderInterface;
use CoreShop\Component\Resource\Repository\PimcoreRepositoryInterface;
use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Payum;
use Payum\Core\Request\Generic;
use Payum\Core\Security\TokenInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentController extends AbstractController
{
    public function __construct(private OrderPaymentProviderInterface $orderPaymentProvider, private PimcoreRepositoryInterface $orderRepository, private GetStatusFactoryInterface $getStatusRequestFactory, private ResolveNextRouteFactoryInterface $resolveNextRouteRequestFactory, private ConfirmOrderFactoryInterface $confirmOrderFactory)
    {
    }

    public function prepareCaptureAction(Request $request): RedirectResponse
    {
        if ($request->attributes->has('token')) {
            $property = 'token';
            $identifier = $request->attributes->get('token');
        } else {
            $property = 'o_id';
            $identifier = $request->attributes->get('order');
        }

        /**
         * @var OrderInterface|null $order
         */
        $order = $this->orderRepository->findOneBy([$property => $identifier]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order with %s "%s" does not exist.', $property, $identifier));
        }

        /**
         * @var PaymentInterface $payment
         */
        $payment = $this->orderPaymentProvider->provideOrderPayment($order);

        $storage = $this->getPayum()->getStorage($payment);
        $storage->update($payment);

        $token = $this->provideTokenBasedOnPayment($payment);

        return $this->redirect($token->getTargetUrl());
    }

    public function afterCaptureAction(Request $request): RedirectResponse
    {
        $token = $this->getPayum()->getHttpRequestVerifier()->verify($request);

        /** @var Generic $status */
        $status = $this->getStatusRequestFactory->createNewWithModel($token);
        $this->getPayum()->getGateway($token->getGatewayName())->execute($status);

        $confirmOrderRequest = $this->confirmOrderFactory->createNewWithModel($status->getFirstModel());
        $this->getPayum()->getGateway($token->getGatewayName())->execute($confirmOrderRequest);

        $resolveNextRoute = $this->resolveNextRouteRequestFactory->createNewWithModel($status->getFirstModel());
        $this->getPayum()->getGateway($token->getGatewayName())->execute($resolveNextRoute);
        $this->getPayum()->getHttpRequestVerifier()->invalidate($token);

        return $this->redirectToRoute($resolveNextRoute->getRouteName(), $resolveNextRoute->getRouteParameters());
    }

    protected function getPayum(): Payum
    {
        return $this->get('payum');
    }

    private function provideTokenBasedOnPayment(PaymentInterface $payment): TokenInterface
    {
        /** @var PaymentProviderInterface $paymentMethod */
        $paymentMethod = $payment->getPaymentProvider();

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if (isset($gatewayConfig->getConfig()['use_authorize']) && $gatewayConfig->getConfig()['use_authorize'] === true) {
            $token = $this->getPayum()->getTokenFactory()->createAuthorizeToken(
                $gatewayConfig->getGatewayName(),
                $payment,
                'coreshop_payment_after',
                []
            );
        } else {
            $token = $this->getPayum()->getTokenFactory()->createCaptureToken(
                $gatewayConfig->getGatewayName(),
                $payment,
                'coreshop_payment_after',
                []
            );
        }

        return $token;
    }
}
