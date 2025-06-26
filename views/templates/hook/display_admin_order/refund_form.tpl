<form id="refund_form" class="defaultForm form-horizontal payxpert" action="" method="post" enctype="multipart/form-data" novalidate="">
    <div class="card panel" id="fieldset_0">
        <div class="card-header panel-heading">
            <i class="material-icons">credit_card</i> {l s="Refund a transaction" mod="payxpert"}
        </div>

        <div class="card-body">
            <div class="form-wrapper">
                <div class="form-group row">
                    <label class="control-label col-lg-3 required">{l s="Order slip" mod="payxpert"}</label>
                    <div class="col-lg-9">
                        <select name="order_slip_id" class="form-control" id="order_slip_id">
                        {foreach from=$orderSlipChoices item=orderSlip name=os}
                            <option value="{$orderSlip.id}" {if $smarty.foreach.os.first}selected{/if}>
                                {$orderSlip.name}
                            </option>
                        {/foreach}
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="control-label col-lg-3 required">{l s="Transaction" mod="payxpert"}</label>
                    <div class="col-lg-9">
                        <select name="transaction_id" class="form-control" id="transaction_id">
                        {foreach from=$transactionChoices item=transaction name=trans}
                            <option value="{$transaction.id}" {if $smarty.foreach.trans.first}selected{/if}>
                                {$transaction.name}
                            </option>
                        {/foreach}
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-footer d-flex justify-content-end panel-footer">
            <button type="submit" value="1" id="refund_form_submit_btn" name="submitAddRefund" class="btn btn-default">
                <i class="material-icons">credit_card</i>
                {l s='Refund' mod='payxpert'}
            </button>
        </div>
    </div>
</form>
