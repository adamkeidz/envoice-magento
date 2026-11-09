define([
    'mage/utils/wrapper',
    'Envoice_Einvoice/js/model/reg-no-assigner'
], function (wrapper, assignRegNo) {
    'use strict';

    return function (setPaymentInformationAction) {
        return wrapper.wrap(setPaymentInformationAction, function (originalAction, messageContainer, paymentData, skipBilling) {
            assignRegNo(paymentData);

            return originalAction(messageContainer, paymentData, skipBilling);
        });
    };
});
