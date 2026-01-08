<!doctype html>

<!--[if lt IE 7]>
<html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>
<html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>
<html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!-->
<html class="no-js" lang="en"> <!--<![endif]-->

<head>
    <title><?php echo get_setting('custom_title', 'InvoicePlane', true); ?> - <?php _trans('login'); ?></title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width">
    <meta name="robots" content="NOINDEX,NOFOLLOW">

    <link rel="icon" href="<?php _core_asset('img/favicon.png'); ?>" type="image/png">

    <link rel="stylesheet" href="<?php _theme_asset('css/style.css'); ?>" type="text/css">
    <link rel="stylesheet" href="<?php _core_asset('css/custom.css'); ?>" type="text/css">
</head>

<body>

<noscript>
    <div class="alert alert-danger no-margin"><?php _trans('please_enable_js'); ?></div>
</noscript>

<br>

<div class="container">

    <div id="login" class="col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3">

<?php
if ($login_logo) {
?>
            <img src="<?php echo base_url(); ?>uploads/<?php echo $login_logo; ?>" class="login-logo img-responsive">
<?php
} else {
?>
            <h1><?php _trans('login'); ?></h1>
<?php
}
?>

        <div class="row"><?php $this->layout->load_view('layout/alerts'); ?></div>

        <form method="post" action="<?php echo site_url($this->uri->uri_string()); ?>">

            <?php _csrf_field(); ?>

            <div class="form-group">
                <label for="email" class="control-label"><?php _trans('email'); ?></label>
                <input type="email" name="email" id="email" class="form-control"
                       placeholder="<?php _trans('email'); ?>" required autofocus
                >
            </div>

            <div class="form-group">
                <label for="password" class="control-label"><?php _trans('password'); ?></label>
                <input type="password" name="password" id="password" class="form-control"
                       placeholder="<?php _trans('password'); ?>" required
                >
            </div>

            <input type="hidden" name="btn_login" value="true">

            <button type="submit" class="btn btn-primary">
                <i class="fa fa-unlock fa-margin"></i> <?php _trans('login'); ?>
            </button>
            <a href="<?php echo site_url('sessions/passwordreset'); ?>" class="btn btn-default">
                <?php _trans('forgot_your_password'); ?>
            </a>

        </form>
<?php
$openid_provider_url = $_ENV['OPENID_PROVIDER_URL'];
$openid_client_id = $_ENV['OPENID_CLIENT_ID'];
$openid_client_secret = $_ENV['OPENID_CLIENT_SECRET'];
if (!($openid_provider_url == null || $openid_provider_url == ''
    || $openid_client_id == null || $openid_client_id == ''
    || $openid_client_secret == null || $openid_client_secret == '')) {
?>
        <br>
        <form method="post" action="<?php echo site_url($this->uri->uri_string()); ?>">
            <?php _csrf_field(); ?>
            <input type="hidden" name="btn_openid" value="true">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-unlock fa-margin"></i> OpenID login
            </button>
        </form>
<?php
}
?>
    </div>
</div>

</body>
</html>
