(() => {
    const fundiinSettings = window.wc.wcSettings.getSetting('fundiin_gateway_data', {});
    const fundiinLabel = window.wp.htmlEntities.decodeEntities(fundiinSettings.title) || window.wp.i18n.__('Fundiin Gateway', 'fundiin_gateway');

    const FundiinContent = () => {
        return window.wp.element.createElement(
            'div',
            null,
            window.wp.element.createElement(
                'div',
                { id: 'script-checkout-container' },
                ''
            ),
            window.wp.element.createElement(
                'script',
                { type: 'text/javascript' },
                `
                    var fundiinCheckoutConfig = {
                        data: {
                            cartItems: ${fundiinSettings.data.cart_items},
                            referenceId: ${fundiinSettings.data.ref_id},
                            orderId: ${fundiinSettings.data.order_id},
                        },
                    };
                `
            ),
            window.wp.element.createElement(
                'script',
                {
                    type: 'application/javascript',
                    defer: true,
                    src: fundiinSettings.gatewayUrl,
                },
                ''
            ),
        );
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
})()
