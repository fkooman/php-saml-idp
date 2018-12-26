<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>

    <p>
        The following attributes will be released to <strong title="<?=$this->e($spEntityId); ?>"><?=$this->e($displayName); ?></strong>.
    </p>

    <table>
        <thead>
            <tr><th>Attribute</th><th>Value(s)</th></tr>
        </thead>
        <tbody>
<?php foreach ($attributeList as $attributeName => $attributeValueList): ?>
            <tr>
                <th>
<?php if (\array_key_exists($attributeName, $attributeMapping)): ?>
                <span title="<?=$this->e($attributeName); ?>"><?=$this->e($attributeMapping[$attributeName]); ?>
<?php else: ?>
                <?=$this->e($attributeName); ?>
<?php endif; ?>
                </th>
                <td>
<?php if (1 === \count($attributeValueList)): ?>
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
        <input type="submit" value="Confirm">
    </form>
<?php $this->stop(); ?>
