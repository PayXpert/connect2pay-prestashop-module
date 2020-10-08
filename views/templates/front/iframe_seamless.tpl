<div id="payment-container">
	<script type="application/json">
	    {
	      "onPaymentResult": "onPaymentResult"
	    }
	</script>
</div>
{literal}
	<script type="text/javascript">
	    function onPaymentResult(response) {
	    	if (response.statusCode == 200) {
          if (response.transaction.resultCode == 000) {
            setTimeout(function () {
              parent.location = "{/literal}{$redirectUrl}{literal}";
            }, 1500);
          }
	    	}
	    }
	</script>
{/literal}
<script async="true" src="https://connect2.payxpert.com/payment/{$customerToken|escape:'htmlall':'UTF-8'}/connect2pay-seamless-v1.2.0.js" data-mount-in="#payment-container" integrity="sha384-1SohwdQilaSZ8SZuQfXaklvSu5aJCFOnw+430nrt43RRCAhRyxulOEKFIxuu1hCF" crossorigin="anonymous"></script>