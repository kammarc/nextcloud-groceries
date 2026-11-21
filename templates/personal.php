<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div class="section" id="groceries-personal">
	<h2><?php p($l->t('Groceries on your phone')); ?></h2>
	<p><?php p($l->t('Open this address on your phone and add it to the home screen. You log in once; the app then keeps its own device password, which you can revoke any time under Security → Devices & sessions.')); ?></p>
	<div style="display: flex; flex-wrap: wrap; gap: 24px; align-items: center; margin: 12px 0;">
		<div id="groceries-qr" data-url="<?php p($_['appUrl']); ?>" data-label="<?php p($l->t('QR code with the address of the app')); ?>"
			style="width: 180px; padding: 8px; border-radius: 8px; background: #ffffff;"></div>
		<div>
			<p><?php p($l->t('Scan the code with the phone camera, or open this address on the phone:')); ?></p>
			<p><a class="button" href="<?php p($_['appUrl']); ?>" target="_blank" rel="noopener"><?php p($_['appUrl']); ?></a></p>
		</div>
	</div>
	<h3><?php p($l->t('iPhone / iPad (Safari)')); ?></h3>
	<ol style="list-style: decimal; margin-left: 2em;">
		<li><?php p($l->t('Open the address in Safari.')); ?></li>
		<li><?php p($l->t('Tap the Share button, then "Add to Home Screen".')); ?></li>
		<li><?php p($l->t('Open Groceries from the home screen and tap "Connect to Nextcloud".')); ?></li>
	</ol>
	<h3><?php p($l->t('Android (Chrome)')); ?></h3>
	<ol style="list-style: decimal; margin-left: 2em;">
		<li><?php p($l->t('Open the address in Chrome.')); ?></li>
		<li><?php p($l->t('Tap "Install app" in the app or the browser menu (⋮ → "Install app").')); ?></li>
	</ol>
</div>
