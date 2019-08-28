<?php $this->layout('base'); ?>
<?php $this->start('main'); ?>
    <h2><?=$this->t('Error'); ?> <?=$this->e($errorCode); ?></h2>
    <p>
        <?=$this->e($errorMessage); ?>
    </p>
<?php $this->stop('main'); ?>
