$(document).ready(function () {

    if (seamless) {
        var payment_confirmation_container = $('#payment-confirmation');
        var confirmation_elements = payment_confirmation_container.html();

        $(document).on('change', '.js-conditions-to-approve input', function() {
            if ($(this).prop('checked')) {
                $('.payxpert-alert').hide();
                $('.payxpert-seamless-container').show();
            } else {
                $('.payxpert-alert').show();
                $('.payxpert-seamless-container').hide();
            }
        });

        //** PS1.7.5.2 */
        $(document).on('change', '#conditions_to_approve\\[terms-and-conditions\\]', function() {
            if ($(this).prop('checked')) {
                $('.payxpert-alert').hide();
                $('.payxpert-seamless-container').show();
            } else {
                $('.payxpert-alert').show();
                $('.payxpert-seamless-container').hide();
            }
        });

        $(document).on('click', 'input[name=payment-option]', function () {
            let inputForm = $('#pay-with-' + $(this).attr('id') + '-form');

            if (
                $(this).data('moduleName') == 'payxpert' 
                && inputForm.length > 0 
                && inputForm.find('input[name=seamless]').val() == 1
            ) {
                payment_confirmation_container.children().remove();
            } else {
                payment_confirmation_container.html(confirmation_elements);
            }
        });
    }

    if (applepay) {
        if (typeof(sdpx) != 'undefined') {
            var resultat = sdpx.isApplePayAvailable();
            resultat.then((response) => {
                if (response.responseCode == "00") {
                    //! Value="1" refer to paymentMethod "CreditCard"
                    let paymentMethod = $('input[name="payxpert_payment_method"][value="1"]');

                    if (paymentMethod.length) {
                        let parentDiv = paymentMethod.closest('div');
                        let parentId = parentDiv.attr('id');

                        if (parentId) {
                            let match = parentId.match(/payment-option-\d+/);
                            if (match) {
                                let paymentOptionId = match[0];

                                $('<img>', {
                                    src: applepay_logo_url,
                                    alt: "Apple Pay Logo",
                                    class: "applepay-logo"
                                }).appendTo('label[for=' + paymentOptionId + ']');

                            } else {
                                console.error("No payment option ID found.");
                            }
                        } else {
                            console.error("No parent ID found.");
                        }
                    }
                }
            });
        }
    }

    if (oneclick) {
        $(document).on('change', '#payxpert_oneclick_register', function(e) {
            let isChecked = $(this).prop('checked') == true ? '1' : '0';
            $('input[name=payxpert_oneclick_register_card]').val(isChecked);
        })
    }
});
