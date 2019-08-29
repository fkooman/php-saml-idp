<?php $this->layout('base'); ?>
<?php $this->start('main'); ?>
    <p>
        <?=$this->t('Personally identifying information will be sent to <strong title="%spEntityId%">%displayName%</strong> as part of the authentication.'); ?>
    </p>

	<details>
		<dl>
	<?php foreach ($attributeList as $attributeName => $attributeValueList): ?>
		    <dt>
	<?php if (array_key_exists($attributeName, $attributeMapping)): ?>
		        <span title="<?=$this->e($attributeName); ?>"><?=$this->e($attributeMapping[$attributeName]); ?></span>
	<?php else: ?>
	            <?=$this->e($attributeName); ?>
	<?php endif; ?>
	        </dt>
	        <dd>
	            <ul>
	<?php foreach ($attributeValueList as $attributeValue): ?>                    
	                <li><code><?=$this->e($attributeValue); ?></code></li>
	<?php endforeach; ?>
	            </ul>
	        </dd>
	<?php endforeach; ?>
		</dl>
	</details>
	
    <form class="consent" method="post" action="<?=$this->e($acsUrl); ?>">
        <input type="hidden" name="SAMLResponse" value="<?=$this->e($samlResponse); ?>">
        <?php if (null !== $relayState): ?>
            <input type="hidden" name="RelayState" value="<?=$this->e($relayState); ?>">
        <?php endif; ?>
        <input type="submit" value="<?=$this->t('Approve'); ?>">
    </form>
<?php $this->stop('main'); ?>
