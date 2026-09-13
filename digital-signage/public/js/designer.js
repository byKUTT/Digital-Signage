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
		parentId, name, text: value, x, y, w, h, fontFamily: 'Inter', fontSize: size, fontWeight: weight,
		fontStyle: 'normal', lineHeight: 1.08, letterSpacing: 0, textAlign: align, textDecoration: 'none',
		textCase: 'none', direction: 'auto', fill: color
	});
	const documentFrom = (name, width, height, background, children) => {
		const pageId = uid();
		const root = node('frame', { name: `${width} × ${height}`, w: width, h: height, fill: background, clip: true });
		return { format: 'vellum', version: 1, name, pages: [{ id: pageId, name, nodes: [root, ...children(root.id)] }], pageId, assets: {}, fonts: {}, components: {}, tokens: { colors: [], typography: [] } };
	};
	const templateCopy = {
		offers: { name: 'Daily offers', heading: 'TODAY\'S\nOFFERS', detail: 'Lunch special', value: '€8.90', note: 'Freshly made · all day' },
		menu: { name: 'Weekly menu', heading: 'THIS WEEK', detail: 'Roast chicken · €9.50\nCreamy salmon · €10.50\nMushroom pasta · €8.90', value: '11:00–15:00', note: 'MON · TUE · WED' },
		event: { name: 'Event announcement', heading: 'SUMMER\nNIGHT', detail: 'Live music · seasonal menu', value: '18:00', note: '24 AUGUST' },
		welcome: { name: 'Welcome screen', heading: 'WELCOME', detail: 'We are open and happy to see you.', value: '09:00–21:00', note: 'TODAY' }
	};
	const palettes = {
		offers: [['#10261d','#d8ff54','#f5f1e8'],['#f2a33b','#20160d','#fff7e8'],['#172b61','#ff7466','#f8fbff'],['#f4eadf','#6e2724','#2a1d19']],
		menu: [['#f1e9dc','#e55e35','#382b25'],['#d9e7dc','#173f35','#142c26'],['#17171a','#f3ce52','#faf7ee'],['#dce8f5','#1f4ba8','#15213a']],
		event: [['#1f39d1','#c8ff45','#ffffff'],['#40144f','#ff8a5b','#fff5ef'],['#0d3234','#f5d76f','#f5fbf7'],['#f0e7dc','#ec384d','#201416']],
		welcome: [['#431529','#ffcf4f','#fff5ee'],['#173d2c','#9de07d','#f7f3e8'],['#172b61','#90d8ed','#ffffff'],['#efe6d7','#d84932','#2c201b']]
	};
	const buildTemplate = (family, variation, orientation) => {
		const portrait = orientation === 'portrait';
		const width = portrait ? 1080 : 1920; const height = portrait ? 1920 : 1080;
		const copy = templateCopy[family]; const colors = palettes[family][variation - 1];
		const margin = Math.round(width * .07); const accentVertical = variation % 2 === 0;
		return documentFrom(`${copy.name} ${variation} · ${portrait ? 'Portrait' : 'Landscape'}`, width, height, colors[0], (root) => {
			const accent = portrait
				? node('rect', { parentId: root, name: 'Accent field', x: accentVertical ? 0 : Math.round(width * .62), y: accentVertical ? Math.round(height * .72) : 0, w: accentVertical ? width : Math.round(width * .38), h: accentVertical ? Math.round(height * .28) : height, fill: colors[1] })
				: node('rect', { parentId: root, name: 'Accent field', x: accentVertical ? 0 : Math.round(width * .68), y: accentVertical ? Math.round(height * .72) : 0, w: accentVertical ? width : Math.round(width * .32), h: accentVertical ? Math.round(height * .28) : height, fill: colors[1] });
			const headingY = variation === 3 ? Math.round(height * .3) : margin;
			const headingW = accentVertical ? Math.round(width * .86) : Math.round(width * .56);
			const foreground = colors[2]; const accentText = colors[0];
			return [
				accent,
				text(root, 'Heading', copy.heading, margin, headingY, headingW, Math.round(height * .3), portrait ? 112 : 142, foreground, 800),
				text(root, 'Note', copy.note, margin, portrait ? Math.round(height * .4) : Math.round(height * .42), headingW, Math.round(height * .08), portrait ? 30 : 34, foreground, 600),
				text(root, 'Primary detail', copy.detail, margin, portrait ? Math.round(height * .53) : Math.round(height * .6), accentVertical ? Math.round(width * .82) : Math.round(width * .52), Math.round(height * .2), portrait ? 42 : 52, foreground, 600),
				text(root, 'Key value', copy.value, accentVertical ? margin : Math.round(width * .72), accentVertical ? Math.round(height * .78) : Math.round(height * .23), accentVertical ? Math.round(width * .82) : Math.round(width * .22), Math.round(height * .18), portrait ? 72 : 104, accentText, 800, accentVertical ? 'left' : 'center')
			];
		});
	};
	const templates = {
		blank: () => documentFrom('Blank landscape', 1920, 1080, '#f4f0e8', (root) => [text(root, 'Headline', 'Your message', 150, 135, 1620, 260, 118, '#182019', 680)]),
		'blank-portrait': () => documentFrom('Blank portrait', 1080, 1920, '#f4f0e8', (root) => [text(root, 'Headline', 'Your message', 90, 140, 900, 360, 96, '#182019', 680)])
	};
	Object.keys(templateCopy).forEach((family) => [1,2,3,4].forEach((variation) => ['landscape','portrait'].forEach((orientation) => {
		templates[`${family}-${variation}-${orientation}`] = () => buildTemplate(family, variation, orientation);
	})));
	templates['daily-offers'] = templates['offers-1-landscape'];
	templates['weekly-menu'] = templates['menu-1-landscape'];
	templates.event = templates['event-1-landscape'];
	templates.welcome = templates['welcome-1-portrait'];
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
				const templateKey = templates[config.template] ? config.template : 'daily-offers';
				const fresh = templates[templateKey]();
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
