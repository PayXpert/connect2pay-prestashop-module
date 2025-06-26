<link rel="stylesheet" href="{$module_dir}views/css/hook/display_admin_order.css">

<div id="payxpert-transactions" class="row">
    <div class="col-lg-12">
        <div class="card panel">
            <h3 class="card-header panel-heading">
                <img class="img payxpert-transactions-logo" alt="Brand Logo" src="{$logo_path}" />
            </h3>

            <div class="card-body">
                {foreach from=$payxpert_messages item=payxpert_message}
                    <div class="alert alert-{$payxpert_message.type}">
                        {$payxpert_message.msg}
                    </div>
                {/foreach}

                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-center">{l s='Transaction ID' mod='payxpert'}</th>
                            <th class="text-center">{l s='Referal Transaction' mod='payxpert'}</th>
                            <th>{l s='Created At' mod='payxpert'}</th>
                            <th>{l s='Operation' mod='payxpert'}</th>
                            <th class="text-right">{l s='Amount' mod='payxpert'}</th>
                            <th class="text-right">{l s='Refundable amount' mod='payxpert'}</th>
                            <th class="text-center">{l s='Currency' mod='payxpert'}</th>
                            <th class="text-center">{l s='Status' mod='payxpert'}</th>
                            <th class="text-center" title="{l s='Liability Shift' mod='payxpert'}">
                                {l s='LS*' mod='payxpert'}
                            </th>
                            <th class="text-center">{l s='Code' mod='payxpert'}</th>
                            <th>{l s='Message' mod='payxpert'}</th>
                            {if isset($capturable_transaction_ids) && $capturable_transaction_ids|count > 0}
                                <th>{l s='Actions' mod='admin'}</th>
                            {/if}
                        </tr>
                    </thead>
                    <tbody>
                        {if $transactions}
                            {foreach from=$transactions item=transaction}
                                <tr>
                                    <td class="text-center">{$transaction.transaction_id}</td>
                                    <td class="text-center">{$transaction.transaction_referal_id}</td>
                                    <td>{$transaction.date_add|date_format:"%d/%m/%Y %H:%M:%S"}</td>
                                    <td>{$transaction.operation}</td>
                                    <td class="text-right">
                                        {if $transaction.operation == 'refund'}-{/if}
                                        {$transaction.amount|number_format:2:",":" "}
                                    </td>
                                    <td class="text-right">
                                        {if isset($refundable_transactions[$transaction.transaction_id].refundable_amount)}
                                            {$refundable_transactions[$transaction.transaction_id].refundable_amount|number_format:2:",":" "}
                                        {else}
                                        {/if}
                                    </td>
                                    <td class="text-center">{$transaction.currency}</td>
                                    <td class="text-center">
                                        {if $transaction.result_code == $code_success}
                                            <span class="badge badge-success">✓</span>
                                        {else}
                                            <span class="badge badge-danger">⤬</span>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        {if $transaction.liability_shift == $liability_shift_ok}
                                            <span class="badge badge-success">✓</span>
                                        {else}
                                            <span class="badge badge-danger">⤬</span>
                                        {/if}
                                    </td>
                                    <td class="text-center">{$transaction.result_code}</td>
                                    <td>{$transaction.result_message}</td>
                                    {if isset($capturable_transaction_ids) && $capturable_transaction_ids|count > 0 && in_array($transaction.transaction_id, $capturable_transaction_ids)}
                                        <td>
                                            <form method="post">
                                                <button class="btn btn-primary" name="submitAddCapture" type="submit" title="{l s='Capture the transaction' mod='payxpert'}">
                                                    <span class="material-icons">payment</span>
                                                </button>
                                                <input type="hidden" name="id_payxpert_payment_transaction" value="{$transaction.id_payxpert_payment_transaction}"/>
                                            </form>
                                        </td>
                                    {/if}
                                </tr>
                            {/foreach}
                        {else}
                            <tr>
                                <td colspan="11" class="text-center">{l s='No transaction found.' mod='payxpert'}</td>
                            </tr>
                        {/if}
                    </tbody>
                </table>

                {if !$has_order_slips && !$is_refund}
                    <div class="alert alert-info">
                        {l s='Please proceed to refund the product on the order by partial refund before executing the PayXpert refund.' mod='payxpert'}
                    </div>
                {/if}

                {if $has_order_slips && isset($refundable_transactions) && $refundable_transactions|count > 0}
                    <div class="refund-form-container">
                        {if $transaction_refund_form}
                            {$transaction_refund_form}
                        {/if}
                    </div>
                {/if}


                {if $display_paybylink or $display_paybylink_x2 or $display_paybylink_x3 or $display_paybylink_x4}
                    <form method="post">
                        <input type="hidden" name="submitAddPaybylink" value="1"/>

                        {if $display_paybylink}
                            <button {if $paybylink_sent}disabled{/if} class="btn btn-info" name="payxpert_payment_method" type="submit">PayByLink</button>
                        {/if}

                        {if isset($instalment_x2)}
                            <button {if $paybylink_sent}disabled{/if} class="btn btn-info" name="payxpert_payment_method" type="submit" value={$instalment_x2}>PayByLink x2</button>
                        {/if}

                        {if isset($instalment_x3)}
                            <button {if $paybylink_sent}disabled{/if} class="btn btn-info" name="payxpert_payment_method" type="submit" value={$instalment_x3}>PayByLink x3</button>
                        {/if}

                        {if isset($instalment_x4)}
                            <button {if $paybylink_sent}disabled{/if} class="btn btn-info" name="payxpert_payment_method" type="submit" value={$instalment_x4}>PayByLink x4</button>
                        {/if}

                        {if $paybylink_sent}
                            {l s='An email has already been sent to the customer in the last 30 days' mod='payxpert'}
                        {/if}
                    </form>
                {/if}

                {if $iterationsLeft}
                    <div class="alert alert-info">
                        {l s='This order has %d installment(s) left' sprintf=[$iterationsLeft] mod='payxpert'}
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>
