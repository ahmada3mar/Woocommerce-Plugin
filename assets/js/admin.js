jQuery(document).ready(function ($) {
  const {id,url,code_setting} = data;
  
  // Convert TeextArea for custom css field on admin settings
  wp.codeEditor.initialize($(`#woocommerce_${id}_custom_style`), code_setting);

  /**
   * Make Validation before submit settings via send request 
   * to prepare checkout to make sure the access token and entity id is valid 
   * @if return success response @then => resume submitting @else print error msg and preventDefault
   * 
   * @returns {boolean} if valid or not
   * 
   */
  const from = $("#mainform");

  from.on('submit', function () {

    $('.input-error-msg').remove();

    let valid = false

    const accesstoken = $(this).find(`input[name='woocommerce_${id}_accesstoken']`);
    const entityId = $(this).find(`input[name='woocommerce_${id}_entityId']`);

    const dataToSend = {
      entityId: entityId.val(),
    };

    $.ajax({
      type: 'POST',
      async: false,
      headers: {
        Authorization: `Bearer ${accesstoken.val()}`
      },
      url: url,
      data: dataToSend,
      dataType: "json",
      success: function () {
        valid = true
      },
      error: function (resultData) {
        valid = false;
        accesstoken.addClass('input-error').after(`<p class='input-error-msg'>${resultData.responseJSON.result.description}</p>`);
        entityId.addClass('input-error').after(`<p class='input-error-msg'>${resultData.responseJSON.result.description}</p>`);
        $('html, body').animate({
          scrollTop: accesstoken.offset().top - 200
        }, 200);
      },

    });
    return valid;
  })

});