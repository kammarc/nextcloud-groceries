<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div class="section" id="groceries-personal">
	<h2><?php p($l->t('Groceries on your phone')); ?></h2>
	<p><?php p($l->t('Open this address on your phone and add it to the home screen. You log in once; the app then keeps its own device password, which you can revoke any time under Security → Devices & sessions.')); ?></p>
	<p><a class="button" href="<?php p($_['appUrl']); ?>" target="_blank" rel="noopener"><?php p($_['appUrl']); ?></a></p>
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
