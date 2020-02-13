<?php $this->layout('base'); ?>
<?php $this->start('main'); ?>
    <div class="auth">
        <p>
            <?=$this->t('Please provide your OTP key.'); ?>
        </p>
        <form method="post" action="two_factor">
            <input type="text" inputmode="numeric" name="otpKey" autocapitalize="off" placeholder="<?=$this->t('OTP'); ?>" autocomplete="off" maxlength="6" required pattern="[0-9]{6}">
            <input type="submit" value="<?=$this->t('Verify'); ?>">
        </form>
    </div>
<?php $this->stop('main'); ?>
