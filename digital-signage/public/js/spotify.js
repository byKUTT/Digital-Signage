document.addEventListener('DOMContentLoaded', () => {
	const app = document.querySelector('[data-spotify-app]');
	if (!app || !window.DSSpotify) return;
	const config = window.DSSpotify;
	const status = app.querySelector('[data-spotify-status]');
	const devices = app.querySelector('[data-spotify-devices]');
	const results = app.querySelector('[data-spotify-results]');
	const search = app.querySelector('[data-spotify-search]');
	const selectedDevice = () => devices?.value || config.deviceId || '';
	const message = (value, error = false) => { status.textContent = value; status.classList.toggle('error', error); };
	const call = async (operation, extra = {}) => {
		const body = new URLSearchParams({ action: 'ds_spotify_control', nonce: config.nonce, controller_id: config.controllerId, operation, ...extra });
		const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
		const json = await response.json();
		if (!json.success) throw new Error(json.data?.message || 'Spotify request failed.');
		return json.data;
	};
	const loadDevices = async () => {
		try {
			const data = await call('devices');
			devices.replaceChildren(...(data.devices || []).map((device) => {
				const option = document.createElement('option'); option.value = device.id || ''; option.textContent = `${device.name} · ${device.type}`; option.selected = option.value === config.deviceId; return option;
			}));
			if (!devices.options.length) { const option = document.createElement('option'); option.textContent = 'Open Spotify on a playback device'; option.value = ''; devices.append(option); }
			message('Spotify Connect devices refreshed.');
		} catch (error) { message(error.message, true); }
	};
	const renderTracks = (items) => {
		results.replaceChildren(...items.map((track) => {
			const button = document.createElement('button'); button.type = 'button'; button.className = 'ds-spotify-track'; button.dataset.uri = track.uri;
			const image = document.createElement('img'); image.src = track.album?.images?.[2]?.url || track.album?.images?.[0]?.url || ''; image.alt = '';
			const copy = document.createElement('span'); const title = document.createElement('strong'); const artist = document.createElement('small'); title.textContent = track.name; artist.textContent = (track.artists || []).map((item) => item.name).join(', '); copy.append(title, artist);
			const play = document.createElement('b'); play.textContent = 'Play'; button.append(image, copy, play); return button;
		}));
	};
	app.addEventListener('click', async (event) => {
		const control = event.target.closest('[data-spotify-action]'); const track = event.target.closest('[data-uri]');
		if (!control && !track) return;
		try { const operation = track ? 'play' : control.dataset.spotifyAction; message('Sending command…'); await call(operation, { device_id: selectedDevice(), uri: track?.dataset.uri || '' }); message(operation === 'play' ? 'Playback started.' : 'Command completed.'); }
		catch (error) { message(error.message, true); }
	});
	devices?.addEventListener('change', async () => { if (!selectedDevice()) return; try { await call('transfer', { device_id: selectedDevice() }); message('Controller output selected.'); } catch (error) { message(error.message, true); } });
	search?.addEventListener('submit', async (event) => { event.preventDefault(); const query = new FormData(search).get('query')?.trim(); if (!query) return; try { message('Searching Spotify…'); const data = await call('search', { query }); renderTracks(data.tracks?.items || []); message(`${data.tracks?.items?.length || 0} tracks found.`); } catch (error) { message(error.message, true); } });
	loadDevices();
});
