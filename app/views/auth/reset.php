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
    <h4 class="card-title"><?=t__('Reset Password');?></h4>
    <form method="post" action="<?= site_url('reset-password/'); ?>" class="my-login-validation" novalidate="" autocomplete="off">
      <div class="form-group">
        <label for="username"><?=t__('Email');?></label>
        <input id="email" type="email" class="form-control" name="email" required="required" autofocus>
        <div class="invalid-feedback">
          <?=t__('Email is invalid.');?>
        </div>
        <div class="form-text text-muted">
					<?=t__('By clicking "Reset" the system will email you a new password.');?>
				</div>
      </div>

      <div class="form-group m-0">
        <?= Nonce::field('reset-password'); ?>
        <input type="submit" name="reset_password" class="btn btn-submit btn-block" value="<?=esc_attr__('Reset');?>">
      </div>
    </form>
  </div>
</div>

<?php $this->stop(); ?>
