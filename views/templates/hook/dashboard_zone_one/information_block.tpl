<section id="payxpert" class="panel widget">
	<div class="panel-heading">
		<i class="icon-time"></i> {l s='PayXpert Activity' mod='payxpert'}
		<span class="panel-heading-action">
			<a id="payxpert-refresh-dashboard" class="list-toolbar-btn" href="#" onclick="refreshDashboard('payxpert'); return false;" title="{l s='Refresh' d='Admin.Actions'}">
				<i class="process-icon-refresh"></i>
			</a>
		</span>
	</div>
    <section id="payxpert_dash_pending" class="loading">
		<header><i class="icon-time"></i> {l s='Currently pending' mod='payxpert'}</header>
		<ul class="data_list">
			<li>
				<span class="data_label"><a href="{$link->getAdminLink('AdminPayxpertSubscription')|escape:'html':'UTF-8'}">{l s='Installments' mod='payxpert'}</a></span>
				<span class="data_value size_l">
					<span id="payxpert_pending_installments"></span>
				</span>
			</li>
		</ul>
	</section>
	<section id="payxpert_dash_notifications" class="loading">
		<header><i class="icon-exclamation-sign"></i> {l s='Task Execution Monitor' mod='payxpert'}</header>
		<div class="data_list_vertical">
			<span id="payxpert_cron_logs"></span>
		</div>
	</section>
	<section id="payxpert_events">
		<div id="pxp-cron-launcher" style="margin-bottom: 20px;">
			<button id="pxp-run-installments-sync" class="btn btn-primary"></button>
		</div>
	</section>
</section>
