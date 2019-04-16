<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="application-name" content="php-saml-idp">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$this->t('Identity Provider'); ?></title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap-reboot.css">
    <link rel="stylesheet" type="text/css" href="css/screen.css">
</head>
<body>
    <div class="header">
        <h1><?=$this->t('Identity Provider'); ?></h1>
    </div> <!-- /header -->
    <div class="content">
        <?=$this->section('content'); ?>
    </div> <!-- /content -->

    <div class="footer">
        Powered by <a href="https://git.tuxed.net/fkooman/php-saml-idp/about/">php-saml-idp</a>
    </div> <!-- /footer -->
</body>
</html>
