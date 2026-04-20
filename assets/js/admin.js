(function ($) {
    'use strict';

    var strings = {
        noOfInstallments: window.crlGatewayStrings.noOfInstallments,
        totalOrderAmount: window.crlGatewayStrings.totalOrderAmount,
        addVariation: window.crlGatewayStrings.addVariation
    };

    function installmentsOutput(installments) {
        var installments = installments || 0;
        var options = '<option value="0">' + strings.noOfInstallments + '</option>';
        for (var i = 1; i <= 60; i++) {
            if (i === installments) {
                options += '<option value="' + i + '" selected>' + i + '</option>';
            } else {
                options += '<option value="' + i + '">' + i + '</option>';
            }
        }

        return '<select class="installments_variation_number">' + options + '</select>';
    }

    function rowOutput(amount, installments) {
        var amount = amount || '';
        var installments = installments || 0;
        var html = '<div class="installments_variation_row">'
            + '<input class="installments_variation_amount" type="number" value="' + amount + '" placeholder="' + strings.totalOrderAmount + '">'
            + installmentsOutput(installments);
        html += '<button type="button" class="button remove_installments_variation_row"><i class="dashicons dashicons-trash"></i></button></div>';

        return html;
    }

    function addRowButtonOutput() {
        return '<button type="button" class="button add_installments_variation_row">' + strings.addVariation + '</button>';
    }

    $(window).on('load', function () {
        var $iv_hidden = $('#woocommerce_cardlink_payment_gateway_woocommerce_installments_variation');
        buildInstallmentsVariationHTML($iv_hidden);
        onClickAddRow();
        onClickRemoveRow();
        updateHiddenField($iv_hidden);

        $("body").on("change", ".installments_variation_row", function (e) {
            updateHiddenField($iv_hidden);
        });
    });

    function buildInstallmentsVariationHTML($iv_hidden) {
        var html = '<div class="installments_variation_rows">';
        if ($iv_hidden.length && $iv_hidden.val() !== '') {
            var installments_split = $iv_hidden.val().split(',');

            $(installments_split).each(function (index, value) {
                var installment = value.split(':');
                var installment_amount = parseInt(installment[0]);
                var installment_number = parseInt(installment[1]);

                html += rowOutput(installment_amount, installment_number);
            });
        }
        html += '</div>';
        html += addRowButtonOutput();

        $iv_hidden.parent('fieldset').prepend(html);
    }

    function onClickAddRow() {
        var html_row = rowOutput();

        $("body").on("click", "button.add_installments_variation_row", function (e) {
            e.preventDefault();
            if ($('.installments_variation_row').length < 10) {
                $('.installments_variation_rows').append(html_row);
            }
        });
    }

    function onClickRemoveRow() {
        var $iv_hidden = $('#woocommerce_cardlink_payment_gateway_woocommerce_installments_variation');

        $("body").on("click", "button.remove_installments_variation_row", function (e) {
            e.preventDefault();
            $(e.currentTarget).parent().remove();
            updateHiddenField($iv_hidden);
        });
    }

    function updateHiddenField($iv_hidden) {
        var iv_hidden_new_val = '';
        $('.installments_variation_row').each(function (index, row) {
            var installment_amount = $(row).children('input').val();
            var installment_number = $(row).children('select').val();
            if (index === 0) {
                iv_hidden_new_val += installment_amount + ':' + installment_number;
            } else {
                iv_hidden_new_val += ', ' + installment_amount + ':' + installment_number;
            }
        });
        $iv_hidden.val(iv_hidden_new_val);
    }

})(jQuery);
