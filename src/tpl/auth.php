<?php $this->layout('base'); ?>
<?php $this->start('main'); ?>
    <form method="post" class="auth">
        <input type="text" name="authUser" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" autofocus required>
        <input type="password" name="authPass" placeholder="<?=$this->t('Password'); ?>" required>
        <input type="submit" value="<?=$this->t('Sign In'); ?>">
    </form>
<?php $this->stop('main'); ?>
