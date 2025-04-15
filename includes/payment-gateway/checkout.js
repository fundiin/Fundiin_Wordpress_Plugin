const fundiinSettings = window.wc.wcSettings.getSetting('fundiin_gateway_data', {});
const fundiinLabel = window.wp.htmlEntities.decodeEntities(fundiinSettings.title) || window.wp.i18n.__('Fundiin Gateway', 'fundiin_gateway');

const FundiinContent = () => {
    return window.wp.htmlEntities.decodeEntities(fundiinSettings.description || '');
};

window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: 'fundiin_gateway',
    label: fundiinLabel,
    content: Object(window.wp.element.createElement)(FundiinContent, null),
    edit: Object(window.wp.element.createElement)(FundiinContent, null),
    canMakePayment: () => true,
    ariaLabel: fundiinLabel,
    supports: {
        features: fundiinSettings.supports,
    },
});