<form method="post" action="<?=BASE?>&amp;C=addons_extensions&amp;M=save_extension_settings">
	<div>
		<input type="hidden" name="file" value="<?=strtolower($name)?>" />
		<input type="hidden" name="XID" value="<?=XID_SECURE_HASH?>" />
	</div>
	<table cellpadding="0" cellspacing="0" style="width:100%" class="mainTable low-extension-settings">
		<colgroup>
			<col style="width:50%" />
			<col style="width:50%" />
		</colgroup>
		<thead>
			<tr>
				<th scope="col"><?=lang('preference')?></th>
				<th scope="col"><?=lang('setting')?></th>
			</tr>
		</thead>
		<tbody>
			<?php $i = 0; ?>
			<tr class="<?=(++$i % 2 ? 'odd' : 'even')?>">
				<td style="vertical-align:top">
					<label for="category_groups"><?=lang('category_groups')?></label>
				</td>
				<td>
					<select name="category_groups[]" id="category_groups" multiple="multiple" size="5">
					<?php foreach ($category_groups AS $group_id => $group_name): ?>
						<option value="<?=$group_id?>"<?php if (in_array($group_id, $current['category_groups'])): ?> selected="selected"<?php endif; ?>>
							<?=htmlspecialchars($group_name)?>
						</option>
					<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr class="<?=(++$i % 2 ? 'odd' : 'even')?>">
				<td>
					<label for="uri_pattern"><?=lang('uri_pattern')?></label>
				</td>
				<td>
					<input type="text" name="uri_pattern" id="uri_pattern" value="<?=htmlspecialchars($current['uri_pattern'])?>" />
				</td>
			</tr>
			<tr class="<?=(++$i % 2 ? 'odd' : 'even')?>">
				<td>
					<strong><?=lang('set_all_segments')?></label>
				</td>
				<td>
					<label><input type="radio" name="set_all_segments" value="y"<?php if ($current['set_all_segments'] == 'y'): ?> checked="checked"<?php endif; ?> /> <?=lang('yes')?></label>
					<label style="margin-left:10px"><input type="radio" name="set_all_segments" value="n"<?php if ($current['set_all_segments'] == 'n'): ?> checked="checked"<?php endif; ?> /> <?=lang('no')?></label>
				</td>
			</tr>
		</tbody>
	</table>
	<input type="submit" class="submit" value="<?=lang('save')?>" />
</form>