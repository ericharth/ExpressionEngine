<?php extend_template('default-nav', 'outer_box'); ?>

<div class="box snap">
	<div class="tbl-ctrls">
		<?=form_open($table['base_url'])?>
			<?php $this->view('_shared/alerts')?>
			<fieldset class="tbl-search right">
				<a class="btn tn action" href="<?=cp_url('settings/uploads/new-upload')?>"><?=lang('upload_create')?></a>
			</fieldset>
			<h1><?=$table_heading?></h1>
			<?php $this->view('_shared/table', $table); ?>
			<?php $this->view('_shared/pagination'); ?>
			<fieldset class="tbl-bulk-act">
				<select name="table_action">
					<option value="none">-- <?=lang('with_selected')?> --</option>
					<option value="remove"><?=lang('upload_remove')?></option>
				</select>
				<input class="btn submit" type="submit" value="<?=lang('submit')?>">
			</fieldset>
		</form>
	</div>
</div>