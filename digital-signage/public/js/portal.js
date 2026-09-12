document.addEventListener('DOMContentLoaded', () => {
	const slideDialog = document.getElementById('slide-dialog');
	const slideForm = slideDialog?.querySelector('form');
	const scheduleDialog = document.getElementById('schedule-dialog');
	const scheduleForm = scheduleDialog?.querySelector('form');
	const updateSlideFields = () => {
		if (!slideForm) return;
		const type = slideForm.elements.slide_type.value;
		slideForm.querySelector('.ds-slide-media').hidden = !['image', 'video'].includes(type);
		slideForm.querySelector('.ds-slide-webpage').hidden = type !== 'webpage';
		slideForm.querySelector('.ds-slide-html').hidden = type !== 'html';
		slideForm.querySelector('.ds-slide-video').hidden = type !== 'video';
	};
	const resetSlide = () => {
		if (!slideForm) return;
		slideForm.reset();
		slideForm.elements.id.value = '';
		slideForm.elements.media_id.value = '';
		slideForm.elements.duration_override.value = '0';
		slideForm.elements.video_play_mode.value = 'until_end';
		slideForm.querySelector('[data-slide-dialog-title]').textContent = 'Add slide';
		slideForm.querySelector('.ds-media-selection').textContent = 'No media selected';
		updateSlideFields();
	};
	const resetSchedule = () => {
		if (!scheduleForm) return;
		scheduleForm.reset(); scheduleForm.elements.id.value = '';
		scheduleForm.elements.start_time.value = '08:00'; scheduleForm.elements.end_time.value = '17:00';
		scheduleForm.querySelector('[data-schedule-dialog-title]').textContent = 'New content schedule';
	};

	document.querySelectorAll('[data-open]').forEach((button) => button.addEventListener('click', () => {
		if (button.dataset.open === 'slide-dialog') resetSlide();
		if (button.dataset.open === 'schedule-dialog') resetSchedule();
		document.getElementById(button.dataset.open)?.showModal();
	}));
	document.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
	document.querySelectorAll('dialog').forEach((dialog) => dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));
	document.querySelectorAll('[data-delete-type]').forEach((button) => button.addEventListener('click', () => {
		const dialog = document.getElementById('delete-dialog');
		const type = button.dataset.deleteType;
		dialog.querySelector('[name="entity_type"]').value = type;
		dialog.querySelector('[name="entity_id"]').value = button.dataset.deleteId;
		dialog.querySelector('.ds-delete-copy').textContent = `This cannot be undone. Type DELETE ${type.toUpperCase()} to confirm.`;
		dialog.querySelector('[name="verification"]').value = '';
		dialog.showModal();
	}));
	document.querySelectorAll('[data-slide-edit]').forEach((button) => button.addEventListener('click', () => {
		resetSlide();
		slideForm.elements.id.value = button.dataset.slideId || '';
		slideForm.elements.title.value = button.dataset.title || '';
		slideForm.elements.slide_type.value = button.dataset.type || 'image';
		slideForm.elements.media_id.value = button.dataset.mediaId || '';
		slideForm.elements.content_url.value = button.dataset.contentUrl || '';
		slideForm.elements.content_html.value = button.dataset.contentHtml || '';
		slideForm.elements.duration_override.value = button.dataset.duration || '0';
		slideForm.elements.video_play_mode.value = button.dataset.playMode || 'until_end';
		slideForm.querySelector('[data-slide-dialog-title]').textContent = 'Edit slide';
		slideForm.querySelector('.ds-media-selection').textContent = button.dataset.mediaUrl ? button.dataset.mediaUrl.split('/').pop() : 'No media selected';
		updateSlideFields();
		slideDialog.showModal();
	}));
	document.querySelectorAll('[data-schedule-edit]').forEach((button) => button.addEventListener('click', () => {
		resetSchedule();
		scheduleForm.elements.id.value = button.dataset.scheduleId;
		scheduleForm.elements.title.value = button.dataset.title;
		scheduleForm.elements.channel_id.value = button.dataset.channelId;
		const screenIds = JSON.parse(button.dataset.screenIds || '[]').map(String);
		const days = JSON.parse(button.dataset.days || '[]');
		scheduleForm.querySelectorAll('[name="screen_ids[]"]').forEach((input) => input.checked = screenIds.includes(input.value));
		scheduleForm.querySelectorAll('[name="days[]"]').forEach((input) => input.checked = days.includes(input.value));
		scheduleForm.elements.start_time.value = button.dataset.start || '08:00';
		scheduleForm.elements.end_time.value = button.dataset.end || '17:00';
		scheduleForm.querySelector('[data-schedule-dialog-title]').textContent = 'Edit content schedule';
		scheduleDialog.showModal();
	}));
	slideForm?.elements.slide_type.addEventListener('change', updateSlideFields);
	slideForm?.querySelector('[data-select-media]')?.addEventListener('click', () => {
		if (!window.wp?.media) return;
		const picker = wp.media({ title: 'Select or upload media', button: { text: 'Use this media' }, multiple: false });
		picker.on('open', () => picker.state().get('library')?.props.set('ds_group', window.DSPortal?.groupId || 0));
		picker.on('select', () => {
			const item = picker.state().get('selection').first().toJSON();
			slideForm.elements.media_id.value = item.id;
			slideForm.querySelector('.ds-media-selection').textContent = item.filename || item.title;
			if (item.type === 'video') slideForm.elements.slide_type.value = 'video';
			if (item.type === 'image') slideForm.elements.slide_type.value = 'image';
			if (!slideForm.elements.title.value) slideForm.elements.title.value = item.title || item.filename;
			updateSlideFields();
		});
		picker.open();
	});
	if (window.wp?.Uploader?.defaults?.multipart_params) window.wp.Uploader.defaults.multipart_params.ds_group = window.DSPortal?.groupId || 0;
	updateSlideFields();
	document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
		await navigator.clipboard.writeText(button.dataset.copy);
		const old = button.textContent; button.textContent = 'Copied'; setTimeout(() => button.textContent = old, 1200);
	}));
});
