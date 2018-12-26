<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="application-name" content="php-saml-idp">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$this->t('Identity Provider'); ?></title>
    <link rel="stylesheet" type="text/css" href="css/milligram.min.css">
    <link rel="stylesheet" type="text/css" href="css/screen.css">
    <?=$this->section('js'); ?>
</head>
<body>
    <h1><?=$this->t('Identity Provider'); ?></h1>
    <div class="content">
        <?=$this->section('content'); ?>
    </div> <!-- container -->

    <div class="footer">
        <a href="https://git.tuxed.net/fkooman/php-saml-idp"><?=$this->t('php-saml-idp'); ?></a>
    </div> <!-- footer -->
</body>
</html>
