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
                    <option value="urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport">urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</option>
                    <option value="urn:oasis:names:tc:SAML:2.0:ac:classes:TimesyncToken">urn:oasis:names:tc:SAML:2.0:ac:classes:TimesyncToken</option>
                    <option value="urn:oasis:names:tc:SAML:2.0:ac:classes:X509">urn:oasis:names:tc:SAML:2.0:ac:classes:X509</option>
                </select>
            </details>
            <input type="submit" value="<?=$this->t('Sign In'); ?>">
        </form>
    </div>
<?php $this->stop('main'); ?>
