<?php $this->layout('base'); ?>
<?php $this->start('js'); ?>
    <script src="js/submit.js"></script>
<?php $this->stop(); ?>
<?php $this->start('content'); ?>
    <form id="submit" method="post" action="<?=$this->e($acsUrl); ?>">
        <input type="hidden" name="SAMLResponse" value="<?=$this->e($samlResponse); ?>">
        <?php if (null !== $relayState): ?>
            <input type="hidden" name="RelayState" value="<?=$this->e($relayState); ?>">
        <?php endif; ?>
        <input type="submit" value="Go">
    </form>
<?php $this->stop(); ?>
