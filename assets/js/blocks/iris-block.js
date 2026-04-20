import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';

const settings = getSetting('cardlink_payment_gateway_iris_block_data', {});
const title = decodeEntities(settings.title || '');
const description = decodeEntities(settings.description || '');

const IrisLabel = () => {
    return <span>{title}</span>;
};

const IrisContent = () => {
    return (
        <div>
            {description && <p>{description}</p>}
        </div>
    );
};

registerPaymentMethod({
    name: 'cardlink_payment_gateway_woocommerce_iris',
    label: <IrisLabel />,
    content: <IrisContent />,
    edit: <IrisContent />,
    canMakePayment: () => true,
    ariaLabel: title,
    supports: {
        features: settings.supports || ['products'],
    },
});
