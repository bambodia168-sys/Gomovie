import test from 'node:test';
import assert from 'node:assert/strict';
import { canonicalKey, canonicalPayload, rewritePlaylist, signPayload, verifyGrant } from './worker.js';

const secret = '0123456789abcdef0123456789abcdef0123456789abcdef';
const manifest = 'movie/42/private/asset-123/master.m3u8';
const now = Math.floor(Date.now() / 1000);
async function signedParams(expires = now + 90) {
  const params = new URLSearchParams({
    v: '1',uid:'7',pid:'9',sid:'12345678-1234-4234-9234-123456789012',exp:String(expires)
  });
  params.set('sig', await signPayload(canonicalPayload(
    manifest,params.get('uid'),params.get('pid'),params.get('sid'),params.get('exp')),secret));
  return params;
}

test('canonical private keys reject path traversal, mixed encoding and foreign origins', () => {
  assert.equal(canonicalKey(manifest),manifest);
  for (const key of ['../secret.key','movie/./x.m3u8','movie//x.m3u8','movie/%2e%2e/x.ts','https://evil.test/x','movie/%00/key.key']) {
    assert.throws(() => canonicalKey(key));
  }
});

test('HMAC grant is restricted to one asset and expires', async () => {
  const params = await signedParams();
  assert.ok(await verifyGrant(manifest,params,secret,now));
  assert.ok(await verifyGrant('movie/42/private/asset-123/seg_1.ts',new URLSearchParams(params.toString()+'&mk='+manifest),secret,now));
  assert.equal(await verifyGrant('movie/43/asset/seg_1.ts',params,secret,now),null);
  const altered = await signedParams();
  altered.set('uid','8');
  assert.equal(await verifyGrant(manifest,altered,secret,now),null);
  const expired = await signedParams(now-1);
  assert.equal(await verifyGrant(manifest,expired,secret,now),null);
});

test('rewrites nested media, segment and encryption key references with grant', async () => {
  const params = await signedParams();
  const hls = '#EXTM3U\n#EXT-X-KEY:METHOD=AES-128,URI="keys/key.key"\n#EXT-X-STREAM-INF:BANDWIDTH=1200000\n720p/playlist.m3u8\n';
  const out = rewritePlaylist(hls,manifest,manifest,params);
  assert.match(out,/URI="\/movie\/42\/private\/asset-123\/keys\/key.key\?/);
  assert.match(out,/\/movie\/42\/private\/asset-123\/720p\/playlist.m3u8\?/);
  assert.match(out,/mk=movie%2F42%2Fprivate%2Fasset-123%2Fmaster.m3u8/);
  assert.throws(() => rewritePlaylist('#EXTM3U\nhttps://evil.test/x.ts',manifest,manifest,params));
});
