$(document).ready(function () {
    $(document).on('click', '.js-submit-support-demand', function () {
        $('form[id="configuration_form"]').submit();
    });

    $(document).on('submit', 'form[id="configuration_form"]', function (e) {
        e.preventDefault();

        if (this.checkValidity()) {
            let url = $(this).attr('action');
            let modalId = '#payxpert_modal_support'
            let modalLoading = $(modalId).find('.loading-screen');
            let modalBodyContent = $(modalId).find('.modal-body-content');
            let modalBodyResponseMessage = $(modalId).find('.response-message');
            let modalFooter = $(modalId).find('.modal-footer');

            modalBodyContent.hide();
            modalLoading.show();
            modalFooter.hide();

            $.ajax({
                url: url ,
                method: 'POST',
                data: $(this).serialize() + '&ajax=true&action=supportFormSubmit',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function (response) {
                    console.log(response);
                    setTimeout(function () {
                        modalLoading.hide();

                        if (response.success) {
                            modalBodyResponseMessage.html('<div class="alert alert-success">'+response.success+'</div>');
                        } else {
                            modalBodyResponseMessage.html('<div class="alert alert-danger">'+response.error+'</div>');
                        }

                        modalBodyResponseMessage.show();
                    }, 500);
                },
                error: function () {
                    setTimeout(function () {
                        modalLoading.hide();
                        modalBodyResponseMessage.html('<div class="alert alert-danger">Une erreur est survenue. Veuillez réessayer.</div>');
                        modalBodyResponseMessage.show();
                    }, 500);
                }
            });
        } else {
            this.reportValidity();
        }
    });

    $(document).on('hidden.bs.modal', '#payxpert_modal_support', function() {
        let modalLoading = $(this).find('.loading-screen');
        let modalBodyContent = $(this).find('.modal-body-content');
        let modalBodyResponseMessage = $(this).find('.response-message');
        let modalFooter = $(this).find('.modal-footer');

        modalLoading.hide();
        modalBodyContent.show();
        modalBodyResponseMessage.hide();
        modalFooter.show();
    });
});
