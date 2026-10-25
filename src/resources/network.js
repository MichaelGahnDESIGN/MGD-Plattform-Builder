const ALLOWED = new Set(["api.osv.dev", "endoflife.date", "repo.packagist.org"]);
export async function requestJson(
  url,
  { body, fetcher = fetch, timeout = 12000, maxBytes = 8 * 1024 * 1024 } = {},
) {
  const target = new URL(url);
  if (
    target.protocol !== "https:" ||
    !ALLOWED.has(target.hostname) ||
    target.username ||
    target.password ||
    target.port
  )
    throw new Error("Quelle nicht zugelassen");
  if (target.hostname === 'repo.packagist.org' && (body || !/^\/p2\/[a-z0-9_.-]+\/[a-z0-9_.-]+\.json$/.test(target.pathname) || target.search || target.hash))
    throw new Error('Registry-Pfad nicht zugelassen');
  const response = await fetcher(target, {
    method: body ? "POST" : "GET",
    body: body ? JSON.stringify(body) : undefined,
    headers: {
      Accept: "application/json",
      ...(body ? { "Content-Type": "application/json" } : {}),
    },
    redirect: "error",
    signal: AbortSignal.timeout(timeout),
  });
  if (!response.ok)
    throw new Error("Quelle nicht verfügbar: HTTP " + response.status);
  if (Number(response.headers.get("content-length")) > maxBytes)
    throw new Error("Antwort zu groß");
  const reader = response.body.getReader();
  let size = 0;
  const chunks = [];
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    size += value.length;
    if (size > maxBytes) {
      await reader.cancel();
      throw new Error("Antwortgrenze erreicht");
    }
    chunks.push(value);
  }
  return JSON.parse(Buffer.concat(chunks).toString("utf8"));
}

export async function pool(items, fn, limit = 4) {
  const results = new Array(items.length);
  let index = 0;
  await Promise.all(
    Array.from({ length: Math.min(limit, items.length) }, async () => {
      while (index < items.length) {
        const i = index++;
        results[i] = await fn(items[i], i);
      }
    }),
  );
  return results;
}
