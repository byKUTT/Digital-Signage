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
	const templates = {
		'daily-offers': () => documentFrom('Daily offers', 1920, 1080, '#10261d', (root) => [
			node('rect', { parentId: root, name: 'Accent panel', x: 1180, y: 0, w: 740, h: 1080, fill: '#d8ff54' }),
			text(root, 'Heading', 'TODAY\'S\nOFFERS', 110, 90, 980, 310, 146, '#f5f1e8', 750),
			text(root, 'Date', 'FRESHLY MADE · ALL DAY', 118, 438, 850, 55, 34, '#9fc3af', 550),
			text(root, 'Offer one', 'Lunch special', 118, 620, 700, 72, 55, '#f5f1e8', 650),
			text(root, 'Offer one price', '€8.90', 118, 704, 700, 128, 112, '#f5f1e8', 760),
			text(root, 'Offer two', 'Coffee + dessert', 1250, 150, 540, 150, 62, '#10261d', 650),
			text(root, 'Offer two price', '€5.50', 1250, 360, 540, 150, 118, '#10261d', 760),
			text(root, 'Footer', 'Available while supplies last', 1250, 900, 540, 70, 30, '#365341', 500)
		]),
		'weekly-menu': () => documentFrom('Weekly menu', 1920, 1080, '#f1e9dc', (root) => [
			node('rect', { parentId: root, name: 'Header', x: 0, y: 0, w: 1920, h: 245, fill: '#e55e35' }),
			text(root, 'Heading', 'THIS WEEK', 92, 65, 950, 120, 100, '#fff9ef', 740),
			text(root, 'Subtitle', 'Lunch menu · 11:00–15:00', 1220, 92, 610, 60, 34, '#fff9ef', 500, 'right'),
			text(root, 'Monday', 'MON\nRoast chicken · €9.50', 110, 340, 760, 170, 48, '#382b25', 650),
			text(root, 'Tuesday', 'TUE\nCreamy salmon · €10.50', 1010, 340, 780, 170, 48, '#382b25', 650),
			text(root, 'Wednesday', 'WED\nMushroom pasta · €8.90', 110, 640, 760, 170, 48, '#382b25', 650),
			text(root, 'Thursday', 'THU\nBeef stew · €9.90', 1010, 640, 780, 170, 48, '#382b25', 650)
		]),
		'event': () => documentFrom('Event announcement', 1920, 1080, '#1f39d1', (root) => [
			node('rect', { parentId: root, name: 'Event block', x: 1050, y: 90, w: 740, h: 900, fill: '#c8ff45', radius: 36 }),
			text(root, 'Label', 'SPECIAL EVENT', 110, 105, 800, 60, 36, '#aab8ff', 650),
			text(root, 'Heading', 'SUMMER\nNIGHT', 105, 250, 850, 360, 150, '#ffffff', 760),
			text(root, 'Date', '24 AUGUST', 1160, 200, 520, 120, 75, '#142160', 740),
			text(root, 'Time', '18:00', 1160, 400, 520, 160, 132, '#142160', 760),
			text(root, 'Details', 'Live music\nSeasonal menu\nFree entry', 1160, 650, 520, 220, 46, '#142160', 600)
		]),
		'welcome': () => documentFrom('Welcome screen', 1080, 1920, '#431529', (root) => [
			node('ellipse', { parentId: root, name: 'Sun', x: 565, y: 170, w: 360, h: 360, fill: '#ffcf4f' }),
			node('rect', { parentId: root, name: 'Lower panel', x: 0, y: 1260, w: 1080, h: 660, fill: '#f6dce8' }),
			text(root, 'Heading', 'WELCOME', 90, 620, 900, 190, 130, '#fff5ee', 760),
			text(root, 'Message', 'We are open\nand happy to see you.', 95, 855, 850, 260, 58, '#eebed1', 580),
			text(root, 'Hours', 'TODAY\n09:00–21:00', 90, 1400, 900, 260, 64, '#431529', 700),
			text(root, 'Footer', 'Ask our team about today’s favourites', 90, 1750, 900, 80, 31, '#75465b', 500)
		]),
		'blank': () => documentFrom('Blank landscape', 1920, 1080, '#f4f0e8', (root) => [
			text(root, 'Headline', 'Your message', 150, 135, 1620, 260, 118, '#182019', 680)
		])
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
