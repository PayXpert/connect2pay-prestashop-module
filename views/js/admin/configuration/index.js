$(document).ready(function () {
    // === GESTION DU CHAMP "capture_mode" ===
    function toggleCaptureModeDesc() {
        var $select = $('select[name="capture_mode"]');
        var $desc = $select.parents('.form-group').find('.help-block');

        if ($select.val() == 1) {
            $desc.show();
        } else {
            $desc.hide();
        }
    }

    function initCaptureMode() {
        toggleCaptureModeDesc();
        $('select[name="capture_mode"]').on('change', toggleCaptureModeDesc);
    }

    // === GESTION DES CHAMPS D'ÉCHÉANCIER (instalments) ===
    function toggleInstalmentFields() {
        var anyActive = false;

        // Cacher tous les champs instalment_x*
        $('input[name^="instalment_x"]').closest('.form-group').hide();

        // Afficher ceux liés à une méthode de paiement activée
        $('input[name^="payment_method_"][value="1"]:checked').each(function () {
            var methodId = $(this).attr('name').split('payment_method_')[1];
            if (methodId == 1) {
                return true;
            }
            $('input[name="instalment_x' + methodId + '"]').closest('.form-group').show();
            anyActive = true;
        });

        // Afficher ou masquer le champ montant minimum
        $('input[name="instalment_payment_min_amount"]').closest('.panel').toggle(anyActive);
    }

    function initInstalments() {
        toggleInstalmentFields();
        $('input[name^="payment_method_"]').on('change', toggleInstalmentFields);
    }

    function initBanners() {
        $('.banner-img').on('click', function() {
            const url = $(this).data('target');
            window.open(url, '_blank');
        });
    }

    // === INITIALISATION GLOBALE ===
    initCaptureMode();
    initInstalments();
    initBanners();
});
