<div style="margin-bottom: 1.25rem;">
    <div class="payxpert-alert alert alert-info">
        {l s='You need to accept the CGV in order to process the payment' mod='payxpert'}
    </div>

    <div class="payxpert-seamless-container" style="display:none">
        <div id="payment-container-{$uniqueContainerId}">
            <script type="application/json">
            {
                "onPaymentResult":"onPaymentResult",
                "payButtonText": "{l s='Pay %s now!' sprintf=[$payButtonAmount] mod='payxpert'}",
                "enableApplePay":  {if $applepay}true{else}false{/if}
            }
            </script>
        </div>

        <script async="true" src="https://connect2.payxpert.com/payment/{$customerToken}/connect2pay-seamless-v1.5.0.js" data-mount-in="#payment-container-{$uniqueContainerId}"
        integrity="sha384-0IS2bunsGTTsco/UMCa56QRukMlq2pEcjUPMejy6WspCmLpGmsD3z0CmF5LQHF5X" crossorigin="anonymous"></script>

        <script type="text/javascript">
            var urlAjax = {$ajaxUrl|@json_encode nofilter};

            function onPaymentResult(response) {
                if (response.statusCode != 200) {
                    console.error("Payment failed");
                    return;
                }

                setTimeout(function() {
                    $.ajax({
                        url: urlAjax,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            transactionID: response.transaction.transactionID,
                            paymentID: response.transaction.paymentID
                        },
                        success: function (data) {
                            if (data.success) {
                                window.location.href = data.urlRedirect;
                            } else {
                                alert(data.message);
                            }
                        },
                        error: function () {
                            console.error("AJAX request failed.");
                        }
                    });
                }, 3000)
            }
        </script>
    </div>
</div>
