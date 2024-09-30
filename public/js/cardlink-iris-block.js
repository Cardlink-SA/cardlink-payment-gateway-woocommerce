import React from 'react';
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useState } from 'react';

const settings = getSetting('cardlink_payment_gateway_iris_block_data', {});

const defaultLabel = __(
    'Cardlink Payment Gateway with IRIS',
    'cardlink-payment-gateway-iris-block'
);

const label = decodeEntities(settings.title) || defaultLabel;

const Content = (props) => {

    return  React.createElement( 'p', null, decodeEntities( settings.description || '' ) );
};

const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={label} />;
};

const Iris = {
    name: "cardlink_payment_gateway_woocommerce_iris",
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    }
};

registerPaymentMethod(Iris);