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
     * Set redirection status for iframe mode.
     */
    function setRedirectionStatus(orderId) {
        $.ajax({
            type: 'POST',
            url: settings.ajax_url,
            data: {
                action: 'cardlink_set_redirection_status',
                security: settings.nonce,
                order_id: orderId
            }
        });
    }

    /**
     * Poll for order completion status (iframe mode).
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
                } else if (response.status === 'pending') {
                    setTimeout(function () {
                        checkOrderStatus(orderId);
                    }, 1000);
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
        $(document.body).on('load', '#payment_iframe', function () {
            var orderId = $(this).data('order-id');
            if (orderId) {
                setRedirectionStatus(orderId);
                checkOrderStatus(orderId);
            }
        });

        // Also check on form submit for iframe mode.
        if ($('#payment_iframe').length && $('#payment_form').attr('target') === 'payment_iframe') {
            var orderId = $('#payment_iframe').data('order-id');
            if (orderId) {
                // Delay to allow form submission.
                setTimeout(function () {
                    setRedirectionStatus(orderId);
                    checkOrderStatus(orderId);
                }, 2000);
            }
        }
    }

    $(document).ready(function () {
        initPaymentCardSelection();
        initDeletePaymentCard();
        initModalPayment();
    });

})(jQuery);
