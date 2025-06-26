<div>
    <table class="table table-bordered table-instalment mb-1">
        <thead>
            <tr>
                <th>{l s='Date' mod='payxpert'}</th>
                <th>{l s='Amount' mod='payxpert'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$schedule item=payment name=schedule_loop}
                <tr>
                    <td class="text-sm-center">
                        {if $smarty.foreach.schedule_loop.first}
                            {l s='To be paid immediately' mod='payxpert'}
                        {else}
                            {$payment.date}
                        {/if}
                    </td>
                    <td class="text-sm-center">{$payment.amountFormatted}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>

    {if isset($seamless) && $seamless}
        {include file="module:payxpert/views/templates/hook/payment_options/seamless.tpl"}
    {/if}
</div>
