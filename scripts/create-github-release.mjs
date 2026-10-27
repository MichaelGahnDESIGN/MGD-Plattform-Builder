import fs from "node:fs/promises";
import path from "node:path";
import https from "node:https";

const token = process.env.GITHUB_TOKEN;
const repository = process.env.GITHUB_REPOSITORY;
const version = process.env.VERSION;
const targetCommitish = process.env.GITHUB_SHA;
const distDir = process.env.DIST_DIR || "dist";

if (!token || !repository || !version || !targetCommitish) {
  throw new Error("GITHUB_TOKEN, GITHUB_REPOSITORY, VERSION and GITHUB_SHA are required.");
}

const tag = `v${version}`;
const apiBase = "https://api.github.com";
const [owner, repo] = repository.split("/");

function request(method, url, { json, body, headers = {} } = {}) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const payload = json !== undefined
      ? Buffer.from(JSON.stringify(json))
      : body !== undefined
        ? Buffer.from(body)
        : null;

    const req = https.request(
      parsed,
      {
        method,
        headers: {
          "Accept": "application/vnd.github+json",
          "Authorization": `Bearer ${token}`,
          "User-Agent": "mgd-platform-release-workflow",
          "X-GitHub-Api-Version": "2022-11-28",
          ...(payload ? { "Content-Length": payload.length } : {}),
          ...(json !== undefined ? { "Content-Type": "application/json" } : {}),
          ...headers,
        },
      },
      (res) => {
        const chunks = [];
        res.on("data", (chunk) => chunks.push(chunk));
        res.on("end", () => {
          const buffer = Buffer.concat(chunks);
          const text = buffer.toString("utf8");

          if (res.statusCode >= 200 && res.statusCode < 300) {
            if (!text) {
              resolve({ status: res.statusCode, data: null });
              return;
            }

            try {
              resolve({ status: res.statusCode, data: JSON.parse(text) });
            } catch {
              resolve({ status: res.statusCode, data: text });
            }
            return;
          }

          const error = new Error(
            `${method} ${url} failed with HTTP ${res.statusCode}: ${text}`
          );
          error.status = res.statusCode;
          error.responseText = text;
          reject(error);
        });
      }
    );

    req.on("error", reject);
    if (payload) req.write(payload);
    req.end();
  });
}

async function getReleaseByTag() {
  try {
    const response = await request(
      "GET",
      `${apiBase}/repos/${owner}/${repo}/releases/tags/${encodeURIComponent(tag)}`
    );
    return response.data;
  } catch (error) {
    if (error.status === 404) return null;
    throw error;
  }
}

async function createOrUpdateRelease(notes) {
  const existing = await getReleaseByTag();

  const payload = {
    tag_name: tag,
    target_commitish: targetCommitish,
    name: `MGD-Plattform-Builder ${tag}`,
    body: notes,
    draft: false,
    prerelease: false,
  };

  if (!existing) {
    const response = await request(
      "POST",
      `${apiBase}/repos/${owner}/${repo}/releases`,
      { json: payload }
    );
    return response.data;
  }

  const response = await request(
    "PATCH",
    `${apiBase}/repos/${owner}/${repo}/releases/${existing.id}`,
    { json: payload }
  );
  return response.data;
}

function contentType(fileName) {
  if (fileName.endsWith(".zip")) return "application/zip";
  if (fileName.endsWith(".tgz") || fileName.endsWith(".tar.gz")) return "application/gzip";
  if (fileName.endsWith(".txt")) return "text/plain; charset=utf-8";
  return "application/octet-stream";
}

async function uploadAssets(release, files) {
  const assetsResponse = await request(
    "GET",
    `${apiBase}/repos/${owner}/${repo}/releases/${release.id}/assets`
  );
  const existingAssets = Array.isArray(assetsResponse.data) ? assetsResponse.data : [];

  for (const file of files) {
    const fileName = path.basename(file);
    const previous = existingAssets.find((asset) => asset.name === fileName);

    if (previous) {
      await request(
        "DELETE",
        `${apiBase}/repos/${owner}/${repo}/releases/assets/${previous.id}`
      );
    }

    const data = await fs.readFile(file);
    const uploadUrl =
      `https://uploads.github.com/repos/${owner}/${repo}/releases/${release.id}/assets` +
      `?name=${encodeURIComponent(fileName)}`;

    await request("POST", uploadUrl, {
      body: data,
      headers: {
        "Content-Type": contentType(fileName),
      },
    });

    console.log(`Uploaded ${fileName}`);
  }
}

const notesPath = path.join(distDir, "RELEASE_NOTES.md");
const notes = await fs.readFile(notesPath, "utf8");
const release = await createOrUpdateRelease(notes);

const files = (await fs.readdir(distDir))
  .filter((name) =>
    name.endsWith(".zip") ||
    name.endsWith(".tgz") ||
    name === "SHA256SUMS.txt"
  )
  .map((name) => path.join(distDir, name));

await uploadAssets(release, files);

console.log(`Release ready: ${release.html_url}`);
