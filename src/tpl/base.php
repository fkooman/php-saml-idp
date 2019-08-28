<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="application-name" content="php-saml-idp">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=$this->t('Identity Provider'); ?></title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap-reboot.min.css">
    <link rel="stylesheet" type="text/css" href="css/screen.css">
</head>
<body>
    <header>
        <?=$this->t('Identity Provider'); ?>
    </header>
    <main>
        <?=$this->section('main'); ?>
    </main>

    <footer>
        Powered by <a href="https://git.tuxed.net/fkooman/php-saml-idp/about/">php-saml-idp</a>
	</footer>
</body>
</html>
