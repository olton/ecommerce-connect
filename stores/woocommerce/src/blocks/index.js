(function() {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { getSetting } = window.wc.wcSettings;
    const { decodeEntities } = window.wp.htmlEntities;
    const { __ } = window.wp.i18n;
    const { createElement } = window.wp.element;

    const settings = getSetting('ecommerceconnect_data', null);
    const fallbackTitle = __('eCommerceConnect', 'woocommerce-gateway-ecommerceconnect');

    if (!settings) {
        return;
    }

    const title = decodeEntities(settings?.title || fallbackTitle);
    const description = decodeEntities(settings?.description || '');

    registerPaymentMethod({
        name: 'ecommerceconnect',
        label: createElement('img', { src: settings?.logo_url, alt: title }),
        ariaLabel: title,
        content: createElement(() => description),
        edit: createElement(() => description),
        canMakePayment: () => true,
        supports: { features: settings?.supports ?? [] },
    });
})();
