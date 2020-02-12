<?php $this->layout('base'); ?>
<?php $this->start('main'); ?>
    <div class="auth">
        <p>
            <?=$this->t('Please sign in with your username and password.'); ?>
        </p>
        <form method="post">
            <input type="text" name="authUser" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" autofocus required>
            <input type="password" name="authPass" placeholder="<?=$this->t('Password'); ?>" required>
<?php if ($requireTwoFactor): ?>
            <input type="text" inputmode="numeric" name="otpKey" autocapitalize="off" placeholder="<?=$this->t('OTP'); ?>" autocomplete="off" maxlength="6" required pattern="[0-9]{6}">
<?php endif; ?>
            <input type="submit" value="<?=$this->t('Sign In'); ?>">
        </form>
    </div>
<?php $this->stop('main'); ?>
