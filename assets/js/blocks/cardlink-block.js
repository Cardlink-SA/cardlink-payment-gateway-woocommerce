import { useState, useEffect } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

const settings = getSetting('cardlink_payment_gateway_block_data', {});
const title = decodeEntities(settings.title || '');
const description = decodeEntities(settings.description || '');
const tokenization = settings.tokenization || false;
const maxInstallments = settings.installments || 1;
const installmentVariations = settings.installment_variations || {};

const CardlinkLabel = () => {
    return <span>{title}</span>;
};

const CardlinkContent = (props) => {
    const { eventRegistration, emitResponse, billing } = props;
    const { onPaymentSetup } = eventRegistration;
    const [installments, setInstallments] = useState(1);

    // Calculate max installments based on cart total and variation rules.
    const cartTotal = parseFloat(billing?.cartTotal?.value || 0) / 100;

    let effectiveMax = maxInstallments;
    if (Object.keys(installmentVariations).length > 0) {
        const amounts = Object.keys(installmentVariations)
            .map(Number)
            .sort((a, b) => a - b);

        for (const amount of amounts) {
            if (cartTotal >= amount) {
                effectiveMax = installmentVariations[String(amount)];
            }
        }
    }

    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        installmentsvalue: String(installments),
                    },
                },
            };
        });
        return () => unsubscribe();
    }, [onPaymentSetup, emitResponse, installments]);

    const showInstallments = effectiveMax > 1;

    return (
        <div>
            {description && <p>{description}</p>}
            {showInstallments && (
                <div className="form-row">
                    <label htmlFor="cardlink-installments">
                        {__('Choose Installments', 'cardlink-payment-gateway')} *
                    </label>
                    <select
                        id="cardlink-installments"
                        value={installments}
                        onChange={(e) => setInstallments(parseInt(e.target.value, 10))}
                        className="input-select"
                    >
                        <option value="1">
                            {__('Without installments', 'cardlink-payment-gateway')}
                        </option>
                        {Array.from({ length: effectiveMax - 1 }, (_, i) => i + 2).map((n) => (
                            <option key={n} value={n}>{n}</option>
                        ))}
                    </select>
                </div>
            )}
        </div>
    );
};

registerPaymentMethod({
    name: 'cardlink_payment_gateway_woocommerce',
    label: <CardlinkLabel />,
    content: <CardlinkContent />,
    edit: <CardlinkContent />,
    canMakePayment: () => true,
    ariaLabel: title,
    supports: {
        features: settings.supports || ['products'],
        showSavedCards: tokenization,
        showSaveOption: tokenization,
    },
});
