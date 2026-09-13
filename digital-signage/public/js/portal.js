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
		slideForm.elements.content_url.required = type === 'webpage';
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
		slideForm.elements.play_sound.checked = true;
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
		dialog.querySelector('.ds-delete-copy').textContent = `The ${type} will be permanently removed. This cannot be undone.`;
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
		slideForm.elements.play_sound.checked = button.dataset.playSound !== '0';
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
		const mediaDialog = document.getElementById('media-library-dialog');
		if (!mediaDialog) return;
		slideDialog?.close();
		mediaDialog.showModal();
	});
	const mediaDialog = document.getElementById('media-library-dialog');
	const restoreSlideDialog = () => {
		mediaDialog?.close();
		if (slideDialog && !slideDialog.open) slideDialog.showModal();
	};
	mediaDialog?.querySelector('[data-media-cancel]')?.addEventListener('click', restoreSlideDialog);
	mediaDialog?.addEventListener('cancel', (event) => { event.preventDefault(); restoreSlideDialog(); });
	const chooseMedia = (button) => {
		if (!slideForm) return;
		slideForm.elements.media_id.value = button.dataset.mediaId || '';
		slideForm.querySelector('.ds-media-selection').textContent = button.dataset.mediaTitle || 'Selected media';
		if (button.dataset.mediaType === 'video') slideForm.elements.slide_type.value = 'video';
		if (button.dataset.mediaType === 'image') slideForm.elements.slide_type.value = 'image';
		if (!slideForm.elements.title.value) slideForm.elements.title.value = button.dataset.mediaTitle || '';
		updateSlideFields();
		restoreSlideDialog();
	};
	mediaDialog?.addEventListener('click', (event) => {
		const button = event.target.closest('[data-media-choice]');
		if (button) chooseMedia(button);
	});
	mediaDialog?.querySelector('[data-media-dialog-upload]')?.addEventListener('submit', async (event) => {
		event.preventDefault();
		const form = event.currentTarget;
		const file = form.elements.media_file.files[0];
		const message = mediaDialog.querySelector('[data-media-upload-status]');
		if (!file) return;
		message.textContent = 'Uploading…';
		const body = new FormData(form);
		body.append('action', 'ds_media_upload');
		body.append('nonce', window.DSPortal?.mediaNonce || '');
		body.append('group_id', String(window.DSPortal?.groupId || 0));
		try {
			const response = await fetch(window.DSPortal?.ajaxUrl || '', { method: 'POST', credentials: 'same-origin', body });
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result.data?.message || 'Upload failed.');
			const item = result.data;
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'ds-media-choice';
			button.dataset.mediaChoice = '';
			button.dataset.mediaId = String(item.id);
			button.dataset.mediaTitle = item.title;
			button.dataset.mediaType = item.type;
			const preview = item.thumbnail || item.url;
			if (preview && item.type === 'image') { const image = document.createElement('img'); image.src = preview; image.alt = ''; button.appendChild(image); }
			else if (preview && item.type === 'video') { const video = document.createElement('video'); video.src = preview; video.muted = true; video.preload = 'metadata'; button.appendChild(video); }
			else { const glyph = document.createElement('span'); glyph.className = 'ds-file-glyph'; glyph.textContent = String(item.type || 'file').toUpperCase(); button.appendChild(glyph); }
			button.insertAdjacentHTML('beforeend', '<strong></strong><small></small>');
			button.querySelector('strong').textContent = item.title;
			button.querySelector('small').textContent = item.type;
			mediaDialog.querySelector('[data-media-choices]').prepend(button);
			form.reset();
			const usage = item.usage;
			message.textContent = usage ? `Upload complete · ${Math.round(usage.percent)}% used` : 'Upload complete. Select the new item below.';
		} catch (error) { message.textContent = error.message; }
	});
	updateSlideFields();
	document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
		await navigator.clipboard.writeText(button.dataset.copy);
		const old = button.textContent; button.textContent = 'Copied'; setTimeout(() => button.textContent = old, 1200);
	}));
	document.querySelectorAll('[data-music-filter]').forEach((input) => input.addEventListener('input', () => {
		const query = input.value.trim().toLowerCase();
		document.querySelectorAll('[data-music-item]').forEach((item) => { item.hidden = Boolean(query && !item.dataset.musicItem.includes(query)); });
	}));
	document.querySelectorAll('[data-template-tab]').forEach((tab) => tab.addEventListener('click', () => {
		const selected = tab.dataset.templateTab;
		document.querySelectorAll('[data-template-tab]').forEach((item) => item.setAttribute('aria-selected', item === tab ? 'true' : 'false'));
		document.querySelectorAll('[data-template-panel]').forEach((panel) => { panel.hidden = panel.dataset.templatePanel !== selected; });
	}));
});
