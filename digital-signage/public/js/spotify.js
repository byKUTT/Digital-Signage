document.addEventListener('DOMContentLoaded', () => {
	const app = document.querySelector('[data-spotify-app]');
	if (!app || !window.DSSpotify) return;

	const config = window.DSSpotify;
	const elements = {
		status: app.querySelector('[data-spotify-status]'),
		devices: app.querySelector('[data-spotify-devices]'),
		results: app.querySelector('[data-spotify-results]'),
		search: app.querySelector('[data-spotify-search]'),
		artwork: app.querySelector('[data-spotify-artwork]'),
		title: app.querySelector('[data-spotify-title]'),
		artist: app.querySelector('[data-spotify-artist]'),
		seek: app.querySelector('[data-spotify-seek]'),
		position: app.querySelector('[data-spotify-position]'),
		duration: app.querySelector('[data-spotify-duration]'),
		volume: app.querySelector('[data-spotify-volume]'),
		queue: app.querySelector('[data-spotify-queue]'),
		toggle: app.querySelector('[data-spotify-action="toggle"]')
	};
	let player = null;
	let sdkDeviceId = '';
	let playback = { duration: 0, position: 0, paused: true, updatedAt: Date.now() };
	let shuffle = false;
	let repeat = 'off';

	const selectedDevice = () => elements.devices?.value || config.deviceId || sdkDeviceId || '';
	const formatTime = (milliseconds) => {
		const seconds = Math.max(0, Math.floor((Number(milliseconds) || 0) / 1000));
		return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
	};
	const message = (value, error = false) => {
		elements.status.textContent = value;
		elements.status.classList.toggle('error', error);
	};
	const call = async (operation, extra = {}) => {
		const body = new URLSearchParams({ action: 'ds_spotify_control', nonce: config.nonce, controller_id: config.controllerId, operation, ...extra });
		const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
		const json = await response.json();
		if (!json.success) throw new Error(json.data?.message || 'Spotify request failed.');
		return json.data;
	};
	const imageFor = (item) => item.album?.images?.[0]?.url || item.images?.[0]?.url || '';
	const artistsFor = (item) => (item.artists || item.album?.artists || []).map((artist) => artist.name).join(', ');

	const renderPlayback = (state) => {
		if (!state) return;
		const track = state.track_window?.current_track || state.item;
		playback = {
			duration: Number(state.duration || track?.duration_ms || 0),
			position: Number(state.position || state.progress_ms || 0),
			paused: typeof state.paused === 'boolean' ? state.paused : !state.is_playing,
			updatedAt: Date.now()
		};
		if (track) {
			elements.title.textContent = track.name || 'Spotify';
			elements.artist.textContent = artistsFor(track) || track.album?.name || '';
			elements.artwork.src = imageFor(track);
			elements.artwork.alt = track.album?.name ? `${track.album.name} cover` : '';
			elements.artwork.closest('.ds-spotify-art')?.classList.toggle('has-artwork', Boolean(elements.artwork.src));
		}
		elements.seek.max = Math.max(1, playback.duration);
		elements.seek.value = playback.position;
		elements.position.textContent = formatTime(playback.position);
		elements.duration.textContent = formatTime(playback.duration);
		elements.toggle.textContent = playback.paused ? '▶' : 'Ⅱ';
	};

	const loadDevices = async () => {
		const data = await call('devices');
		const remoteDevices = data.devices || [];
		const allDevices = sdkDeviceId ? [{ id: sdkDeviceId, name: 'This controller', type: 'Web Playback SDK' }, ...remoteDevices.filter((device) => device.id !== sdkDeviceId)] : remoteDevices;
		elements.devices.replaceChildren(...allDevices.map((device) => {
			const option = document.createElement('option');
			option.value = device.id || '';
			option.textContent = `${device.name} · ${device.type}`;
			option.selected = option.value === (config.deviceId || sdkDeviceId);
			return option;
		}));
		if (!elements.devices.options.length) {
			const option = document.createElement('option');
			option.textContent = 'Open Spotify on a playback device';
			option.value = '';
			elements.devices.append(option);
		}
	};

	const resultCard = (item, type) => {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'ds-spotify-track';
		button.dataset.uri = item.uri || '';
		button.dataset.itemType = type;
		const image = document.createElement('img');
		image.src = imageFor(item);
		image.alt = '';
		const copy = document.createElement('span');
		const title = document.createElement('strong');
		const detail = document.createElement('small');
		title.textContent = item.name || '';
		detail.textContent = artistsFor(item) || (type === 'playlist' ? 'Playlist' : 'Album');
		copy.append(title, detail);
		const play = document.createElement('b');
		play.textContent = 'Play';
		button.append(image, copy, play);
		return button;
	};

	const renderSearch = (data) => {
		const cards = [
			...(data.tracks?.items || []).map((item) => resultCard(item, 'track')),
			...(data.albums?.items || []).map((item) => resultCard(item, 'album')),
			...(data.playlists?.items || []).filter(Boolean).map((item) => resultCard(item, 'playlist'))
		];
		elements.results.replaceChildren(...cards);
		return cards.length;
	};

	const renderQueue = async () => {
		try {
			const data = await call('queue');
			const rows = (data.queue || []).slice(0, 5).map((track) => {
				const row = document.createElement('div');
				row.className = 'ds-spotify-queue-row';
				const image = document.createElement('img');
				image.src = imageFor(track);
				image.alt = '';
				const copy = document.createElement('span');
				const title = document.createElement('strong');
				const artist = document.createElement('small');
				title.textContent = track.name || '';
				artist.textContent = artistsFor(track);
				copy.append(title, artist);
				row.append(image, copy);
				return row;
			});
			elements.queue.replaceChildren(...rows);
		} catch (error) {
			elements.queue.textContent = '';
		}
	};

	const initializeSdk = async () => {
		if (!window.Spotify?.Player || player) return;
		player = new window.Spotify.Player({
			name: config.playerName,
			getOAuthToken: async (callback) => {
				try { callback((await call('token')).access_token); }
				catch (error) { message(error.message, true); }
			},
			volume: 0.5
		});
		player.addListener('ready', async ({ device_id: deviceId }) => {
			sdkDeviceId = deviceId;
			await loadDevices();
			message('This controller is ready as a Spotify output.');
		});
		player.addListener('not_ready', () => message('This controller is temporarily unavailable.', true));
		player.addListener('player_state_changed', renderPlayback);
		['initialization_error', 'authentication_error', 'account_error', 'playback_error'].forEach((eventName) => {
			player.addListener(eventName, ({ message: errorMessage }) => message(errorMessage, true));
		});
		player.addListener('autoplay_failed', () => message('Press play once to allow browser audio.', true));
		await player.connect();
	};

	window.onSpotifyWebPlaybackSDKReady = initializeSdk;
	if (window.Spotify?.Player) initializeSdk();

	app.addEventListener('click', async (event) => {
		const control = event.target.closest('[data-spotify-action]');
		const item = event.target.closest('[data-uri]');
		if (!control && !item) return;
		try {
			await player?.activateElement();
			if (item) {
				await call('play', { device_id: selectedDevice(), uri: item.dataset.uri || '', item_type: item.dataset.itemType || 'track' });
				message('Playback started.');
			} else {
				const action = control.dataset.spotifyAction;
				if (action === 'toggle') {
					if (selectedDevice() === sdkDeviceId && player) await player.togglePlay();
					else await call(playback.paused ? 'play' : 'pause', { device_id: selectedDevice() });
				} else if (action === 'shuffle') {
					shuffle = !shuffle;
					await call('shuffle', { device_id: selectedDevice(), state: shuffle ? 'true' : 'false' });
					control.classList.toggle('active', shuffle);
				} else if (action === 'repeat') {
					repeat = repeat === 'off' ? 'context' : repeat === 'context' ? 'track' : 'off';
					await call('repeat', { device_id: selectedDevice(), state: repeat });
					control.classList.toggle('active', repeat !== 'off');
				} else {
					await call(action, { device_id: selectedDevice() });
				}
			}
			setTimeout(async () => {
				try { renderPlayback(await call('status')); await renderQueue(); } catch (error) { /* Playback may still be activating. */ }
			}, 500);
		} catch (error) { message(error.message, true); }
	});

	elements.devices?.addEventListener('change', async () => {
		if (!selectedDevice()) return;
		try {
			await player?.activateElement();
			await call('transfer', { device_id: selectedDevice() });
			message(selectedDevice() === sdkDeviceId ? 'Playing on this controller.' : 'Spotify Connect output selected.');
		} catch (error) { message(error.message, true); }
	});
	elements.search?.addEventListener('submit', async (event) => {
		event.preventDefault();
		const query = new FormData(elements.search).get('query')?.trim();
		if (!query) return;
		try {
			message('Searching Spotify…');
			const count = renderSearch(await call('search', { query }));
			message(`${count} results found.`);
		} catch (error) { message(error.message, true); }
	});
	elements.seek?.addEventListener('change', async () => {
		try { await call('seek', { device_id: selectedDevice(), position_ms: elements.seek.value }); }
		catch (error) { message(error.message, true); }
	});
	elements.volume?.addEventListener('change', async () => {
		try {
			if (selectedDevice() === sdkDeviceId && player) await player.setVolume(Number(elements.volume.value) / 100);
			else await call('volume', { device_id: selectedDevice(), volume_percent: elements.volume.value });
		} catch (error) { message(error.message, true); }
	});

	setInterval(() => {
		if (!playback.paused && playback.duration) {
			playback.position = Math.min(playback.duration, playback.position + (Date.now() - playback.updatedAt));
			playback.updatedAt = Date.now();
			elements.seek.value = playback.position;
			elements.position.textContent = formatTime(playback.position);
		}
	}, 1000);
	Promise.all([loadDevices(), call('status'), renderQueue()])
		.then(([, state]) => renderPlayback(state))
		.catch((error) => message(error.message, true));
});
