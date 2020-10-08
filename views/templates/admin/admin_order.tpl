<div id="formAddPaymentPanel" class="panel">
    <div class="panel-heading"><i class="icon-money"></i> {l s='Refund' mod='payxpert'}</div>
    <form id="formAddPayment" method="post" action="index.php?controller=AdminOrders&amp;vieworder&amp;id_order=187&amp;token=09a2e52d59b1fff6ca8ba553bbe1354e">
        <div class="form-group">
            <div class="row">
                <label class="control-label col-lg-12">
                    {l s='Refund your customer on his card directly with Payxpert' mod='payxpert'}
                </label>
            </div>
            <div class="row">
                <label class="control-label col-lg-2">
                    <span class="title_box">{l s='Amount already Refunded:' mod='payxpert'}</span>
                </label>
                <label class="control-label col-lg-1">
                    <span class="title_box">{Tools::displayPrice($pxpTotalRefund, $pxpOrderCurrency)|escape:'htmlall':'UTF-8'}</span>
                </label>
            </div>
            <div class="row">
                <label class="control-label col-lg-2">
                    <span class="title_box">{l s='Amount still refundable:' mod='payxpert'}</span>
                </label>
                <label class="control-label col-lg-1">
                    <span class="title_box">{Tools::displayPrice($pxpRefundAvailable, $pxpOrderCurrency)|escape:'htmlall':'UTF-8'}</span>
                </label>
            </div>
        </div>
        {if $pxpRefundAvailable > 0}
            <input type="hidden" name="pxpOrder" value="{$pxpOrder->id|escape:'htmlall':'UTF-8'}">
            <div class="form-group">
                <div class="row">
                    <label class="control-label col-lg-1">
                        <span class="title_box">{l s='Amount' mod='payxpert'}</span>
                    </label>
                    <div class="col-lg-2">
                        <div class="input-group">
                            <input type="text" name="pxpRefundAmount">
                            <div class="input-group-addon">{$pxpCurrencySymbol|escape:'htmlall':'UTF-8'}</div>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="pxpRefund(this)" type="button">{l s='Refund' mod='payxpert'}</button>
                </div>
                <div class="row">
                    <div class="col-lg-1">&nbsp;</div>
                    <div class="col-lg-3">
                        <p>{l s='To refund 2$/2€, input 2/2.00/2,00' mod='payxpert'}</p>
                        <p>{l s='To refund 02 cents, input 0.02/0,02' mod='payxpert'}</p>
                        <p>{l s='To refund 20 cents, input 0.20/0,20' mod='payxpert'}</p>
                    </div>
                </div>
            </div>
        {/if}
    </form>
</div>
{literal}
<script type="text/javascript">
    function pxpRefund() {
        $.ajax({
            url: "{/literal}{$pxpRefundLink}{literal}&ajax=1&pxpOrder="+$("input[name='pxpOrder']").val()+"&pxpRefundAmount="+$("input[name='pxpRefundAmount']").val(),
            type: 'post',
            dataType: 'json',
            contentType: 'application/json',
            success: function (data) {
                alert(data.msg);
                if (data.success) {
                    location.reload();
                }
            }
        });
    }
</script>
{/literal}