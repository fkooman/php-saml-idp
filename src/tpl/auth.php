<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <div class="auth">
        <p>
            <?=$this->t('Please sign in with your username and password.'); ?>
        </p>

        <?php if (isset($errorOccurred)): ?>
            <p class="error">
                <?=$this->t('The credentials you provided were not correct.'); ?>
            </p>
        <?php endif; ?>

        <form method="post">
            <fieldset>
                <label for="authUser"><?=$this->t('Username'); ?></label>
                <input size="30" type="text"     id="authUser" name="authUser" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" autofocus required>
                <label for="authPass"><?=$this->t('Password'); ?></label>
                <input size="30" type="password" id="authPass" name="authPass" placeholder="<?=$this->t('Password'); ?>" required>
            </fieldset>
            <fieldset>
                <button type="submit"><?=$this->t('Sign In'); ?></button>
            </fieldset>
        </form>
    </div>
<?php $this->stop(); ?>
