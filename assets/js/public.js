(function ($) {
    'use strict';

    var gatewayId = 'cardlink_payment_gateway_woocommerce';
    var settings = window.cardlinkGateway || {};

    /**
     * Handle payment card radio selection - show/hide new card form.
     */
    function initPaymentCardSelection() {
        $(document.body).on('change', 'input[name="' + gatewayId + '-card"]', function () {
            var val = $(this).val();
            if (val === 'new') {
                $('.payment-cards-new-card').show();
            } else {
                $('.payment-cards-new-card').hide();
            }
        });
    }

    /**
     * Handle delete payment card button.
     */
    function initDeletePaymentCard() {
        $(document.body).on('click', '.payment-cards__field .remove', function (e) {
            e.preventDefault();

            var $link = $(this);
            var cardId = $link.data('card-id');
            var $container = $link.closest('.payment-cards__tokens');

            $container.addClass('loading');

            $.ajax({
                type: 'POST',
                url: settings.ajax_url,
                data: {
                    action: 'cardlink_delete_token',
                    security: settings.nonce,
                    card_id: cardId
                },
                success: function (response) {
                    if (response.status === 'success') {
                        $container.replaceWith(response.response);
                    }
                },
                complete: function () {
                    $container.removeClass('loading');
                }
            });
        });
    }

    /**
     * Poll for the gateway response (iframe mode).
     *
     * The server returns `success` with a redirect URL only after the payment
     * gateway has POSTed its response back to the site. Until then it returns
     * `pending` and we keep polling. We no longer pre-mark the order via a
     * second AJAX call: the previous design raced that "set" call against this
     * "check" call, and when the check won the empty flag was misread as
     * "payment done", redirecting the customer off the payment page before
     * they had paid.
     */
    function checkOrderStatus(orderId) {
        $.ajax({
            type: 'POST',
            url: settings.ajax_url,
            data: {
                action: 'cardlink_check_order_status',
                security: settings.nonce,
                order_id: orderId
            },
            success: function (response) {
                if (response.status === 'success' && response.response) {
                    window.top.location.href = response.response;
                } else {
                    setTimeout(function () {
                        checkOrderStatus(orderId);
                    }, 2000);
                }
            },
            error: function () {
                setTimeout(function () {
                    checkOrderStatus(orderId);
                }, 2000);
            }
        });
    }

    /**
     * Initialize iframe/modal payment flow.
     */
    function initModalPayment() {
        var $iframe = $('#payment_iframe');

        if (!$iframe.length || $('#payment_form').attr('target') !== 'payment_iframe') {
            return;
        }

        var orderId = $iframe.data('order-id');
        if (!orderId) {
            return;
        }

        // Delay to allow the form submission into the iframe to start, then
        // poll until the gateway response arrives.
        setTimeout(function () {
            checkOrderStatus(orderId);
        }, 2000);
    }

    $(document).ready(function () {
        initPaymentCardSelection();
        initDeletePaymentCard();
        initModalPayment();
    });

})(jQuery);
