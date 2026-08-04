import { spawnSync } from "node:child_process";
import { readdirSync } from "node:fs";
import { dirname, relative, resolve } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const adminRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const serverRoot = resolve(adminRoot, "..", "..");
const testDirectory = resolve(adminRoot, "tests");
const tsxLoader = resolve(adminRoot, "node_modules", "tsx", "dist", "loader.mjs");
const testFiles = readdirSync(testDirectory)
  .filter(name => /\.test\.(?:ts|mjs)$/.test(name))
  .sort()
  .map(name => relative(serverRoot, resolve(testDirectory, name)));

const result = spawnSync(
  process.execPath,
  ["--import", pathToFileURL(tsxLoader).href, "--test", ...testFiles],
  { cwd: serverRoot, stdio: "inherit" }
);

if (result.error) {
  throw result.error;
}

process.exit(result.status ?? 1);
