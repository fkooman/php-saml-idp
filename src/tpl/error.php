<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <div class="error">
    <h2><?=$this->t('Error'); ?> <?=$this->e($errorCode); ?></h2>
    <p>
        <?=$this->e($errorMessage); ?>
    </p>
    </div> <!-- /error -->
<?php $this->stop(); ?>
