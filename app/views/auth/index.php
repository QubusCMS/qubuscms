<?php
use TriTan\Csrf\Nonce;
use Qubus\Hooks\ActionFilterHook;

$this->layout('main::_layouts/auth-layout');
$this->section('auth');
?>

<div class="brand">
  <img src="static/assets/img/auth/logo.jpg" alt="logo">
</div>

<div class="card fat">
  <div class="card-body">
    <h4 class="card-title"><?=t__('Login');?></h4>
    <?php ActionFilterHook::getInstance()->{'doAction'}('login_form_top'); ?>
    <form method="post" action="<?= login_url(); ?>" class="my-login-validation" novalidate="" autocomplete="off">
      <div class="form-group">
        <label for="username"><?=t__('Username');?></label>
        <input id="username" type="text" class="form-control" name="user_login" required="required" autofocus>
        <div class="invalid-feedback">
          <?=t__('Username is invalid.');?>
        </div>
      </div>

      <div class="form-group">
        <label for="password"><?=t__('Password');?>
          <a href="reset-password/" class="float-right">
            <?=t__('Forgot Password?');?>
          </a>
        </label>
        <input id="password" type="password" class="form-control" name="user_pass" required="required" data-eye>
          <div class="invalid-feedback">
            <?=t__('Password is required.');?>
          </div>
      </div>

      <div class="form-group">
        <div class="custom-checkbox custom-control">
          <input type="checkbox" name="rememberme" value="yes" id="remember" class="custom-control-input">
          <label for="remember" class="custom-control-label"><?=t__('Remember Me');?></label>
        </div>
      </div>

      <div class="form-group m-0">
        <?= Nonce::field('ttcms-login'); ?>
        <input type="submit" name="login-submit" class="btn btn-submit btn-block" value="<?=esc_attr__('Login');?>">
      </div>
    </form>
    <?php ActionFilterHook::getInstance()->{'doAction'}('login_form_bottom'); ?>
  </div>
</div>

<?php $this->stop(); ?>
