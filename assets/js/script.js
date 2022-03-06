let form = jQuery('<form></form>')
/**
 * create a HTML from and insert it after order_details class 
 * This form must be created to replace it with CopyAndPay form 
 */
form.attr('action', dataObj.postbackURL)
    .addClass('paymentWidgets')
    .attr('data-brands', dataObj.payment_brands)
jQuery('.order_details').after(form)

/**
 * 
 * to check if current broswer support ApplePay 
 * @param {boolean} 
 * if @returns {false} attach error message Your Device Dose Not Support ApplePay
 */
if (!window.ApplePaySession && dataObj.payment_brands.includes('APPLEPAY')) {
    jQuery('.woocommerce-notices-wrapper')
    .append('<ul class="woocommerce-error" role="alert"><li>Your Device Dose Not Support ApplePay</li></ul>')
 }

 /**
  * initiate wpwlOptions 
  */
var wpwlOptions = {

    onReady: function () {

        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-qrcode').hide();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile').hide();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-group-paymentMode').hide();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-group-mobilePhone').show();
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile .wpwl-control-radio-mobile').attr('checked', true);
        jQuery('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile .wpwl-control-radio-mobile').trigger('click');

    },
    "style": dataObj.style, // <== this style comes from settings page of gateways
    "paymentTarget": "_top",
    "registrations": {
        "hideInitialPaymentForms": "true",
        "requireCvv": "true"
    }


}