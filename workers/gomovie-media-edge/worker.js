/**
 * Gomovie private-media edge reference implementation.
 * Never configure a public R2 domain. This worker does not implement DRM.
 * Deploy only after independent security, rights, load and geo verification.
 */

const encoder = new TextEncoder();
const MAX_PLAYLIST_BYTES = 2097152;
const PRIVATE_TYPES = {
  m3u8: "application/vnd.apple.mpegurl",
  ts: "video/mp2t",
  m4s: "video/iso.segment",
  mp4: "video/mp4",
  vtt: "text/vtt; charset=utf-8",
  key: "application/octet-stream",
};

export function canonicalKey(raw) {
  if (typeof raw !== "string" || raw.length === 0 || raw.length > 1024) throw Error("Invalid key");
  let value;
  try { value = decodeURIComponent(raw); } catch { throw Error("Invalid encoding"); }
  if (!/^[A-Za-z0-9_./-]+$/.test(value) ||
      value.split("/").some((p) => p === "" || p === "." || p === "..")) {
    throw Error("Invalid private media key");
  }
  return value;
}

export function canonicalPayload(manifest, uid, pid, sid, expires) {
  return ["gomovie-hls-v1", manifest, uid, pid, sid, expires].join("\n");
}

export async function signPayload(payload, secret) {
  const key = await crypto.subtle.importKey("raw", encoder.encode(secret), {name:"HMAC", hash:"SHA-256"}, false, ["sign"]);
  const raw = await crypto.subtle.sign("HMAC", key, encoder.encode(payload));
  return [...new Uint8Array(raw)].map((n) => n.toString(16).padStart(2, "0")).join("");
}

async function verifyPayload(payload, secret, hex) {
  if (!/^[a-f0-9]{64}$/.test(hex)) return false;
  const key = await crypto.subtle.importKey("raw", encoder.encode(secret), {name:"HMAC", hash:"SHA-256"}, false, ["verify"]);
  const bytes = new Uint8Array(hex.match(/.{2}/g).map((part) => parseInt(part, 16)));
  return crypto.subtle.verify("HMAC", key, bytes, encoder.encode(payload));
}

export async function verifyGrant(key, params, secret, nowSeconds) {
  if (typeof secret !== "string" || secret.length < 32) return null;
  const uid = params.get("uid");
  const pid = params.get("pid");
  const sid = params.get("sid");
  const exp = params.get("exp");
  const sig = params.get("sig");
  const manifest = canonicalKey(params.get("mk") || key);
  if (params.get("v") !== "1" ||
      !/^[1-9][0-9]*$/.test(uid || "") || !/^[1-9][0-9]*$/.test(pid || "") ||
      !/^[0-9a-fA-F-]{36}$/.test(sid || "") || !/^[0-9]{10,12}$/.test(exp || "") ||
      !Number.isSafeInteger(Number(exp)) || Number(exp) <= nowSeconds ||
      Number(exp) > nowSeconds + 360 ||
      !manifest.endsWith(".m3u8")) return null;

  const base = manifest.slice(0, manifest.lastIndexOf("/") + 1);
  // Asset-scoped grant: no other media asset or object folder is accessible.
  if (key !== manifest && (!base || !key.startsWith(base))) return null;
  const signatureValid = await verifyPayload(
    canonicalPayload(manifest, uid, pid, sid, exp), secret, sig || ""
  );
  return signatureValid ? {uid, pid, sid, manifest} : null;
}

function rewriteLocalUri(reference, playlistKey, manifest, signed) {
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(reference) || reference.startsWith("/") ||
      reference.includes("?") || reference.includes("#")) throw Error("Unsafe HLS reference");
  const directory = playlistKey.slice(0, playlistKey.lastIndexOf("/") + 1);
  const key = canonicalKey(directory + reference);
  const allowed = manifest.slice(0, manifest.lastIndexOf("/") + 1);
  if (!allowed || !key.startsWith(allowed)) throw Error("Cross-asset HLS reference");
  const params = new URLSearchParams(signed);
  params.set("mk", manifest);
  return "/" + key.split("/").map(encodeURIComponent).join("/") + "?" + params.toString();
}

