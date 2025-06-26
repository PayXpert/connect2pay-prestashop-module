{extends file="helpers/form/form.tpl"}

{block name="defaultForm"}
    <div class="col-md-10 col-md-offset-1">
        {include file='./header.tpl'}

        {$smarty.block.parent}

        {include file='./footer.tpl'}
    </div>
{/block}
