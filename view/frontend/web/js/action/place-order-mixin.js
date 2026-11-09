define([
    'mage/utils/wrapper',
    'Envoice_Einvoice/js/model/reg-no-assigner'
], function (wrapper, assignRegNo) {
    'use strict';

    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
            assignRegNo(paymentData);

            return originalAction(paymentData, messageContainer);
        });
    };
});
