<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
use WC_Gateway_Transbank_Webpay_Plus_REST;
use WC_Order;

class ThankYouPageController
{
    public function show($orderId)
    {
        $woocommerceOrder = new WC_Order($orderId);
        $webpayPlusPaymentGateway = new WC_Gateway_Transbank_Webpay_Plus_REST();
        $transbankOneclickPaymentGateway = new WC_Gateway_Transbank_Oneclick_Mall_REST();
        if (!in_array($woocommerceOrder->get_payment_method(), [$webpayPlusPaymentGateway->id, $transbankOneclickPaymentGateway->id])) {
            return;
        }

        $webpayTransaction = TransbankWebpayOrders::getApprovedByOrderId($orderId);
        if ($webpayTransaction === null) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo.
            // if (SessionMessageHelper::exists()){
            //     SessionMessageHelper::printMessage();
            //     return;
            // }
            wc_print_notice('Transacción <strong>fallida</strong>. Puedes volver a intentar el pago', 'error');

            return;
        }

        if ($webpayTransaction->status !== TransbankWebpayOrders::STATUS_APPROVED) {
            return wp_redirect($woocommerceOrder->get_cancel_order_url());
        }

        // Transacción aprobada
        wc_print_notice(__('Transacción aprobada', 'transbank'), 'success');
        $finalResponse = json_decode($webpayTransaction->transbank_response);

        if ($webpayTransaction->product == TransbankWebpayOrders::PRODUCT_WEBPAY_ONECLICK) {
            $firstTransaction = $finalResponse->details[0] ?? null;
            $responseCode = $firstTransaction->responseCode ?? null;
            $status = $firstTransaction->status ?? null;
            $responseTitle = ($responseCode === 0 && $status === 'AUTHORIZED') ? 'Transacción Aprobada' : 'Transacción Rechazada';
            $dateAccepted = new DateTime($finalResponse->transactionDate ?? null);
            $paymentType = ResponseController::getHumanReadablePaymentType($firstTransaction->paymentTypeCode);
            $installmentType = ResponseController::getHumanReadableInstallemntsType($firstTransaction->paymentTypeCode);
            require __DIR__.'/../../views/order-summary-oneclick.php';
            return;
        }

        [$authorizationCode, $amount, $sharesNumber, $transactionResponse, $installmentType, $date_accepted, $sharesAmount, $paymentType] = (new ResponseController([]))->getTransactionDetails($finalResponse);
        require __DIR__.'/../../views/order-summary.php';

    }
}
