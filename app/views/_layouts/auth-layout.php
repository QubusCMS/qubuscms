<?php
$app = \Liten\Liten::getInstance();
use Qubus\Hooks\ActionFilterHook;

ob_start();
ob_implicit_flush(0);
ActionFilterHook::getInstance()->doAction('auth_init');
$option = (
    new \TriTan\Common\Options\Options(
        new TriTan\Common\Options\OptionsMapper(
            app()->qudb,
            new TriTan\Common\Context\HelperContext()
        )
    )
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <base href="<?= site_url(); ?>">
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
	<title><?= t__('Login', 'tritan-cms') . ' &lsaquo; ' . $option->{'read'}('sitename'); ?> &#8212; <?= t__('Qubus CMS', 'tritan-cms'); ?></title>
  <link rel="stylesheet" href="static/assets/css/auth/bootstrap.min.css">
	<link rel="stylesheet" href="static/assets/css/auth/auth.min.css">
</head>

<body class="my-login-page">
	<section class="h-100">
		<div class="container h-100">
			<div class="row justify-content-md-center h-100">
				<div class="card-wrapper">

          <?php $this->section('auth'); ?>
          <?php $this->stop(); ?>

					<div class="footer">
            <footer>
                <div class="container">
                    <div class="col-md-10 col-md-offset-1 text-center">
                        <h6 style="font-size:14px;font-weight:100;"><?= esc_html__('Powered by'); ?> <a href="//www.qubuscms.com"><?= esc_html__('Qubus CMS'); ?></a> r<?= CURRENT_RELEASE; ?></h6>
                    </div>
                </div>
            </footer>
					</div>

				</div>
			</div>
		</div>
	</section>

	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	<script src="static/assets/js/auth/auth.min.js"></script>
</body>
</html>
<?php print_gzipped_page(); ?>
