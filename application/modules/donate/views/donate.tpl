<div id="donate">
	<div class="ucp_divider"></div>
	<section id="paypal_area">
		<form method="post" action="{$url}donate/wikipal" class="page_form">
			<input type="hidden" name="cmd" value="_xclick" />
			<input type="hidden" name="item_name" value="{lang("donation_for", "donate")} {$server_name}" />
			<input type="hidden" name="quantity" value="1" />
			<input type="hidden" name="custom" value="{$user_id}" />
			
			<select name="amount" style="direction:rtl; font-family:tahoma; font-size:12px;">
				{foreach from=$donate_wikipal.values item=value key=key}
					<option value="{$key}" id="option_{$key}">{$value} عدد {lang("dp", "donate")} - {$key} {$currency_sign}</option>
				{/foreach}
			</select>

			<input type='submit' style="direction:rtl; font-family:tahoma; font-size:12px;" value='پرداخت از طریق ویکی پال' />
			<div class="clear"></div>
		</form>
	</section>
</div>