<?php

$this->layout('main::_layouts/admin-layout');
$this->section('backend');
TriTan\Common\Container::getInstance()->{'set'}('screen_parent', 'options');
TriTan\Common\Container::getInstance()->{'set'}('screen_child', 'options-reading');
use TriTan\Csrf\Nonce;
use Qubus\Hooks\ActionFilterHook;

ActionFilterHook::getInstance()->{'doAction'}('options_init');

$option = (
    new \TriTan\Common\Options\Options(
        new TriTan\Common\Options\OptionsMapper(
            app()->qudb,
            new TriTan\Common\Context\HelperContext()
        )
    )
);
?>
<!-- form start -->
<form name="form" method="post" data-toggle="validator" action="<?= admin_url('options-reading/'); ?>" id="form" autocomplete="off">
    <!-- Content Wrapper. Contains post content -->
    <div class="content-wrapper">
        <!-- Content Header (Post header) -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <i class="fa fa-cogs"></i>
                    <h3 class="box-title"><?= esc_html__('Reading Options'); ?></h3>

                    <div class="pull-right">
                        <?= Nonce::field('options-reading'); ?>
                        <button type="submit" class="btn btn-success"><i class="fa fa-pencil-alt"></i> <?= esc_html__('Update'); ?></button>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">

                <?= (new \TriTan\Common\FlashMessages())->showMessage(); ?>

                <div class="row">
                    <!-- left column -->
                    <div class="col-md-9">
                        <!-- general form elements -->
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><?= esc_html__('Reading Options'); ?></h3>
                            </div>
                            <!-- /.box-header -->
                            <div class="box-body">
                                <div class="form-group">
                                    <label><strong><?= esc_html__('Charset'); ?></strong></label>
                                    <?=ttcms_charset($option->{'read'}('charset'));?>
                                </div>
                                <div class="form-group">
                                    <label><strong><?= esc_html__('Site Theme'); ?></strong></label>
                                    <select class="form-control select2" name="current_site_theme" style="width: 100%;">
                                        <option value=""> ------------------------- </option>
                                        <?php get_site_themes($option->{'read'}('current_site_theme')); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><strong><?= esc_html__('Posts per Page'); ?></strong></label>
                                    <input type="text" class="form-control" name="posts_per_page" value="<?= $option->{'read'}('posts_per_page'); ?>" />
                                </div>
                                <div class="form-group">
                                    <label><strong><?= esc_html__('Date Format'); ?></strong></label>
                                    <input type="text" class="form-control" name="date_format" value="<?= $option->{'read'}('date_format'); ?>" />
                                </div>
                                <div class="form-group">
                                    <label><strong><?= esc_html__('Time Format'); ?></strong></label>
                                    <input type="text" class="form-control" name="time_format" value="<?= $option->{'read'}('time_format'); ?>" />
                                </div>
                                <?php ActionFilterHook::getInstance()->{'doAction'}('options_reading_form'); ?>
                            </div>
                            <!-- /.box-body -->
                        </div>
                        <!-- /.box-body -->
                    </div>
                    <!-- /.left column -->

                </div>
                <!--/.row -->
            </section>
            <!-- /.Main content -->
    </div>
</form>
<!-- /.Content Wrapper. Contains post content -->
<?php $this->stop(); ?>
