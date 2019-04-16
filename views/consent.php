<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>

    <p>
        <?=$this->t('The following attribute(s) will be released to <strong title="%spEntityId%">%displayName%</strong>.'); ?>
    </p>

    <table>
        <thead>
            <tr><th><?=$this->t('Attribute'); ?></th><th><?=$this->t('Value(s)'); ?></th></tr>
        </thead>
        <tbody>
<?php foreach ($attributeList as $attributeName => $attributeValueList): ?>
            <tr>
                <th>
<?php if (array_key_exists($attributeName, $attributeMapping)): ?>
                <span title="<?=$this->e($attributeName); ?>"><?=$this->e($attributeMapping[$attributeName]); ?></span>
<?php else: ?>
                <?=$this->e($attributeName); ?>
<?php endif; ?>
                </th>
                <td>
<?php if (1 === count($attributeValueList)): ?>
                    <?=$this->e($attributeValueList[0]); ?>
<?php else: ?>
                    <ul>
<?php foreach ($attributeValueList as $attributeValue): ?>                    
                        <li><?=$this->e($attributeValue); ?></li>
<?php endforeach; ?>
                    </ul>
<?php endif; ?>
                </td>
<?php endforeach; ?>
        </tbody>
    </table>

    <form id="submit" method="post" action="<?=$this->e($acsUrl); ?>">
        <input type="hidden" name="SAMLResponse" value="<?=$this->e($samlResponse); ?>">
        <?php if (null !== $relayState): ?>
            <input type="hidden" name="RelayState" value="<?=$this->e($relayState); ?>">
        <?php endif; ?>
        <button type="submit"><?=$this->t('Approve'); ?></button>
    </form>
<?php $this->stop(); ?>
