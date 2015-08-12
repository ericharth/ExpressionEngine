<?php $this->extend('_templates/default-nav') ?>

<div class="tbl-ctrls">
	<h1><?php echo isset($cp_heading) ? $cp_heading : $cp_page_title?></h1>
	<?=ee('Alert')->getAllInlines()?>
	<section class="item-wrap email">
		<div class="item">
			<ul class="toolbar">
				<li class="remove"><a href="" class="m-link" rel="modal-confirm-remove" title="remove"></a></li>
			</ul>
			<h3><b>Date:</b> <?=$localize->human_time($email->cache_date->format('U'))?><br><b>From:</b> <a href="<?=ee('CP/URL', 'myaccount', array('id' => $email->member_id))?>"><?=$email->member_name?></a><br><b>To:</b> <?=$email->recipient_name?><br><b>Subject:</b> <?=$email->subject?></h3>
			<div class="message">
				<?=$email->message?>
			</div>
		</div>
	</section>
</div>

<?php $this->startOrAppendBlock('modals'); ?>

<?php
$modal_vars = array(
	'name'      => 'modal-confirm-remove',
	'form_url'	=> ee('CP/URL', 'logs/email'),
	'hidden'	=> array(
		'delete'	=> $email->cache_id
	),
	'checklist'	=> array(
		array(
			'kind' => lang('view_email_logs'),
			'desc' => lang('sent_to') . ' ' . $email->recipient_name . ', ' . lang('subject') . ': ' . $email->subject
		)
	)
);

$this->embed('_shared/modal_confirm_remove', $modal_vars);
?>

<?php $this->endBlock(); ?>
