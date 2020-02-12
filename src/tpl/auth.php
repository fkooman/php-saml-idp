<?php $this->layout('base'); ?>
<?php $this->start('main'); ?>
    <div class="auth">
        <p>
            <?=$this->t('Please sign in with your username and password.'); ?>
        </p>
        <form method="post">
            <input type="text" name="authUser" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" autofocus required>
            <input type="password" name="authPass" placeholder="<?=$this->t('Password'); ?>" required>
            <details>
                <summary>Advanced</summary>
                <label for="authnContextClassRef">AuthnContextClassRef</label>
                <select name="authnContextClassRef" id="authnContextClassRef">
<?php foreach ($supportedAuthnContextClassRefList as $supportedAuthnContextClassRef): ?>
<?php if ($supportedAuthnContextClassRef === $requestedAuthnContextClassRef): ?>
    <option selected="selected" value="<?=$this->e($supportedAuthnContextClassRef); ?>">(REQ) <?=$this->e($supportedAuthnContextClassRef); ?></option>
<?php else: ?>
    <option value="<?=$this->e($supportedAuthnContextClassRef); ?>"><?=$this->e($supportedAuthnContextClassRef); ?></option>
<?php endif; ?>
<?php endforeach; ?>
                </select>
            </details>
            <input type="submit" value="<?=$this->t('Sign In'); ?>">
        </form>
    </div>
<?php $this->stop('main'); ?>
