define([
    'Magento_Ui/js/form/element/abstract',
    'Magento_Checkout/js/model/payment/additional-validators'
], function (Element, additionalValidators) {
    'use strict';

    return Element.extend({
        defaults: {
            template: 'ui/form/field',
            elementTmpl: 'ui/form/element/input',
            additionalClasses: 'envoice-reg-no',
            inputName: 'envoice_buyer_reg_no'
        },

        /** @inheritdoc */
        initialize: function () {
            var field = this;

            this._super();
            additionalValidators.registerValidator({
                validate: function () {
                    return field.validate().valid;
                }
            });

            return this;
        }
    });
});
