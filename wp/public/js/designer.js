document.addEventListener('DOMContentLoaded', () => {
	const frame = document.getElementById('ds-vellum-frame');
	if (!frame || !window.DSDesigner) return;
	const config = window.DSDesigner;
	const title = document.getElementById('ds-design-title');
	const status = document.getElementById('ds-design-status');
	const channel = document.getElementById('ds-design-channel');
	const duration = document.getElementById('ds-design-duration');
	let designId = Number(config.designId || 0);
	let initialized = false;

	const uid = () => globalThis.crypto?.randomUUID?.() || `ds${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
	const setStatus = (message, error = false) => {
		status.textContent = message;
		status.classList.toggle('error', error);
	};
	const api = () => frame.contentWindow?.vellum;
	const node = (type, properties = {}) => ({
		id: uid(), type, name: type[0].toUpperCase() + type.slice(1), parentId: null,
		x: 0, y: 0, w: 160, h: 100, rotation: 0, fill: '#ffffff', fill2: '#ffffff', fillType: 'solid',
		gradientAngle: 90, fillOpacity: 1, stroke: '#000000', strokeWidth: 0, radius: 0, opacity: 1,
		visible: true, locked: false, clip: false, shadow: false, shadowColor: '#000000', shadowOpacity: .16,
		shadowBlur: 20, shadowX: 0, shadowY: 6, version: 0, ...properties
	});
	const text = (parentId, name, value, x, y, w, h, size, color, weight = 600, align = 'left') => node('text', {
		parentId, name, text: value, x, y, w, h, fontFamily: 'Geist', fontSize: size, fontWeight: weight,
		fontStyle: 'normal', lineHeight: 1.08, letterSpacing: 0, textAlign: align, textDecoration: 'none',
		textCase: 'none', direction: 'auto', fill: color
	});
	const documentFrom = (name, width, height, background, children) => {
		const pageId = uid();
		const root = node('frame', { name: `${width} × ${height}`, w: width, h: height, fill: background, clip: true });
		return { format: 'vellum', version: 1, name, pages: [{ id: pageId, name, nodes: [root, ...children(root.id)] }], pageId, assets: {}, fonts: {}, components: {}, tokens: { colors: [], typography: [] } };
	};
	const templates = {
		blank: () => documentFrom('Blank landscape', 1920, 1080, '#f4f0e8', (root) => [text(root, 'Headline', 'Your message', 150, 135, 1620, 260, 118, '#182019', 680)]),
		'blank-portrait': () => documentFrom('Blank portrait', 1080, 1920, '#f4f0e8', (root) => [text(root, 'Headline', 'Your message', 90, 140, 900, 360, 96, '#182019', 680)])
	};
	const templateAliases = { 'daily-offers': 'offers-1-landscape', 'weekly-menu': 'menu-1-landscape', event: 'event-1-landscape', welcome: 'welcome-1-portrait' };
	const dataUrl = (blob) => new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = reject; reader.readAsDataURL(blob); });
	const loadTemplate = async (requestedKey) => {
		if (templates[requestedKey]) return templates[requestedKey]();
		const key = templateAliases[requestedKey] || requestedKey;
		if (!/^(offers|menu|event|welcome)-[1-4]-(landscape|portrait)$/.test(key)) return templates.blank();
		const response = await fetch(`${config.templateBase}${key}.json`, { credentials: 'same-origin' });
		if (!response.ok) throw new Error('Template could not be loaded.');
		const documentData = await response.json();
		await Promise.all(Object.entries(documentData.assetFiles || {}).map(async ([assetId, filename]) => {
			const imageResponse = await fetch(`${config.imageBase}${filename}`, { credentials: 'same-origin' });
			if (!imageResponse.ok) throw new Error('Template image could not be loaded.');
			documentData.assets[assetId] = await dataUrl(await imageResponse.blob());
		}));
		delete documentData.assetFiles;
		return documentData;
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
	const resizeForChannel = (resolution) => {
		const match = /^(\d{2,5})x(\d{2,5})$/.exec(resolution || '');
		const vellum = api();
		if (!match || !vellum?.ready) return false;
		const width = Number(match[1]); const height = Number(match[2]);
		const page = vellum.doc.data.pages.find((item) => item.id === vellum.doc.data.pageId) || vellum.doc.data.pages[0];
		const root = page?.nodes?.find((item) => !item.parentId);
		if (!root || (root.w === width && root.h === height)) return false;
		const scaleX = width / root.w; const scaleY = height / root.h; const typeScale = Math.min(scaleX, scaleY);
		page.nodes.forEach((item) => {
			if (item.id === root.id) { item.w = width; item.h = height; item.name = `${width} × ${height}`; }
			else {
				item.x *= scaleX; item.y *= scaleY; item.w *= scaleX; item.h *= scaleY;
				if (Number.isFinite(item.fontSize)) item.fontSize *= typeScale;
				if (Number.isFinite(item.radius)) item.radius *= typeScale;
				if (Number.isFinite(item.strokeWidth)) item.strokeWidth *= typeScale;
			}
			item.version = Number(item.version || 0) + 1;
		});
		vellum.doc.refresh(); vellum.fit(); vellum.render();
		setStatus(`Canvas matched to channel at ${width} × ${height}`);
		return true;
	};
	const waitUntilReady = () => new Promise((resolve, reject) => {
		let attempts = 0;
		const timer = setInterval(() => {
			if (api()?.ready) { clearInterval(timer); resolve(api()); }
			else if (++attempts > 200) { clearInterval(timer); reject(new Error('Designer did not finish loading.')); }
		}, 50);
	});
	const initialize = async () => {
		if (initialized) return;
		try {
			await waitUntilReady();
			initialized = true;
			if (config.document) {
				loadDocument(config.document);
				setStatus('Saved design loaded');
			} else {
				const fresh = await loadTemplate(config.template || 'daily-offers');
				title.value = fresh.name;
				loadDocument(fresh);
				setStatus(`${fresh.name} template loaded`);
			}
		} catch (error) { setStatus(error.message, true); }
	};
	frame.addEventListener('load', initialize);
	if (frame.contentDocument?.readyState === 'complete') initialize();
	channel?.addEventListener('change', () => resizeForChannel(config.channelFormats?.[channel.value] || ''));

	document.querySelectorAll('[data-design-format]').forEach((button) => button.addEventListener('click', () => {
		const [width, height] = button.dataset.designFormat.split('x').map(Number);
		if (!confirm('Start a new blank design? Save the current design first if you want to keep it.')) return;
		designId = 0;
		const fresh = documentFrom(`${width} × ${height}`, width, height, '#f4f0e8', (root) => [text(root, 'Headline', 'Your message', Math.round(width * .08), Math.round(height * .1), Math.round(width * .84), Math.round(height * .22), Math.max(48, Math.round(width / 18)), '#182019', 680)]);
		title.value = fresh.name;
		loadDocument(fresh);
		setStatus(`New ${fresh.name} canvas`);
	}));

	const save = async (mode) => {
		const publish = mode === 'publish';
		const close = mode === 'close';
		const vellum = api();
		if (!vellum?.ready) { setStatus('Designer is still loading.', true); return; }
		if (publish && !channel.value) { setStatus('Choose a channel before publishing.', true); channel.focus(); return; }
		if (channel.value) resizeForChannel(config.channelFormats?.[channel.value] || '');
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
			if (close) window.location.assign(config.listUrl);
		} catch (error) { setStatus(error.message, true); }
		finally { document.querySelectorAll('[data-design-save]').forEach((button) => button.disabled = false); }
	};
	document.querySelectorAll('[data-design-save]').forEach((button) => button.addEventListener('click', () => save(button.dataset.designSave)));
});