export function rewritePlaylist(body, playlistKey, manifest, signed) {
  if (typeof body !== "string" || !body.startsWith("#EXTM3U")) throw Error("Not an HLS playlist");
  return body.split(/\r?\n/).map((line) => {
    if (!line.trim()) return line;
    if (line.startsWith("#")) {
      return line.replace(/URI="([^"]+)"/g, (_tag, uri) =>
        'URI="' + rewriteLocalUri(uri, playlistKey, manifest, signed) + '"');
    }
    return rewriteLocalUri(line.trim(), playlistKey, manifest, signed);
  }).join("\n");
}

function responseHeaders(ext, origin, allowedOrigin) {
  const headers = new Headers({
    "Content-Type": PRIVATE_TYPES[ext] || "application/octet-stream",
    "Cache-Control": "private, no-store, max-age=0",
    "X-Content-Type-Options": "nosniff",
    "Cross-Origin-Resource-Policy": "cross-origin",
    "Referrer-Policy": "no-referrer",
  });
  if (origin && origin === allowedOrigin) {
    headers.set("Access-Control-Allow-Origin", origin);
    headers.set("Access-Control-Allow-Methods", "GET, HEAD, OPTIONS");
    headers.set("Access-Control-Allow-Headers", "Content-Type");
    headers.set("Vary", "Origin");
  }
  return headers;
}

export default {
  async fetch(request, env) {
    const deny = (status = 403) => new Response(null, {status, headers:{"Cache-Control":"no-store"}});
    if (!["GET", "HEAD", "OPTIONS"].includes(request.method)) return deny(405);
    const origin = request.headers.get("Origin");
    if (request.method === "OPTIONS") {
      if (!origin || origin !== env.FRONTEND_ORIGIN) return deny();
      return new Response(null, {status:204,headers:responseHeaders("m3u8",origin,env.FRONTEND_ORIGIN)});
    }
    if (!env.PRIVATE_MEDIA || typeof env.PRIVATE_MEDIA.get !== "function" ||
        typeof env.APP_ORIGIN !== "string" || !env.APP_ORIGIN.startsWith("https://") ||
        typeof env.EDGE_SHARED_KEY !== "string" || env.EDGE_SHARED_KEY.length < 32) return deny(503);

    try {
      const url = new URL(request.url);
      const key = canonicalKey(url.pathname.slice(1));
      const ext = key.split(".").at(-1)?.toLowerCase();
      if (!Object.hasOwn(PRIVATE_TYPES, ext)) return deny(404);
      const grant = await verifyGrant(key, url.searchParams, env.MEDIA_SIGNING_KEY, Math.floor(Date.now()/1000));
      if (!grant) return deny();

      // No edge cache or origin fetch before live authorizer approval.
      const authorized = await fetch(new URL("/api/v1/internal/media/authorize", env.APP_ORIGIN), {
        method:"POST",
        redirect:"manual",
        headers:{"Content-Type":"application/json", "X-Gomovie-Edge-Key":env.EDGE_SHARED_KEY},
        body:JSON.stringify({uid:Number(grant.uid),pid:Number(grant.pid),sid:grant.sid,manifest_key:grant.manifest}),
      });
      if (!authorized.ok) return deny();

      const object = await env.PRIVATE_MEDIA.get(key);
      if (!object) return deny(404);
      const headers = responseHeaders(ext, origin, env.FRONTEND_ORIGIN);
      if (request.method === "HEAD") return new Response(null,{status:200,headers});

      if (ext === "m3u8") {
        if (object.size > MAX_PLAYLIST_BYTES) return deny();
        const playlist = rewritePlaylist(await object.text(), key, grant.manifest, url.searchParams);
        return new Response(playlist,{status:200,headers});
      }
      return new Response(object.body,{status:200,headers});
    } catch {
      return deny();
    }
  },
};
