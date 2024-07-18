import React from 'react';
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useState } from 'react';

const settings = getSetting('cardlink_payment_gateway_block_data', {});

const defaultLabel = __(
    'Cardlink Payment Gateway',
    'cardlink-payment-gateway-block'
);

const label = decodeEntities(settings.title) || defaultLabel;

const Content = (props) => {

    const description = React.createElement( 'p', null, decodeEntities( settings.description || '' ) );
    let installmentsNumber = settings.installments || 1;

    const [installmentsValue, setInstallmentsValue] = useState('');
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;
    useEffect( () => {
        const unsubscribe = onPaymentSetup( async () => {
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        installmentsValue,
                    },
                },
            };
        } );
        return () => {
            unsubscribe();
        };
    }, [
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
        onPaymentSetup,
        installmentsValue
    ] );

    if (settings.installment_variations) {
        const totals = props.billing.cartTotal.value;
        const minorUnit = props.billing.currency.minorUnit;
        const totalPrice = totals / Math.pow(10, minorUnit);
        let maxInstallments = 1;
        for (const [amount, installments] of Object.entries(settings.installment_variations)) {
            if ( totalPrice >= amount ) {
                maxInstallments = installments;
            }
        }
        installmentsNumber = maxInstallments;
    }

    if (installmentsNumber <= 1) {
        return description;
    }

    const installmentOptions = [];
    for (let i = 1; i <= installmentsNumber; i++) {
        installmentOptions.push(
            React.createElement(
                'option',
                { key: i, value: i },
                i === 1 ? __('Without installments', 'cardlink-payment-gateway') : i
            )
        );
    }

    const handleInstallmentChange = (e) => {
        setInstallmentsValue(e.target.value);
    };
    const installments = React.createElement(
        'div',
        null,
        React.createElement('label', { htmlFor: 'payment-installments' }, `${__('Choose Installments', 'cardlink-payment-gateway')}: `),
        React.createElement(
            'select',
            { id: 'payment-installments', name: 'installments', onChange: handleInstallmentChange },
            installmentOptions
        )
    );

    return React.createElement( 'div', null, description, installments );
};

const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={label} />;
};

const Cardlink = {
    name: "cardlink_payment_gateway_woocommerce",
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
        showSavedCards: settings.tokenization ?? false,
        showSaveOption: settings.tokenization ?? false
    }
};

registerPaymentMethod(Cardlink);