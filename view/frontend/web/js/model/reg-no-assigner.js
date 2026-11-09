define(['uiRegistry'], function (registry) {
    'use strict';

    var FIELD = 'checkout.steps.billing-step.payment.beforeMethods.envoice-reg-no';

    /**
     * Puts the BRN or IC on the payment payload, where the server plugin reads it.
     *
     * @param {Object} paymentData
     */
    return function (paymentData) {
        var field = registry.get(FIELD);

        if (!field || !paymentData) {
            return;
        }
        paymentData['extension_attributes'] = paymentData['extension_attributes'] || {};
        paymentData['extension_attributes']['envoice_buyer_reg_no'] = String(field.value() || '').trim();
    };
});
