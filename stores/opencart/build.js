import { cp, mkdir, readFile, rm, stat } from "node:fs/promises";
import { createRequire } from "node:module";
import { dirname, join, resolve } from "node:path";
import { tmpdir } from "node:os";
import { execFile } from "node:child_process";
import { promisify } from "node:util";

const execFileAsync = promisify(execFile);
const require = createRequire(import.meta.url);
const scriptDir = dirname(require.resolve("./build.js"));

async function ensureExists(path) {
	await stat(path);
}

async function zipDirectory(stagingDir, zipPath) {
	if (process.platform === "win32") {
		const script = `Compress-Archive -Path '${stagingDir}\\*' -DestinationPath '${zipPath}' -Force`;
		await execFileAsync("powershell.exe", ["-NoProfile", "-Command", script]);
		return;
	}

	await execFileAsync("zip", ["-r", zipPath, "."], {
		cwd: stagingDir,
	});
}

async function buildArchive() {
	const packagePath = join(scriptDir, "package.json");
	const packageRaw = await readFile(packagePath, "utf8");
	const { version } = JSON.parse(packageRaw);

	if (!version) {
		throw new Error("Version is missing in package.json");
	}

	const distDir = join(scriptDir, "dist");
	const archiveName = `opencart-ecommerce-connect-${version}.zip`;
	const archivePath = join(distDir, archiveName);
	const stagingDir = join(tmpdir(), `opencart-ecommerce-connect-${version}-${Date.now()}`);

	const sourceAdmin = resolve(scriptDir, "src", "admin");
	const sourceCatalog = resolve(scriptDir, "src", "catalog");
	const sourceLicense = resolve(scriptDir, "LICENSE");
	const sourceInstall = resolve(scriptDir, "src", "install.json");

	await ensureExists(sourceAdmin);
	await ensureExists(sourceCatalog);
	await ensureExists(sourceLicense);
	await ensureExists(sourceInstall);

	await mkdir(distDir, { recursive: true });
	await rm(stagingDir, { recursive: true, force: true });
	await mkdir(stagingDir, { recursive: true });

	await cp(sourceAdmin, join(stagingDir, "admin"), { recursive: true });
	await cp(sourceCatalog, join(stagingDir, "catalog"), { recursive: true });
	await cp(sourceLicense, join(stagingDir, "LICENSE"));
	await cp(sourceInstall, join(stagingDir, "install.json"));

	await rm(archivePath, { force: true });
	await zipDirectory(stagingDir, archivePath);
	await rm(stagingDir, { recursive: true, force: true });

	console.log(`Created: ${archivePath}`);
}

buildArchive().catch((error) => {
	console.error("Build failed:", error);
	process.exitCode = 1;
});
