

let form =  jQuery('<form></form>')
form.attr('action', dataObj.postbackURL).attr('method', 'post').addClass('paymentWidgets').attr('data-brands' ,dataObj.payment_brands )
form.data('brands',dataObj.payment_brands)
console.log(dataObj.payment_brands)
jQuery('.woocommerce-notices-wrapper').append(form)
var wpwlOptions = {

    onReady: function() {

        if (dataObj.tokenization == 'enable') {

            const storeMsg = 'Store payment details?';
            if (dataObj.is_arabic) {
                storeMsg = ' هل تريد حفظ معلومات البطاقة ؟';
            }
            let savingCard = jQuery("<div></div>").addClass('customLabel hyperpay-customLabel')
            .text(storeMsg)
            .append(jQuery('<input type="checkbox" name="createRegistration" value="true">'))
    
            jQuery('.wpwl-button').before(savingCard);
        }


        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-qrcode').hide();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile').hide();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-group-paymentMode').hide();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-group-mobilePhone').show();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile .wpwl-control-radio-mobile').attr('checked', true);
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile .wpwl-control-radio-mobile').trigger('click');

    },
    "style": dataObj.style,
    "paymentTarget": "_top",
    "registrations": {
        "hideInitialPaymentForms": "true",
        "requireCvv": "true"
    }


}

