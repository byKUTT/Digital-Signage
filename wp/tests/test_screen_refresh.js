/* Run the actual player functions with browser APIs stubbed. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let source = fs.readFileSync(require('node:path').join(__dirname, '../public/js/player.js'), 'utf8');
source = source.replace("\tif ( 'loading' === document.readyState ) {", "\twindow.testPlayer = { loadCachedPlaylist: loadCachedPlaylist, fetchPlaylist: fetchPlaylist, checkForChanges: checkForChanges, state: state, applyPlaylist: applyPlaylist, setApply: function ( fn ) { applyPlaylist = fn; } }; return;\n\tif ( 'loading' === document.readyState ) {");
let stored = null;
let reply = null;
let requested = '';
let applied = null;
const context = {
  window: { DS_PLAYER: { screenId: 1, restUrl: 'https://example.test/wp-json/ds/v1/screen/one' }, location: { search: '' } },
  navigator: { userAgent: 'test' },
  document: { documentElement: { classList: { add() {} } }, getElementById: () => null },
  localStorage: { getItem: () => stored, setItem: (key, value) => { stored = value; } },
  fetch: async (url) => { requested = url; return { ok: true, json: async () => reply }; },
  setTimeout() {}, clearTimeout() {}, setInterval() {}, clearInterval() {}, console,
};
vm.runInNewContext(source, context);
const player = context.window.testPlayer;
(async () => {
  stored = JSON.stringify({ screen_id: 2, channel_id: 202 });
  assert.equal(player.loadCachedPlaylist(), null, 'wrong-screen offline cache must be rejected');
  stored = JSON.stringify({ screen_id: 1, channel_id: 101 });
  assert.equal(player.loadCachedPlaylist().channel_id, 101);
  player.setApply(data => { applied = data; player.state.playlist = data; });
  reply = { screen_id: 2, channel_id: 202 };
  stored = null;
  await player.fetchPlaylist();
  assert.equal(applied, null, 'wrong-screen response must never render');
  assert.equal(stored, null, 'wrong-screen response must never be cached');
  reply = { screen_id: 1, channel_id: 101, screen_revision: 'save-1' };
  await player.fetchPlaylist();
  assert.equal(applied.channel_id, 101);
  assert.equal(JSON.parse(stored).screen_id, 1);
  assert.ok(requested.includes('_ds_screen=1') && requested.includes('_ds_time='));
  console.log('7 player identity and cache checks passed');
})().catch(error => { console.error(error); process.exitCode = 1; });
