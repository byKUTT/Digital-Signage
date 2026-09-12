document.addEventListener('DOMContentLoaded', () => {
	const frame = document.getElementById('ds-vellum-frame');
	if (!frame || !window.DSDesigner) return;
	const config = window.DSDesigner;
	const title = document.getElementById('ds-design-title');
	const status = document.getElementById('ds-design-status');
	const channel = document.getElementById('ds-design-channel');
	const duration = document.getElementById('ds-design-duration');
	let designId = Number(config.designId || 0);

	const setStatus = (message, error = false) => {
		status.textContent = message;
		status.classList.toggle('error', error);
	};
	const api = () => frame.contentWindow?.vellum;
	const node = (type, properties = {}) => ({
		id: crypto.randomUUID(), type, name: type[0].toUpperCase() + type.slice(1), parentId: null,
		x: 0, y: 0, w: 160, h: 100, rotation: 0, fill: '#ffffff', fill2: '#ffffff', fillType: 'solid',
		gradientAngle: 90, fillOpacity: 1, stroke: '#000000', strokeWidth: 0, radius: 0, opacity: 1,
		visible: true, locked: false, clip: false, shadow: false, shadowColor: '#000000', shadowOpacity: .16,
		shadowBlur: 20, shadowX: 0, shadowY: 6, version: 0, ...properties
	});
	const makeDocument = (width, height, name) => {
		const pageId = crypto.randomUUID();
		const frameNode = node('frame', { name: `${width} × ${height}`, w: width, h: height, clip: true });
		const textNode = node('text', { parentId: frameNode.id, name: 'Headline', x: Math.round(width * .08), y: Math.round(height * .1), w: Math.round(width * .84), h: Math.round(height * .2), text: 'Your message', fontFamily: 'Inter', fontSize: Math.max(48, Math.round(width / 18)), fontWeight: 600, fontStyle: 'normal', lineHeight: 1.1, letterSpacing: 0, textAlign: 'left', textDecoration: 'none', textCase: 'none', direction: 'auto', fill: '#182019' });
		return { format: 'vellum', version: 1, name, pages: [{ id: pageId, name, nodes: [frameNode, textNode] }], pageId, assets: {}, fonts: {}, components: {}, tokens: { colors: [], typography: [] } };
	};
	const loadDocument = (documentData) => {
		const vellum = api();
		if (!vellum || !documentData) return;
		vellum.doc.data = documentData;
		vellum.doc.refresh();
		vellum.state.selection.clear();
		vellum.history.undoStack.length = 0;
		vellum.history.redoStack.length = 0;
		vellum.fit();
		vellum.render();
	};
	const waitUntilReady = () => new Promise((resolve, reject) => {
		let attempts = 0;
		const timer = setInterval(() => {
			if (api()?.ready) { clearInterval(timer); resolve(api()); }
			else if (++attempts > 200) { clearInterval(timer); reject(new Error('Designer did not finish loading.')); }
		}, 50);
	});

	let initialized = false;
	const initialize = async () => {
		if (initialized) return;
		try {
			await waitUntilReady();
			initialized = true;
			if (config.document) loadDocument(config.document);
			setStatus(config.document ? 'Saved design loaded' : 'Ready to design');
		} catch (error) { setStatus(error.message, true); }
	};
	frame.addEventListener('load', initialize);
	if (frame.contentDocument?.readyState === 'complete') initialize();

	document.querySelectorAll('[data-design-format]').forEach((button) => button.addEventListener('click', () => {
		const [width, height] = button.dataset.designFormat.split('x').map(Number);
		const name = button.dataset.designName || `${width} × ${height}`;
		if (!confirm('Start a new design? Save the current design first if you want to keep it.')) return;
		designId = 0;
		title.value = name;
		loadDocument(makeDocument(width, height, name));
		setStatus(`New ${name} canvas`);
	}));

	const save = async (publish) => {
		const vellum = api();
		if (!vellum?.ready) { setStatus('Designer is still loading.', true); return; }
		if (publish && !channel.value) { setStatus('Choose a channel before publishing.', true); channel.focus(); return; }
		const roots = vellum.doc.nodes.filter((item) => !item.parentId).map((item) => item.id);
		if (!roots.length) { setStatus('Add a frame or object before saving.', true); return; }
		document.querySelectorAll('[data-design-save]').forEach((button) => button.disabled = true);
		setStatus(publish ? 'Rendering and publishing…' : 'Rendering and saving…');
		try {
			vellum.doc.data.name = title.value.trim() || 'Untitled design';
			const canvas = await vellum.renderer.exportCanvas(roots, 1);
			const body = new FormData();
			body.append('action', 'ds_vellum_save'); body.append('nonce', config.nonce);
			body.append('group_id', String(config.groupId)); body.append('design_id', String(designId));
			body.append('title', vellum.doc.data.name); body.append('document', vellum.doc.serialize());
			body.append('image', canvas.toDataURL('image/png')); body.append('publish', publish ? '1' : '0');
			body.append('channel_id', channel.value); body.append('duration', duration.value);
			const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result.data?.message || 'The design could not be saved.');
			designId = Number(result.data.designId);
			setStatus(result.data.message);
			history.replaceState(null, '', config.editUrl.replace('__ID__', String(designId)));
		} catch (error) { setStatus(error.message, true); }
		finally { document.querySelectorAll('[data-design-save]').forEach((button) => button.disabled = false); }
	};
	document.querySelectorAll('[data-design-save]').forEach((button) => button.addEventListener('click', () => save(button.dataset.designSave === 'publish')));
});
